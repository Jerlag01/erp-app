<?php

namespace Database\Factories;

use App\Enums\WasteRegion;
use App\Models\WasteCategory;
use App\Models\WasteEntry;
use App\Models\WasteProcessor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WasteEntry>
 */
class WasteEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => today()->toDateString(),
            'waste_category_id' => fn (): int => WasteCategory::query()->where('waste_code', '17 04 01')->value('id') ?? WasteCategory::factory()->create()->id,
            'weight_kg' => fake()->randomFloat(2, 1, 500),
            'waste_processor_id' => WasteProcessor::factory(),
        ];
    }

    /**
     * Lead batteries handed in, in the given region.
     */
    public function batteries(WasteRegion $region = WasteRegion::Flanders): static
    {
        return $this->state(fn (): array => [
            'waste_category_id' => WasteCategory::query()->where('waste_code', '16 06 01*')->value('id'),
            'region' => $region,
            'destruction_certificate' => fake()->numerify('###'),
            'cow_code' => '02GAM',
        ]);
    }
}
