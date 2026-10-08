<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_tag' => 'EQ-'.fake()->unique()->numerify('####'),
            'name' => fake()->randomElement(['Installatietester', 'Boormachine', 'Isolatieweerstandsmeter', 'Verlengkabel 25 m', 'Geïsoleerde ladder']),
            'asset_category_id' => AssetCategory::factory(),
            'brand' => fake()->randomElement(['Fluke', 'Makita', 'Hilti', 'Benning']),
            'model' => fake()->bothify('??-###'),
            'serial_number' => fake()->bothify('SN########'),
            'status' => AssetStatus::InService,
            'inspection_date' => today()->subMonths(6),
            'next_inspection_date' => today()->addMonths(6),
        ];
    }
}
