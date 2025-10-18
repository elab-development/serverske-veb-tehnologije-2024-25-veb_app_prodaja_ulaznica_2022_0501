<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketTypeResource;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TicketTypeController extends Controller
{
    public function indexForEvent(Request $request, Event $event)
    {
        $validated = $request->validate([
            'is_active' => ['sometimes', Rule::in(['0', '1', 0, 1, true, false])],
            'category'  => ['sometimes', 'string', 'max:255'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0'],
            'sort_by'   => ['sometimes', Rule::in(['price', 'name', 'created_at'])],
            'sort_dir'  => ['sometimes', Rule::in(['asc', 'desc'])],
            'page'      => ['sometimes', 'integer', 'min:1'],
            'per_page'  => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $sortBy  = $validated['sort_by'] ?? 'price';
        $sortDir = $validated['sort_dir'] ?? 'asc';
        $perPage = $validated['per_page'] ?? 15;

        $query = TicketType::where('event_id', $event->id);

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', filter_var($validated['is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (int)$validated['is_active']);
        }

        if (!empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (!empty($validated['min_price'])) {
            $query->where('price', '>=', $validated['min_price']);
        }
        if (!empty($validated['max_price'])) {
            $query->where('price', '<=', $validated['max_price']);
        }

        $query->orderBy($sortBy, $sortDir);

        $types = $query->paginate($perPage);

        if ($types->isEmpty()) {
            return response()->json('No ticket types found.', 404);
        }

        return TicketTypeResource::collection($types);
    }

    public function store(Request $request, Event $event)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can create ticket types'], 403);
        }

        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'category'        => ['nullable', 'string', 'max:255'],
            'price'           => ['required', 'numeric', 'min:0'],
            'quantity_total'  => ['required', 'integer', 'min:1'],
            'quantity_sold'   => ['sometimes', 'integer', 'min:0'], // najčešće 0 kada se kreira
            'sales_start_at'  => ['nullable', 'date'],
            'sales_end_at'    => ['nullable', 'date', 'after_or_equal:sales_start_at'],
            'is_active'       => ['sometimes', 'boolean'],
        ]);

        $payload = array_merge($validated, ['event_id' => $event->id]);
        $ticketType = TicketType::create($payload);

        return response()->json([
            'message'     => 'Ticket type created successfully',
            'ticket_type' => new TicketTypeResource($ticketType),
        ], 201);
    }

    public function show(TicketType $ticketType)
    {
        $ticketType->load('event');

        return response()->json([
            'ticket_type' => new TicketTypeResource($ticketType),
        ]);
    }

    public function update(Request $request, TicketType $ticketType)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can update ticket types'], 403);
        }

        $validated = $request->validate([
            'name'            => ['sometimes', 'string', 'max:255'],
            'category'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'price'           => ['sometimes', 'numeric', 'min:0'],
            'quantity_total'  => ['sometimes', 'integer', 'min:1'],
            // quantity_sold se menja transakciono prilikom kupovine — po defaultu ga ne otvaramo kroz update
            'sales_start_at'  => ['sometimes', 'nullable', 'date'],
            'sales_end_at'    => ['sometimes', 'nullable', 'date', 'after_or_equal:sales_start_at'],
            'is_active'       => ['sometimes', 'boolean'],
        ]);

        $ticketType->update($validated);

        return response()->json([
            'message'     => 'Ticket type updated successfully',
            'ticket_type' => new TicketTypeResource($ticketType),
        ]);
    }

    public function destroy(TicketType $ticketType)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can delete ticket types'], 403);
        }

        $ticketType->delete();

        return response()->json(['message' => 'Ticket type deleted successfully']);
    }
}
