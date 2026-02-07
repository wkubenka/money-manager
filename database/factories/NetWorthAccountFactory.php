<?php

namespace Database\Factories;

use App\Enums\AccountCategory;
use App\Models\NetWorthAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NetWorthAccount> */
class NetWorthAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => fake()->randomElement(AccountCategory::cases()),
            'name' => fake()->randomElement([
                'House', 'Car', '401k', 'Roth IRA', 'Brokerage',
                'Emergency Fund', 'Vacation Fund', 'Student Loan',
                'Mortgage', 'Checking Account', 'Savings Account',
            ]),
            'balance' => fake()->numberBetween(100000, 50000000),
            'sort_order' => 0,
        ];
    }

    public function category(AccountCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    public function emergencyFund(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => AccountCategory::Savings,
            'name' => 'Emergency Fund',
            'is_emergency_fund' => true,
        ]);
    }
}
