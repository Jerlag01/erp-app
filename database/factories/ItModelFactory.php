<?php

namespace Database\Factories;

use App\Models\ItCategory;
use App\Models\ItModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItModel>
 */
class ItModelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Latitude 5440', 'ThinkPad T14', 'EliteBook 840', 'P2422H']).' '.fake()->unique()->randomNumber(4),
            'it_category_id' => ItCategory::factory(),
            'model_number' => fake()->bothify('??-####'),
            'eol_months' => 48,
        ];
    }
}
