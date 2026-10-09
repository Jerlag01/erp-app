<?php

namespace Database\Factories;

use App\Enums\BatteryTechnology;
use App\Models\BatteryModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatteryModel>
 */
class BatteryModelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->numerify('# OPzV ###'),
            'technology' => BatteryTechnology::OPzV,
            'weight_kg' => 42,
            'length_mm' => 168,
            'width_mm' => 208,
            'height_mm' => 535,
        ];
    }
}
