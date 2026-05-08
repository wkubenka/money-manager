<?php

namespace Database\Factories;

use App\Models\DebtScenario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtScenario>
 */
class DebtScenarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'strategy' => fake()->randomElement(['avalanche', 'snowball']),
            'extra_payment_cents' => fake()->numberBetween(0, 50000),
            'lump_sum_cents' => 0,
            'lump_sum_month' => 1,
            'sort_order' => 0,
        ];
    }
}
