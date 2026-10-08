<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Stuk '.fake()->unique()->randomNumber(5),
            'abbreviation' => fake()->unique()->lexify('??????'),
            'allows_decimals' => false,
        ];
    }
}
