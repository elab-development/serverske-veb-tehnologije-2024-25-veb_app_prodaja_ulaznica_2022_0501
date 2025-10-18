<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\User;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        $ticketType = TicketType::factory()->create();
        $user = User::factory()->create();

        $qty = $this->faker->numberBetween(1, 4);
        $unit = $ticketType->price;
        $status = $this->faker->randomElement(['paid', 'pending', 'cancelled', 'expired']);

        return [
            'user_id'        => $user->id,
            'event_id'       => $ticketType->event_id,
            'ticket_type_id' => $ticketType->id,
            'quantity'       => $qty,
            'unit_price'     => $unit,
            'total_amount'   => $qty * $unit,
            'status'         => $status,
            'reserved_until' => in_array($status, ['pending']) ? Carbon::now()->addMinutes(10) : null,
        ];
    }

    public function paid(): static
    {
        return $this->state(function (array $attrs) {
            return ['status' => 'paid', 'reserved_until' => null];
        });
    }

    public function pending(): static
    {
        return $this->state(function (array $attrs) {
            return ['status' => 'pending', 'reserved_until' => now()->addMinutes(10)];
        });
    }

    public function expired(): static
    {
        return $this->state(function (array $attrs) {
            return ['status' => 'expired', 'reserved_until' => now()->subMinutes(1)];
        });
    }

    public function cancelled(): static
    {
        return $this->state(function (array $attrs) {
            return ['status' => 'cancelled', 'reserved_until' => null];
        });
    }
}
