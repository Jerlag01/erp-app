<?php

namespace Database\Factories;

use App\Models\BatterySupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatterySupplier>
 */
class BatterySupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'BAE Batterien GmbH',
            'email' => fake()->unique()->safeEmail(),
            'contact_name' => fake()->name(),
            'locale' => 'en',
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
