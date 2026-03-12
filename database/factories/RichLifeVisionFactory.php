<?php

namespace Database\Factories;

use App\Models\RichLifeVision;
use App\Models\RichLifeVisionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RichLifeVision> */
class RichLifeVisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rich_life_vision_category_id' => null,
            'text' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }

    public function inCategory(RichLifeVisionCategory $category): static
    {
        return $this->state(fn () => [
            'rich_life_vision_category_id' => $category->id,
        ]);
    }
}
