<?php

namespace Database\Factories;

use App\Models\FuelPass;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelPass>
 */
class FuelPassFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'card_number' => fake()->unique()->numerify('7077###############'),
            'pin_code' => fake()->numerify('####'),
            'expires_on' => now()->addYears(2)->endOfMonth()->toDateString(),
        ];
    }
}
