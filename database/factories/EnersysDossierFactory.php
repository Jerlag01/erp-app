<?php

namespace Database\Factories;

use App\Enums\BatteryDeliveryMode;
use App\Enums\BatteryMounting;
use App\Models\EnersysDossier;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnersysDossier>
 */
class EnersysDossierFactory extends Factory
{
    protected $model = EnersysDossier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory()->inCategory('605'),
            'contact_person' => 'Pascal Callebaut',
            'po_number' => 'NVT',
            'truck_plate' => 'Camionette',
            'waste_type' => 'UN2800, AFVAL, BATTERIJEN (ACCUMULATOREN), NAT, VAN HET GESLOTEN TYPE, 8, (E)',
            'old_capacity_ah' => 0,
            'old_count' => 2,
            'removal' => true,
            'new_capacity_label' => '155/100',
            'new_capacity_ah' => 155,
            'new_count' => 2,
            'delivery_mode' => BatteryDeliveryMode::Place,
            'supplier_location' => 'Power Installation NV',
            'brand' => 'Enersys',
            'battery_type' => '12V62FS',
            'mounting' => BatteryMounting::Cabinet,
            'cells_per_battery' => 4,
        ];
    }
}
