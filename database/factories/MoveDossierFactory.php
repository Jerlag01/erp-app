<?php

namespace Database\Factories;

use App\Enums\BatteryMounting;
use App\Enums\BatteryTechnology;
use App\Models\BatteryModel;
use App\Models\MoveDossier;
use App\Models\Project;
use App\Models\WorkSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MoveDossier>
 */
class MoveDossierFactory extends Factory
{
    protected $model = MoveDossier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory()->inCategory('605'),
            'move_from_work_site_id' => WorkSite::factory(),
            'contact_person' => 'Pascal Callebaut',
            'removal' => false,
            'old_count' => 0,
            'new_capacity_ah' => 490,
            'new_technology' => BatteryTechnology::OPzV,
            'new_count' => 2,
            'brand' => 'BAE',
            'battery_model_id' => fn (): ?int => BatteryModel::query()->where('name', '7 OPzV 490')->value('id'),
            'mounting' => BatteryMounting::UShape,
            'cells_per_battery' => 24,
            'volts_per_cell' => 2,
        ];
    }
}
