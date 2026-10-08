<?php

namespace Database\Factories;

use App\Models\StockItem;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockItem>
 */
class StockItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Automaat 2P 16A', 'XVB 3G2,5', 'Differentieel 40A 30mA', 'Zekering 10A']).' '.fake()->unique()->numberBetween(1, 99999),
            'unit_id' => Unit::factory(),
            'manufacturer_reference' => fake()->bothify('A9F#####'),
            'distributor_reference' => fake()->numerify('########'),
            'is_active' => true,
        ];
    }

    /**
     * Measured in meters, so decimals are allowed.
     */
    public function inMeters(): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_id' => Unit::factory()->state(['allows_decimals' => true]),
        ]);
    }
}
