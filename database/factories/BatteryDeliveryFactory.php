<?php

namespace Database\Factories;

use App\Models\BatteryDelivery;
use App\Models\BatteryModel;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatteryDelivery>
 */
class BatteryDeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => fn (): int => Project::factory()->inCategory('604')->create()->id,
            'order_confirmation' => (string) fake()->numberBetween(4140000, 4159999),
            'battery_model_id' => fn (): ?int => BatteryModel::query()->where('name', '7 OPzV 490')->value('id'),
            'pieces' => 48,
            'planned_delivery_date' => '2026-10-20',
        ];
    }
}
