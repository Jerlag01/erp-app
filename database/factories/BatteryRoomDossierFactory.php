<?php

namespace Database\Factories;

use App\Models\BatteryRoomDossier;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A new 609 project gets its dossier by itself; this factory is for dossiers on other projects.
 *
 * @extends Factory<BatteryRoomDossier>
 */
class BatteryRoomDossierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => fn (): int => Project::factory()->create()->id,
            'room_number' => 'A-1A006',
            'note_date' => '2026-11-02',
        ];
    }
}
