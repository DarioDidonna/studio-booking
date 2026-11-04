<?php

namespace Database\Factories;

use App\Models\Operator;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Operator>
 */
class OperatorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'is_active' => true,
        ];
    }

    /**
     * Give the operator a staff account to log in with.
     */
    public function withStaffAccount(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory()->staff()->state(['name' => $attributes['name']]),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
