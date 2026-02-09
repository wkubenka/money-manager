<?php

namespace Database\Factories;

use App\Models\RichLifeVision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RichLifeVision> */
class RichLifeVisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'text' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }
}
