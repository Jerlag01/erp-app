<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectCategory;

/**
 * A 604 project (batteries) in Flanders or Brussels that is closed gets a 609 project (battery room) for
 * the same COW site, once. The PO number is not taken over: Proximus gives a new one later, which is
 * added on the project. The 609 waits for that PO.
 */
class CreateFollowUpProject
{
    public const SOURCE_CATEGORY = '604';

    public const FOLLOW_UP_CATEGORY = '609';

    /**
     * Areas (work site areas) that get a follow-up project.
     */
    public const AREAS = ['1-VLA', '2-VLA', '3-BRU'];

    public function applies(Project $project): bool
    {
        $area = strtoupper(str_replace(' ', '', (string) $project->workSite?->area?->name));

        return $project->category?->code === self::SOURCE_CATEGORY
            && $project->status === ProjectStatus::Closed
            && $project->work_site_id !== null
            && in_array($area, self::AREAS, true);
    }

    public function handle(Project $project): ?Project
    {
        if (! $this->applies($project)) {
            return null;
        }

        $category = ProjectCategory::query()->where('code', self::FOLLOW_UP_CATEGORY)->first();

        if (! $category || Project::query()->where('source_project_id', $project->id)->where('project_category_id', $category->id)->exists()) {
            return null;
        }

        $followUp = Project::query()->create([
            'project_category_id' => $category->id,
            'work_site_id' => $project->work_site_id,
            'status' => ProjectStatus::WaitingForPo,
            'notes' => __('battery_rooms.follow_up_note', ['reference' => $project->reference]),
            'created_by' => $project->created_by,
            'source_project_id' => $project->id,
        ]);

        $followUp->teams()->sync($project->teams()->pluck('teams.id'));
        $followUp->leaders()->sync($project->leaders()->pluck('users.id'));

        return $followUp;
    }
}
