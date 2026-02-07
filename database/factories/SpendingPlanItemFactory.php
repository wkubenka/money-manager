<?php

namespace Database\Factories;

use App\Enums\SpendingCategory;
use App\Models\SpendingPlan;
use App\Models\SpendingPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SpendingPlanItem> */
class SpendingPlanItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'spending_plan_id' => SpendingPlan::factory(),
            'category' => fake()->randomElement(SpendingCategory::cases()),
            'name' => fake()->randomElement(['Rent', 'Groceries', 'Insurance', '401k', 'Vacation Fund', 'Dining Out']),
            'amount' => fake()->numberBetween(5000, 200000),
            'sort_order' => 0,
        ];
    }

    /**
     * Set the item category.
     */
    public function category(SpendingCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }
}
