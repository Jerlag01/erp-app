<?php

namespace Database\Factories;

use App\Models\WorkSiteContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkSiteContact>
 */
class WorkSiteContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company' => fake()->company(),
            'email' => fake()->safeEmail(),
            'phone' => '+32 4 123 45 67',
        ];
    }
}
