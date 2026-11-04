<?php

namespace Database\Factories;

use App\Models\Operator;
use App\Models\TimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeOff>
 */
class TimeOffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addWeek()->startOfDay();

        return [
            'operator_id' => Operator::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addDay(),
            'reason' => 'Ferie',
        ];
    }
}
