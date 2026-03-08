<?php

namespace Database\Factories;

use App\Models\RichLifeVision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RichLifeVision> */
class RichLifeVisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'text' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }
}
