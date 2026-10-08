<?php

namespace Database\Factories;

use App\Models\WasteProcessor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WasteProcessor>
 */
class WasteProcessorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Recycling',
            'city' => 'Antwerpen',
            'is_active' => true,
        ];
    }
}
