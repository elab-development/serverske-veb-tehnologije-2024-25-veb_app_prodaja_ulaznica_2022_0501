<?php

namespace Database\Factories;

use App\Models\TicketType;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketTypeFactory extends Factory
{
    protected $model = TicketType::class;

    public function definition(): array
    {
        $names = [
            ['name' => 'Parter', 'category' => 'standard'],
            ['name' => 'Tribina', 'category' => 'standard'],
            ['name' => 'Fan Pit', 'category' => 'fan'],
            ['name' => 'VIP', 'category' => 'vip'],
            ['name' => 'Early Bird', 'category' => 'promo'],
        ];
        $choice = $this->faker->randomElement($names);

        $price = $this->faker->randomElement([1500, 2200, 2900, 3500, 6000, 9500]) + 0.00;
        $total = $this->faker->numberBetween(50, 1000);

        return [
            'event_id'       => Event::factory(),
            'name'           => $choice['name'],
            'category'       => $choice['category'],
            'price'          => $price,
            'quantity_total' => $total,
            'quantity_sold'  => 0,
            'sales_start_at' => null,
            'sales_end_at'   => null,
            'is_active'      => true,
        ];
    }
}
