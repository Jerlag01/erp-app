<?php

namespace Database\Factories;

use App\Models\OfferCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferCategory>
 */
class OfferCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->randomLetter(),
            'name' => fake()->words(2, true),
            'is_general_costs' => false,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }

    public function generalCosts(): static
    {
        return $this->state(['is_general_costs' => true]);
    }
}
