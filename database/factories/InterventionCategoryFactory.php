<?php

namespace Database\Factories;

use App\Enums\InterventionCategoryKind;
use App\Models\InterventionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterventionCategory>
 */
class InterventionCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => InterventionCategoryKind::Category,
            'name' => strtoupper(fake()->unique()->lexify('CAT-????')),
            'is_active' => true,
        ];
    }
}
