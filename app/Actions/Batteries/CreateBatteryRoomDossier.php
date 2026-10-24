<?php

namespace App\Actions\Batteries;

use App\Models\BatteryDossier;
use App\Models\BatteryRoomDossier;
use App\Models\Project;
use App\Models\User;

/**
 * Starts the battery room dossier of a 609 project (one per project). When the project follows up a
 * 604, the room of its battery dossier is taken over; the checklist starts empty (all NOK).
 */
class CreateBatteryRoomDossier
{
    public function appliesTo(Project $project): bool
    {
        return modules()->batteryRooms() && $project->category?->code === BatteryRoomDossier::CATEGORY;
    }

    public function handle(Project $project, ?User $by = null): ?BatteryRoomDossier
    {
        if (! $this->appliesTo($project)) {
            return null;
        }

        if ($existing = BatteryRoomDossier::query()->where('project_id', $project->id)->first()) {
            return $existing;
        }

        $source = $project->source_project_id ? BatteryDossier::query()->where('project_id', $project->source_project_id)->latest('id')->first() : null;

        return BatteryRoomDossier::query()->create([
            'project_id' => $project->id,
            'room_number' => $source?->new_room ?: $source?->old_room,
            'note_date' => today(),
            'signed_by' => $by?->name,
            'created_by' => $by?->id,
        ]);
    }
}
