<?php

namespace Database\Factories;

use App\Models\BaeOrder;
use App\Models\BatteryModel;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A new 604 project gets its BAE order by itself; this factory is for orders on other projects.
 *
 * @extends Factory<BaeOrder>
 */
class BaeOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => fn (): int => Project::factory()->create()->id,
            'order_date' => '2026-07-14',
            'battery_model_id' => fn (): ?int => BatteryModel::query()->where('name', '7 OPzV 490')->value('id'),
            'mounting' => 'u_vorm',
            'capacity_ah' => 500,
            'technology' => 'OpzV',
            'battery_count' => 2,
            'cells_per_battery' => 24,
            'volts_per_cell' => 2,
            'cell_price' => 214.52,
            'lines' => BaeOrder::linesFor(null),
        ];
    }
}
