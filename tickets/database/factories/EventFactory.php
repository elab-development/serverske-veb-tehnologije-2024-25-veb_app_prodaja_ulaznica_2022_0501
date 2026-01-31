<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $titles = [
            'Tech Summit Belgrade',
            'Rock Festival Novi Sad',
            'Derbi Zvezda vs Partizan',
            'Jazz Night at Kolarac',
            'Gaming Expo Balkan',
        ];

        $title = $this->faker->randomElement($titles);
        $start = Carbon::now()->addDays($this->faker->numberBetween(7, 120))->setHour(20)->setMinute(0);

        return [
            'title'       => $title,
            'slug'        => Str::slug($title) . '-' . Str::random(5),
            'description' => $this->faker->optional()->paragraph(),
            'venue'       => $this->faker->randomElement(['Štark Arena', 'Spens', 'Kolarac', 'Tašmajdan', 'Arena NS']),
            'city'        => $this->faker->randomElement(['Beograd', 'Novi Sad', 'Niš']),
            'start_at'    => $start,
            'end_at'      => (clone $start)->addHours(3),
        ];
    }
}
