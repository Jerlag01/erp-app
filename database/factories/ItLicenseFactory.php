<?php

namespace Database\Factories;

use App\Models\ItLicense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItLicense>
 */
class ItLicenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Office '.fake()->unique()->randomNumber(5),
            'seats' => 3,
            'product_key' => fake()->bothify('?????-?????-?????-?????'),
            'reassignable' => true,
        ];
    }
}
