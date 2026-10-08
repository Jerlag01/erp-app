<?php

namespace Database\Factories;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $controlDate = fake()->dateTimeBetween('-11 months', '-1 month');

        return [
            'plate_number' => fake()->unique()->regexify('[12]-[A-Z]{3}-[0-9]{3}'),
            'brand' => fake()->randomElement(['Volkswagen', 'Renault', 'Ford', 'Mercedes-Benz']),
            'type' => fake()->randomElement(['Transporter', 'Trafic', 'Transit Custom', 'Sprinter']),
            'vin' => fake()->unique()->regexify('[A-HJ-NPR-Z0-9]{17}'),
            'year' => fake()->numberBetween(2015, (int) date('Y')),
            'fuel' => FuelType::Diesel,
            'mileage' => fake()->numberBetween(5000, 250000),
            'control_date' => $controlDate,
            'next_control_date' => (clone $controlDate)->modify('+1 year'),
            'status' => VehicleStatus::Active,
        ];
    }

    public function controlDueIn(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'control_date' => today()->addDays($days)->subYear(),
            'next_control_date' => today()->addDays($days),
        ]);
    }
}
