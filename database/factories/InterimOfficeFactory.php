<?php

namespace Database\Factories;

use App\Models\InterimOffice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterimOffice>
 */
class InterimOfficeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'is_active' => true,
        ];
    }
}
