<?php

namespace Database\Factories;

use App\Enums\Weekday;
use App\Models\Operator;
use App\Models\WorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkingHour>
 */
class WorkingHourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_id' => Operator::factory(),
            'weekday' => Weekday::Monday,
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
        ];
    }
}
