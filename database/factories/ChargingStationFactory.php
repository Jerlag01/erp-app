<?php

namespace Database\Factories;

use App\Enums\ChargingConnectivity;
use App\Enums\ChargingStationType;
use App\Enums\ChargingSystem;
use App\Models\ChargingStation;
use App\Models\WorkSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChargingStation>
 */
class ChargingStationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_site_id' => WorkSite::factory(),
            'ace_number' => 'ACE'.fake()->unique()->numerify('#######'),
            'password' => fake()->password(12, 12),
            'recovery_code' => fake()->password(16, 16),
            'sim_ean' => fake()->numerify('5413729######'),
            'sim_ssn' => fake()->numerify('2100450######'),
            'connectivity' => ChargingConnectivity::Wired,
            'type' => ChargingStationType::Standalone,
            'online' => true,
            'backoffice' => 'Nexxtmove',
            'installation_year' => 2024,
            'brand' => 'ALFEN',
            'quantity' => 1,
            'system' => ChargingSystem::Duo,
            'in_service' => true,
            'commissioned_by' => 'PI',
        ];
    }
}
