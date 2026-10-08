<?php

namespace Database\Factories;

use App\Enums\BuildingType;
use App\Models\Country;
use App\Models\WorkSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkSite>
 */
class WorkSiteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cow_code' => fake()->unique()->numerify('##').fake()->lexify('???'),
            'building_type' => BuildingType::Atmk,
            'country_id' => fn () => Country::belgiumId(),
            'street' => 'Rue de la Gare',
            'house_number' => (string) fake()->numberBetween(1, 200),
            'postal_code' => '4000',
            'city' => 'Liège',
            'state' => 'Liège',
        ];
    }
}
