<?php

namespace Database\Factories;

use App\Enums\ItMaintenanceType;
use App\Models\ItAsset;
use App\Models\ItMaintenance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItMaintenance>
 */
class ItMaintenanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'it_asset_id' => ItAsset::factory(),
            'type' => ItMaintenanceType::Repair,
            'title' => 'Scherm vervangen',
            'start_date' => today(),
        ];
    }
}
