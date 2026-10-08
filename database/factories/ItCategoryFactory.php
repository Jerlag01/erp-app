<?php

namespace Database\Factories;

use App\Enums\ItCategoryType;
use App\Models\ItCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItCategory>
 */
class ItCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ItCategoryType::Asset,
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
