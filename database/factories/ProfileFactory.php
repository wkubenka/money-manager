<?php

namespace Database\Factories;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date_of_birth' => fake()->date(),
            'retirement_age' => 65,
            'expected_return' => 7.0,
            'withdrawal_rate' => 4.0,
            'emergency_fund_months' => 6,
        ];
    }
}
