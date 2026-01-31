<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            [
                'title'       => 'Tech Summit Belgrade 2025',
                'venue'       => 'Štark Arena',
                'city'        => 'Beograd',
                'start_at'    => Carbon::parse('2025-12-05 10:00:00'),
                'end_at'      => Carbon::parse('2025-12-05 18:00:00'),
                'description' => 'Najveća tech konferencija u regionu – AI, Cloud, DevOps.',
            ],
            [
                'title'       => 'Rock Festival Novi Sad',
                'venue'       => 'SPENS',
                'city'        => 'Novi Sad',
                'start_at'    => Carbon::parse('2026-06-20 18:00:00'),
                'end_at'      => Carbon::parse('2026-06-21 01:00:00'),
                'description' => 'Dvodnevni rock festival sa regionalnim bendovima.',
            ],
            [
                'title'       => 'Derbi Zvezda vs Partizan',
                'venue'       => 'Stadion Rajko Mitić',
                'city'        => 'Beograd',
                'start_at'    => Carbon::parse('2025-11-22 19:00:00'),
                'end_at'      => Carbon::parse('2025-11-22 21:00:00'),
                'description' => 'Večiti derbi – fudbalski spektakl.',
            ],
        ];

        foreach ($events as $data) {
            Event::updateOrCreate(
                ['slug' => Str::slug($data['title'])],
                [
                    'title'       => $data['title'],
                    'slug'        => Str::slug($data['title']),
                    'description' => $data['description'],
                    'venue'       => $data['venue'],
                    'city'        => $data['city'],
                    'start_at'    => $data['start_at'],
                    'end_at'      => $data['end_at'],
                ]
            );
        }
    }
}
