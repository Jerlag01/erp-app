<?php

namespace Database\Factories;

use App\Models\CableStrippingLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CableStrippingLine>
 */
class CableStrippingLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeThisYear()->format('Y-m-d'),
            'imputation' => strtoupper(fake()->bothify('##???')),
            'start_time' => '08:00',
            'end_time' => '12:00',
            'weight_before' => 500,
            'empty_bin_weight' => 40,
            'weight_after' => 400,
            'bin_number' => 'PI'.fake()->numberBetween(1, 60),
        ];
    }
}
