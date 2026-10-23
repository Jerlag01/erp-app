<?php

namespace Database\Factories;

use App\Enums\WasteRegion;
use App\Models\WasteEntry;
use App\Models\WasteEntryLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WasteEntryLine>
 */
class WasteEntryLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'waste_entry_id' => WasteEntry::factory()->batteries(),
            'region' => WasteRegion::Flanders,
            'destruction_certificate' => fake()->numerify('###'),
            'cow_code' => '02GAM',
            'weight_kg' => fake()->randomFloat(2, 1, 200),
        ];
    }
}
