<?php

namespace Database\Factories;

use App\Models\StockCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockCategory>
 */
class StockCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'sort' => 0,
        ];
    }
}
