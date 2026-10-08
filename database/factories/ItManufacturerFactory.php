<?php

namespace Database\Factories;

use App\Models\ItManufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItManufacturer>
 */
class ItManufacturerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'website' => fake()->url(),
        ];
    }
}
