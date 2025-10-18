<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EventController extends Controller
{

    public function index(Request $request)
    {
        $validated = $request->validate([
            'q'         => ['sometimes', 'string', 'max:255'],
            'city'      => ['sometimes', 'string', 'max:255'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to'   => ['sometimes', 'date_format:Y-m-d'],
            'sort_by'   => ['sometimes', Rule::in(['title', 'start_at', 'created_at'])],
            'sort_dir'  => ['sometimes', Rule::in(['asc', 'desc'])],
            'page'      => ['sometimes', 'integer', 'min:1'],
            'per_page'  => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $sortBy  = $validated['sort_by'] ?? 'start_at';
        $sortDir = $validated['sort_dir'] ?? 'asc';
        $perPage = $validated['per_page'] ?? 15;

        $query = Event::query();

        // search
        if (!empty($validated['q'])) {
            $q = $validated['q'];
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('venue', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%");
            });
        }

        // filter: city
        if (!empty($validated['city'])) {
            $query->where('city', $validated['city']);
        }

        // filter: date range po start_at
        if (!empty($validated['date_from'])) {
            $query->whereDate('start_at', '>=', $validated['date_from']);
        }
        if (!empty($validated['date_to'])) {
            $query->whereDate('start_at', '<=', $validated['date_to']);
        }

        // sort
        $query->orderBy($sortBy, $sortDir);

        $events = $query->withCount('ticketTypes')->paginate($perPage);

        if ($events->isEmpty()) {
            return response()->json('No events found.', 404);
        }

        return EventResource::collection($events);
    }


    public function store(Request $request)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can create events'], 403);
        }

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'slug'        => ['required', 'string', 'max:255', 'unique:events,slug'],
            'description' => ['nullable', 'string'],
            'venue'       => ['required', 'string', 'max:255'],
            'city'        => ['nullable', 'string', 'max:255'],
            'start_at'    => ['required', 'date'],
            'end_at'      => ['nullable', 'date', 'after_or_equal:start_at'],
        ]);

        $event = Event::create($validated);

        return response()->json([
            'message' => 'Event created successfully',
            'event'   => new EventResource($event),
        ], 201);
    }


    public function show(Event $event)
    {
        $event->load('ticketTypes');

        return response()->json([
            'event' => new EventResource($event),
        ]);
    }

    public function update(Request $request, Event $event)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can update events'], 403);
        }

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'slug'        => ['sometimes', 'string', 'max:255', Rule::unique('events', 'slug')->ignore($event->id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'venue'       => ['sometimes', 'string', 'max:255'],
            'city'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'start_at'    => ['sometimes', 'date'],
            'end_at'      => ['sometimes', 'nullable', 'date', 'after_or_equal:start_at'],
        ]);

        $event->update($validated);

        return response()->json([
            'message' => 'Event updated successfully',
            'event'   => new EventResource($event),
        ]);
    }


    public function destroy(Event $event)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can delete events'], 403);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully']);
    }
}
