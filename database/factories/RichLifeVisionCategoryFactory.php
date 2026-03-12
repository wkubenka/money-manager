<?php

namespace Database\Factories;

use App\Models\RichLifeVisionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RichLifeVisionCategory> */
class RichLifeVisionCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Health & Wellness',
                'Home & Living',
                'Travel & Experiences',
                'Generosity',
                'Career & Growth',
                'Relationships',
            ]),
            'sort_order' => 0,
        ];
    }
}
