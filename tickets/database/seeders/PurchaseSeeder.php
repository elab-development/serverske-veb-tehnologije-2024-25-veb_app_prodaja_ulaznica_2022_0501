<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\Purchase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseSeeder extends Seeder
{
    public function run(): void
    {
        $pera = User::where('email', 'pera@tickets.rs')->first();
        $mika = User::where('email', 'mika@tickets.rs')->first();
        $admin = User::where('email', 'admin@tickets.rs')->first();

        $events = Event::all()->keyBy('slug');

        $createPaid = function (User $user, TicketType $tt, int $qty) {
            $unit = $tt->price;
            $total = $unit * $qty;

            // u transakciji zbog usklađivanja sold
            DB::transaction(function () use ($user, $tt, $qty, $unit, $total) {
                Purchase::create([
                    'user_id'        => $user->id,
                    'event_id'       => $tt->event_id,
                    'ticket_type_id' => $tt->id,
                    'quantity'       => $qty,
                    'unit_price'     => $unit,
                    'total_amount'   => $total,
                    'status'         => 'paid',
                    'reserved_until' => null,
                ]);

                // uvećaj sold
                $tt->increment('quantity_sold', $qty);
            });
        };

        $createPending = function (User $user, TicketType $tt, int $qty, int $ttlMinutes = 10) {
            $unit = $tt->price;
            $total = $unit * $qty;

            Purchase::create([
                'user_id'        => $user->id,
                'event_id'       => $tt->event_id,
                'ticket_type_id' => $tt->id,
                'quantity'       => $qty,
                'unit_price'     => $unit,
                'total_amount'   => $total,
                'status'         => 'pending',
                'reserved_until' => Carbon::now()->addMinutes($ttlMinutes),
            ]);
        };

        // --- primeri kupovina ---

        // Pera kupuje Tech Summit (Early Bird) x2 (PAID)
        if ($event = $events->get('tech-summit-belgrade-2025')) {
            $tt = TicketType::where('event_id', $event->id)->where('name', 'Early Bird')->first();
            if ($tt && $pera) {
                $createPaid($pera, $tt, 2);
            }
        }

        // Mika kupuje Rock Festival (Fan Pit) x3 (PAID)
        if ($event = $events->get('rock-festival-novi-sad')) {
            $tt = TicketType::where('event_id', $event->id)->where('name', 'Fan Pit')->first();
            if ($tt && $mika) {
                $createPaid($mika, $tt, 3);
            }
        }

        // Admin (kao korisnik) rezerviše Derbi (Zapad) x4 (PENDING)
        if ($event = $events->get('derbi-zvezda-vs-partizan')) {
            $tt = TicketType::where('event_id', $event->id)->where('name', 'Zapad')->first();
            if ($tt && $admin) {
                $createPending($admin, $tt, 4, 15);
            }
        }

        // Još par random plaćenih kupovina za statistiku
        $randomUsers = User::inRandomOrder()->limit(5)->get();
        foreach ($randomUsers as $u) {
            $tt = TicketType::inRandomOrder()->first();
            if (!$tt) continue;

            $qty = rand(1, 3);
            $createPaid($u, $tt, $qty);
        }

        // I nekoliko pending/expired
        $moreUsers = User::inRandomOrder()->limit(3)->get();
        foreach ($moreUsers as $u) {
            $tt = TicketType::inRandomOrder()->first();
            if (!$tt) continue;

            // 50% pending budućnost, 50% expired prošlost
            if (rand(0, 1)) {
                $createPending($u, $tt, rand(1, 2), 8);
            } else {
                Purchase::create([
                    'user_id'        => $u->id,
                    'event_id'       => $tt->event_id,
                    'ticket_type_id' => $tt->id,
                    'quantity'       => 1,
                    'unit_price'     => $tt->price,
                    'total_amount'   => $tt->price,
                    'status'         => 'expired',
                    'reserved_until' => Carbon::now()->subMinutes(5),
                ]);
            }
        }
    }
}
