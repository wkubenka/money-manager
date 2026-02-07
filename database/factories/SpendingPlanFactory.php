<?php

namespace Database\Factories;

use App\Models\SpendingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SpendingPlan> */
class SpendingPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Current Plan', 'Dream Plan', 'If I Move Cities', 'After Raise']),
            'monthly_income' => fake()->numberBetween(300000, 1000000),
            'gross_monthly_income' => fake()->numberBetween(400000, 1500000),
            'pre_tax_investments' => fake()->numberBetween(20000, 200000),
        ];
    }
}
