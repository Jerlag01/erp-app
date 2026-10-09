<?php

namespace Database\Factories;

use App\Enums\BatteryDeliveryMode;
use App\Enums\BatteryMounting;
use App\Enums\BatteryTechnology;
use App\Models\BatteryDossier;
use App\Models\BatteryModel;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The example of the Excel: 2 × 800 Ah OPzV replaced by 2 × 7 OPzV 490 (500 Ah) in a cabinet.
 *
 * @extends Factory<BatteryDossier>
 */
class BatteryDossierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => fn (): int => Project::factory()->inCategory('604')->create()->id,
            'po_number' => '4503359900',
            'old_capacity_ah' => 800,
            'old_technology' => BatteryTechnology::OPzV,
            'old_count' => 2,
            'removal' => true,
            'old_room' => 'A00A007',
            'new_capacity_ah' => 500,
            'new_technology' => BatteryTechnology::OPzV,
            'new_count' => 2,
            'delivery_mode' => BatteryDeliveryMode::Place,
            'supplier_location' => 'Power Installation NV',
            'new_room' => 'A00A007',
            'brand' => 'BAE',
            'battery_model_id' => fn (): ?int => BatteryModel::query()->where('name', '7 OPzV 490')->value('id'),
            'mounting' => BatteryMounting::Cabinet,
            'cells_per_battery' => 24,
            'battery_total_price' => 18750.63,
            'hourly_rate' => 64.68,
        ];
    }
}
