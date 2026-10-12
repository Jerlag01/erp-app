<?php

namespace Database\Factories;

use App\Models\Router;
use App\Models\WorkSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Router>
 */
class RouterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_site_id' => WorkSite::factory(),
            'type' => 'Teltonika RUT241',
            'serial_number' => fake()->unique()->numerify('##########'),
            'sim_ean' => fake()->numerify('5413729######'),
            'sim_ssn' => fake()->numerify('2100450######'),
            'username' => 'admin',
            'password' => fake()->password(12, 12),
        ];
    }
}
