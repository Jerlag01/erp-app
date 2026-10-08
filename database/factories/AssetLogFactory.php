<?php

namespace Database\Factories;

use App\Enums\AssetLogType;
use App\Enums\DamageSeverity;
use App\Models\Asset;
use App\Models\AssetLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetLog>
 */
class AssetLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'type' => AssetLogType::Note,
            'date' => today(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
        ];
    }

    public function damage(DamageSeverity $severity = DamageSeverity::Minor): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AssetLogType::Damage,
            'severity' => $severity,
        ]);
    }

    public function repairOf(AssetLog $damage): static
    {
        return $this->state(fn (array $attributes) => [
            'asset_id' => $damage->asset_id,
            'type' => AssetLogType::Repair,
            'damage_id' => $damage->id,
            'performed_by' => fake()->company(),
            'cost' => fake()->randomFloat(2, 20, 400),
        ]);
    }
}
