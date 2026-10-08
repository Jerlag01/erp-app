<?php

namespace Database\Factories;

use App\Models\ItSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItSupplier>
 */
class ItSupplierFactory extends Factory
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
