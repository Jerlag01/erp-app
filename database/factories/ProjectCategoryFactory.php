<?php

namespace Database\Factories;

use App\Enums\ProjectNumbering;
use App\Models\ProjectCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectCategory>
 */
class ProjectCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => (string) fake()->unique()->numberBetween(700, 999),
            'name' => fake()->words(2, true),
            'numbering' => ProjectNumbering::Sequence,
            'has_short_description' => false,
            'is_active' => true,
        ];
    }
}
