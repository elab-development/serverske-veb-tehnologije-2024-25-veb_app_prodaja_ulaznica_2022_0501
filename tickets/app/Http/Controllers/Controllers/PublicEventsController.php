<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PublicEventsController extends Controller
{
    public function index(Request $request)
    {
        $limit   = (int) $request->input('limit', 10);
        $limit   = max(1, min(50, $limit));
        $refresh = $request->boolean('refresh', false);
        $q       = trim((string) $request->input('q', ''));

        $cacheKey = 'public_events_list:' . ($q ? Str::slug($q) : 'all') . ':' . $limit;
        $ttl      = 600; // 10 min

        if (!$refresh && Cache::has($cacheKey)) {
            return response()->json([
                'source' => 'cache',
                'events' => Cache::get($cacheKey),
            ]);
        }

        $sources = [
            // Art Institute of Chicago – Exhibitions API
            function () use ($limit, $q) {
                $url = 'https://api.artic.edu/api/v1/exhibitions';
                $resp = Http::withHeaders([
                    'User-Agent' => 'tickets-app/1.0 (+https://example.com)',
                    'Accept'     => 'application/json',
                ])
                    ->retry(2, 500)
                    ->timeout(8)
                    ->get($url, [
                        'limit' => 100,
                        'fields' => 'title,aic_start_at,aic_end_at,short_description,web_url,venue_display',
                    ]);

                if ($resp->failed()) return null;

                $data = $resp->json('data', []);
                // Normalizacija i filtriranje
                $events = collect($data)
                    ->filter(function ($e) use ($q) {
                        if ($q === '') return true;
                        $hay = strtolower(($e['title'] ?? '') . ' ' . ($e['short_description'] ?? '') . ' ' . ($e['venue_display'] ?? ''));
                        return str_contains($hay, strtolower($q));
                    })
                    ->map(function ($e) {
                        return [
                            'name'        => $e['title'] ?? 'Untitled Exhibition',
                            'description' => $e['short_description'] ?? null,
                            'category'    => 'Exhibition',
                            'link'        => $e['web_url'] ?? null,
                            'city'        => null,
                            'venue'       => $e['venue_display'] ?? null,
                            'start_at'    => $e['aic_start_at'] ?? null,
                            'end_at'      => $e['aic_end_at'] ?? null,
                            'source'      => 'artic',
                        ];
                    })
                    ->values()
                    ->take($limit);

                return $events->isNotEmpty() ? $events->all() : null;
            },
        ];

        $result = null;
        foreach ($sources as $fetch) {
            try {
                $result = $fetch();
                if (is_array($result) && !empty($result)) {
                    Cache::put($cacheKey, $result, $ttl);
                    return response()->json([
                        'source' => 'api',
                        'events' => $result,
                    ]);
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        $fallback = collect([
            [
                'name'        => 'Local Indie Showcase',
                'description' => 'Regionalni indie bendovi i umetnici.',
                'category'    => 'Music',
                'link'        => null,
                'city'        => 'Beograd',
                'venue'       => 'Dom omladine',
                'start_at'    => now()->addDays(14)->toIso8601String(),
                'end_at'      => now()->addDays(14)->addHours(4)->toIso8601String(),
                'source'      => 'fallback',
            ],
            [
                'name'        => 'Summer Open-Air Cinema',
                'description' => 'Projekcije domaćih i stranih filmova pod vedrim nebom.',
                'category'    => 'Cinema',
                'link'        => null,
                'city'        => 'Novi Sad',
                'venue'       => 'Danube Park',
                'start_at'    => now()->addDays(21)->toIso8601String(),
                'end_at'      => now()->addDays(21)->addHours(3)->toIso8601String(),
                'source'      => 'fallback',
            ],
            [
                'name'        => 'Tech Meetup: Laravel & Vue',
                'description' => 'Tehničko veče za Laravel/Vue developere.',
                'category'    => 'Tech',
                'link'        => null,
                'city'        => 'Niš',
                'venue'       => 'Startup Hub',
                'start_at'    => now()->addDays(7)->toIso8601String(),
                'end_at'      => now()->addDays(7)->addHours(2)->toIso8601String(),
                'source'      => 'fallback',
            ],
        ])
            ->when($q !== '', function ($c) use ($q) {
                return $c->filter(function ($e) use ($q) {
                    $hay = strtolower(($e['name'] ?? '') . ' ' . ($e['description'] ?? '') . ' ' . ($e['category'] ?? '') . ' ' . ($e['city'] ?? '') . ' ' . ($e['venue'] ?? ''));
                    return str_contains($hay, strtolower($q));
                });
            })
            ->take($limit)
            ->values()
            ->all();

        Cache::put($cacheKey, $fallback, $ttl);

        return response()->json([
            'source' => 'fallback',
            'events' => $fallback,
        ]);
    }
}
