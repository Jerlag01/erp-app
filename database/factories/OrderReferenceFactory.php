<?php

namespace Database\Factories;

use App\Models\OrderReference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderReference>
 */
class OrderReferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999);

        return [
            'year' => 2026,
            'number' => $number,
            'month' => 10,
            'initials' => 'JL',
            'reference' => OrderReference::format($number, 10, 2026, 'JL'),
            'user_id' => User::factory(),
        ];
    }
}
