<?php

namespace Database\Factories;

use App\Models\ExpenseAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExpenseAccount> */
class ExpenseAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement([
                'Chase Checking', 'Amex Credit Card', 'Wells Fargo Savings',
                'Capital One Card', 'Venmo', 'Cash', 'Apple Card',
            ]),
        ];
    }
}
