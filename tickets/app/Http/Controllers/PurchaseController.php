<?php

namespace App\Http\Controllers;

use App\Http\Resources\PurchaseResource;
use App\Models\Event;
use App\Models\Purchase;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    /**
     * PUT /events/{event}/queue/join
     * Dodaje prijavljenog korisnika (role=user) u red čekanja za dati event.
     * Koristimo DB tabelu: waitlist_entries (bez Eloquent modela).
     */
    public function joinQueue(Request $request, Event $event)
    {
        if (!Auth::check() || Auth::user()->role !== 'user') {
            return response()->json(['error' => 'Only logged-in users with role=user can join queue'], 403);
        }
        $userId = Auth::id();

        // Pokušaj insert, u slučaju unique konflikta (event_id,user_id) – već je u redu
        try {
            DB::table('waitlist_entries')->insert([
                'event_id'   => $event->id,
                'user_id'    => $userId,
                'status'     => 'queued',
                'token'      => null,
                'ttl_until'  => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // ignore (najverovatnije unique constraint)
        }

        $entry = DB::table('waitlist_entries')
            ->where('event_id', $event->id)
            ->where('user_id', $userId)
            ->first();

        if (!$entry) {
            return response()->json(['error' => 'Queue join failed'], 422);
        }

        // Pozicija = broj queued sa ID <= mog ID (FIFO po auto-increment ID)
        $position = null;
        if ($entry->status === 'queued') {
            $position = DB::table('waitlist_entries')
                ->where('event_id', $event->id)
                ->where('status', 'queued')
                ->where('id', '<=', $entry->id)
                ->count();
        }

        $queueSize = DB::table('waitlist_entries')
            ->where('event_id', $event->id)
            ->where('status', 'queued')
            ->count();

        return response()->json([
            'message'    => 'Joined queue',
            'event_id'   => $event->id,
            'position'   => $position,
            'queue_size' => $queueSize,
        ]);
    }

    /**
     * GET /events/{event}/queue/status
     * Vraća trenutnu poziciju korisnika u redu i/ili gate_token ako je pušten.
     */
    public function queueStatus(Request $request, Event $event)
    {
        if (!Auth::check() || Auth::user()->role !== 'user') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $userId = Auth::id();

        $entry = DB::table('waitlist_entries')
            ->where(['event_id' => $event->id, 'user_id' => $userId])
            ->first();

        if (!$entry) {
            return response()->json(['error' => 'Not in queue'], 404);
        }

        $queueSize = DB::table('waitlist_entries')
            ->where('event_id', $event->id)
            ->where('status', 'queued')
            ->count();

        $position = null;
        if ($entry->status === 'queued') {
            $position = DB::table('waitlist_entries')
                ->where('event_id', $event->id)
                ->where('status', 'queued')
                ->where('id', '<=', $entry->id)
                ->count();
        }

        $gateToken = null;
        if (
            $entry->status === 'admitted' &&
            $entry->ttl_until &&
            Carbon::now()->lt(Carbon::parse($entry->ttl_until))
        ) {
            $gateToken = $entry->token;
        }

        return response()->json([
            'event_id'   => $event->id,
            'position'   => $position,
            'queue_size' => $queueSize,
            'gate_token' => $gateToken,
        ]);
    }

    /**
     * POST /events/{event}/queue/admit
     * Admin pušta sledećih N iz reda i izdaje gate token sa TTL-om.
     * body: { "count": 50, "ttl_seconds": 600 }
     */
    public function admitNext(Request $request, Event $event)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can admit queue'], 403);
        }

        $data = $request->validate([
            'count'       => ['sometimes', 'integer', 'min:1', 'max:2000'],
            'ttl_seconds' => ['sometimes', 'integer', 'min:60', 'max:3600'],
        ]);

        $count = $data['count'] ?? 50;
        $ttl   = $data['ttl_seconds'] ?? 600;

        $admitted = [];

        DB::transaction(function () use ($event, $count, $ttl, &$admitted) {
            // Pokupi N najstarijih "queued" i zaključa redove
            $rows = DB::table('waitlist_entries')
                ->where('event_id', $event->id)
                ->where('status', 'queued')
                ->orderBy('id', 'asc')
                ->limit($count)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $token    = Str::random(32);
                $ttlUntil = Carbon::now()->addSeconds($ttl);

                DB::table('waitlist_entries')
                    ->where('id', $row->id)
                    ->update([
                        'status'     => 'admitted',
                        'token'      => $token,
                        'ttl_until'  => $ttlUntil,
                        'updated_at' => now(),
                    ]);

                $admitted[] = ['user_id' => $row->user_id, 'token' => $token];
            }
        });

        return response()->json([
            'message'  => 'Admitted users',
            'event_id' => $event->id,
            'count'    => count($admitted),
            'admitted' => $admitted, // po želji možeš izostaviti token iz odziva
        ]);
    }

    // === Helper: provera gate tokena kroz DB waitlist ===
    private function assertValidGateTokenDb(Event $event, ?string $token, int $userId): ?\Illuminate\Http\JsonResponse
    {
        if (!$token) {
            return response()->json(['error' => 'Missing gate_token'], 403);
        }

        $row = DB::table('waitlist_entries')
            ->where([
                'event_id' => $event->id,
                'user_id'  => $userId,
                'token'    => $token,
                'status'   => 'admitted',
            ])->first();

        if (!$row) {
            return response()->json(['error' => 'Invalid gate_token'], 403);
        }

        if (!$row->ttl_until || Carbon::now()->gte(Carbon::parse($row->ttl_until))) {
            return response()->json(['error' => 'Expired gate_token'], 403);
        }

        return null;
    }

    /**
     * GET /purchases
     * - Admin: može filtrirati po user_id, event_id, ticket_type_id, status; vidi sve
     * - User: vidi samo svoje (isti skup filtera osim user_id koji se ignoriše)
     */
    public function index(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'user_id'        => ['sometimes', 'integer', 'min:1'],
            'event_id'       => ['sometimes', 'integer', 'min:1'],
            'ticket_type_id' => ['sometimes', 'integer', 'min:1'],
            'status'         => ['sometimes', Rule::in(['pending', 'paid', 'cancelled', 'expired'])],
            'page'           => ['sometimes', 'integer', 'min:1'],
            'per_page'       => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort_by'        => ['sometimes', Rule::in(['created_at', 'status', 'total_amount'])],
            'sort_dir'       => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);

        $query = Purchase::query();
        $isAdmin = Auth::user()->role === 'admin';

        if ($isAdmin) {
            if (!empty($validated['user_id'])) {
                $query->where('user_id', $validated['user_id']);
            }
        } else {
            $query->where('user_id', Auth::id());
        }

        if (!empty($validated['event_id'])) {
            $query->where('event_id', $validated['event_id']);
        }
        if (!empty($validated['ticket_type_id'])) {
            $query->where('ticket_type_id', $validated['ticket_type_id']);
        }
        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $sortBy  = $validated['sort_by'] ?? 'created_at';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $perPage = $validated['per_page'] ?? 15;

        $query->orderBy($sortBy, $sortDir);

        $paginator = $query->with(['event', 'ticketType'])->paginate($perPage);

        if ($paginator->isEmpty()) {
            return response()->json('No purchases found.', 404);
        }

        return PurchaseResource::collection($paginator);
    }

    /**
     * GET /purchases/{purchase}
     * - Admin vidi sve; user vidi samo svoje
     */
    public function show(Purchase $purchase)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $isAdmin = Auth::user()->role === 'admin';
        if (!$isAdmin && $purchase->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $purchase->load(['event', 'ticketType', 'user']);

        return response()->json([
            'purchase' => new PurchaseResource($purchase),
        ]);
    }

    /**
     * POST /events/{event}/purchases/reserve
     * body: { "ticket_type_id": X, "quantity": Y, "gate_token": "..." , "ttl_minutes"?: 10 }
     * Kreira pending rezervaciju (reserved_until = now()+TTL).
     */
    public function reserve(Request $request, Event $event)
    {
        if (!Auth::check() || Auth::user()->role !== 'user') {
            return response()->json(['error' => 'Only logged-in users with role=user can reserve'], 403);
        }

        $validated = $request->validate([
            'ticket_type_id' => ['required', 'integer', 'min:1'],
            'quantity'       => ['required', 'integer', 'min:1', 'max:10'],
            'gate_token'     => ['required', 'string'],
            'ttl_minutes'    => ['sometimes', 'integer', 'min:2', 'max:30'],
        ]);

        // Provera čekao­ni­ce (gate token)
        if ($resp = $this->assertValidGateTokenDb($event, $validated['gate_token'], Auth::id())) {
            return $resp;
        }

        $ticketType = TicketType::where('id', $validated['ticket_type_id'])
            ->where('event_id', $event->id)
            ->first();

        if (!$ticketType || !$ticketType->is_active) {
            return response()->json(['error' => 'Ticket type not available'], 422);
        }

        // prozori prodaje
        if ($ticketType->sales_start_at && Carbon::now()->lt($ticketType->sales_start_at)) {
            return response()->json(['error' => 'Sales not started yet'], 422);
        }
        if ($ticketType->sales_end_at && Carbon::now()->gt($ticketType->sales_end_at)) {
            return response()->json(['error' => 'Sales ended'], 422);
        }

        $qty = (int) $validated['quantity'];
        $ttl = $validated['ttl_minutes'] ?? 10;

        $purchase = Purchase::create([
            'user_id'        => Auth::id(),
            'event_id'       => $event->id,
            'ticket_type_id' => $ticketType->id,
            'quantity'       => $qty,
            'unit_price'     => $ticketType->price,
            'total_amount'   => $ticketType->price * $qty,
            'status'         => 'pending',
            'reserved_until' => Carbon::now()->addMinutes($ttl),
        ]);

        return response()->json([
            'message'  => 'Reservation created',
            'purchase' => new PurchaseResource($purchase),
        ], 201);
    }

    /**
     * POST /purchases/{purchase}/pay
     * Transakcija + row-lock na ticket_types (sprečava oversell).
     */
    public function pay(Request $request, Purchase $purchase)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $isAdmin = Auth::user()->role === 'admin';
        if (!$isAdmin && $purchase->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if ($purchase->status !== 'pending') {
            return response()->json(['error' => 'Purchase must be pending to pay'], 422);
        }
        if ($purchase->reserved_until && Carbon::now()->gt($purchase->reserved_until)) {
            $purchase->update(['status' => 'expired']);
            return response()->json(['error' => 'Reservation expired'], 422);
        }

        DB::transaction(function () use ($purchase) {
            $tt = TicketType::where('id', $purchase->ticket_type_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($tt->quantity_sold + $purchase->quantity > $tt->quantity_total) {
                abort(response()->json(['error' => 'Not enough tickets available'], 422));
            }

            $tt->increment('quantity_sold', $purchase->quantity);
            $purchase->update([
                'status'         => 'paid',
                'reserved_until' => null,
            ]);
        });

        $purchase->load(['event', 'ticketType', 'user']);

        return response()->json([
            'message'  => 'Payment successful',
            'purchase' => new PurchaseResource($purchase),
        ]);
    }
}
