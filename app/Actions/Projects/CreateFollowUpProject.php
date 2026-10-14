<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectCategory;

/**
 * A 604 project (batteries) that is executed in Flanders or Brussels gets a 605 project for the same COW
 * site, once. The PO number is not taken over.
 */
class CreateFollowUpProject
{
    public const SOURCE_CATEGORY = '604';

    public const FOLLOW_UP_CATEGORY = '605';

    /**
     * Areas (work site areas) that get a follow-up project.
     */
    public const AREAS = ['1-VLA', '2-VLA', '3-BRU'];

    /**
     * Statuses that count as executed.
     */
    public const EXECUTED = [ProjectStatus::Executed, ProjectStatus::InvoicingToDo];

    public function applies(Project $project): bool
    {
        $area = strtoupper(str_replace(' ', '', (string) $project->workSite?->area?->name));

        return $project->category?->code === self::SOURCE_CATEGORY
            && in_array($project->status, self::EXECUTED, true)
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
            'status' => ProjectStatus::Offer,
            'notes' => __('erp.projects.follow_up_note', ['reference' => $project->reference]),
            'created_by' => $project->created_by,
            'source_project_id' => $project->id,
        ]);

        $followUp->teams()->sync($project->teams()->pluck('teams.id'));
        $followUp->leaders()->sync($project->leaders()->pluck('users.id'));

        return $followUp;
    }
}
