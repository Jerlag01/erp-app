<?php

namespace Database\Factories;

use App\Models\ItAsset;
use App\Models\ItModel;
use App\Models\ItStatusLabel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItAsset>
 */
class ItAssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_tag' => 'IT-'.fake()->unique()->numerify('#####'),
            'it_model_id' => ItModel::factory(),
            'it_status_label_id' => ItStatusLabel::factory(),
            'serial' => fake()->bothify('??########'),
            'purchase_date' => today()->subYear(),
            'warranty_months' => 36,
        ];
    }
}
