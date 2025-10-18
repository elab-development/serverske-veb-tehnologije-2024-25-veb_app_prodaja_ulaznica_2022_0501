<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;
use App\Models\TicketType;

class TicketTypeSeeder extends Seeder
{
    public function run(): void
    {
        $events = Event::all()->keyBy('slug');

        $defs = [
            'tech-summit-belgrade-2025' => [
                ['name' => 'Early Bird', 'category' => 'promo',   'price' => 2500, 'total' => 300],
                ['name' => 'Standard',   'category' => 'standard', 'price' => 3500, 'total' => 1200],
                ['name' => 'VIP',        'category' => 'vip',     'price' => 9000, 'total' => 100],
            ],
            'rock-festival-novi-sad' => [
                ['name' => 'Fan Pit', 'category' => 'fan',     'price' => 4200, 'total' => 800],
                ['name' => 'Parter',  'category' => 'standard', 'price' => 3200, 'total' => 2000],
                ['name' => 'VIP',     'category' => 'vip',     'price' => 11000, 'total' => 150],
            ],
            'derbi-zvezda-vs-partizan' => [
                ['name' => 'Sever',   'category' => 'tribina', 'price' => 1800, 'total' => 6000],
                ['name' => 'Istok',   'category' => 'tribina', 'price' => 2400, 'total' => 7000],
                ['name' => 'Zapad',   'category' => 'tribina', 'price' => 2600, 'total' => 7000],
                ['name' => 'VIP',     'category' => 'vip',     'price' => 15000, 'total' => 400],
            ],
        ];

        foreach ($defs as $slug => $items) {
            $event = $events->get($slug);
            if (!$event) {
                continue;
            }

            foreach ($items as $t) {
                TicketType::updateOrCreate(
                    [
                        'event_id' => $event->id,
                        'name'     => $t['name'],
                    ],
                    [
                        'category'       => $t['category'],
                        'price'          => $t['price'],
                        'quantity_total' => $t['total'],
                        'quantity_sold'  => 0,
                        'sales_start_at' => null,
                        'sales_end_at'   => null,
                        'is_active'      => true,
                    ]
                );
            }
        }
    }
}
