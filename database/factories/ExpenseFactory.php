<?php

namespace Database\Factories;

use App\Enums\SpendingCategory;
use App\Models\Expense;
use App\Models\ExpenseAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Expense> */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'expense_account_id' => ExpenseAccount::factory(),
            'merchant' => fake()->randomElement([
                "Trader Joe's", 'Amazon', 'Shell Gas', 'Netflix',
                'Starbucks', 'Target', 'Whole Foods', 'Costco',
                'Spotify', 'Uber', 'Chipotle', 'Walgreens',
            ]),
            'amount' => fake()->numberBetween(100, 50000),
            'category' => fake()->randomElement(SpendingCategory::cases()),
            'date' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function category(SpendingCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }
}
