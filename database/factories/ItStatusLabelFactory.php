<?php

namespace Database\Factories;

use App\Enums\ItStatusType;
use App\Models\ItStatusLabel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItStatusLabel>
 */
class ItStatusLabelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Status '.fake()->unique()->randomNumber(6),
            'type' => ItStatusType::Deployable,
        ];
    }

    public function undeployable(): static
    {
        return $this->state(fn (array $attributes) => ['type' => ItStatusType::Undeployable]);
    }
}
