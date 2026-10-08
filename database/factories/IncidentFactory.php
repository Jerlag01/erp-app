<?php

namespace Database\Factories;

use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reporter_name' => fn (array $attributes) => User::find($attributes['user_id'])?->name ?? fake()->name(),
            'reported_at' => now(),
            'type' => IncidentType::NearMiss,
            'location_details' => 'Laadkade',
            'description' => 'Ladder gleed weg bij het opstellen.',
        ];
    }
}
