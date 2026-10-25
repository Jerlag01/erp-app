<?php

namespace App\Actions\Batteries;

use App\Enums\ProjectStatus;
use App\Models\BatteryRoomDossier;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Makes a 609 project by hand (for a battery room that does not follow a closed 604, or before it
 * closes): on a COW site, waiting for its PO, with its battery room dossier. When it follows a 604,
 * that project is linked and its team and leaders are taken over; otherwise the person making it leads
 * it with their own teams.
 */
class CreateBatteryRoomProject
{
    /**
     * @param  array{work_site_id: int, room_number?: ?string, source_project_id?: ?int}  $data
     */
    public function handle(array $data, User $by): BatteryRoomDossier
    {
        return DB::transaction(function () use ($data, $by): BatteryRoomDossier {
            $source = filled($data['source_project_id'] ?? null) ? Project::query()->find($data['source_project_id']) : null;

            $project = Project::query()->create([
                'project_category_id' => ProjectCategory::query()->where('code', BatteryRoomDossier::CATEGORY)->value('id'),
                'work_site_id' => $data['work_site_id'],
                'status' => ProjectStatus::WaitingForPo,
                'notes' => $source ? __('battery_rooms.follow_up_note', ['reference' => $source->reference]) : null,
                'created_by' => $by->id,
                'source_project_id' => $source?->id,
            ]);

            $project->leaders()->sync($source ? $source->leaders()->pluck('users.id') : [$by->id]);
            $project->teams()->sync($source ? $source->teams()->pluck('teams.id') : $by->teamIds());

            // The dossier came with the project (Project::created); without a module it is made here.
            $dossier = $project->batteryRoom()->first() ?? BatteryRoomDossier::query()->create(['project_id' => $project->id, 'note_date' => today(), 'created_by' => $by->id]);

            if (filled($data['room_number'] ?? null)) {
                $dossier->update(['room_number' => $data['room_number']]);
            }

            return $dossier;
        });
    }
}
