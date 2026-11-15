<?php

namespace Database\Factories;

use App\Models\CableCalculation;
use App\Models\CableCalculationCircuit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CableCalculation>
 */
class CableCalculationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Voeding '.fake()->bothify('TD-##'),
        ];
    }

    /**
     * With one AC circuit (32 A three-phase over 50 m) or the given attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function withCircuit(array $attributes = []): static
    {
        return $this->has(CableCalculationCircuit::factory()->state($attributes), 'circuits');
    }
}
