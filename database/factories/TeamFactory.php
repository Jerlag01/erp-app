<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Ploeg '.fake()->unique()->city(),
            'color' => fake()->hexColor(),
            'description' => null,
            'is_active' => true,
        ];
    }
}
