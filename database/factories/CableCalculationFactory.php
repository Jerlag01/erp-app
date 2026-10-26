<?php

namespace Database\Factories;

use App\Models\CableCalculation;
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
            'system' => 'ac3',
            'voltage' => 400,
            'load_type' => 'current',
            'load_current' => 32,
            'power_factor' => 0.85,
            'length' => 50,
            'material' => 'cu',
            'insulation' => 'xlpe',
            'installation_method' => 'C',
            'ambient_temperature' => 30,
            'grouped_circuits' => 1,
            'parallel_conductors' => 1,
            'max_voltage_drop' => 3,
        ];
    }
}
