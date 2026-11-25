<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\BatteryDossier;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

/**
 * Battery replacements (604) and interventions follow their confirmed planning (drafts do not count):
 * in the planning → "planned" (also a 604 whose batteries are "ordered"); its first planned day has come → "in progress"; for an
 * intervention, its last planned day has passed → "executed" (a 604 stays "in progress": it is only executed once its
 * battery dossier is finished, which makes it "invoicing to do"); taken out of the planning again → back to where it was before ("to be executed"
 * for an intervention, "to be planned" for a 604). Only these statuses move here: from "executed" on the
 * project is left alone (closing the intervention form also makes it "executed", the review or the
 * finished battery dossier "invoicing to do"). Runs when the planning changes and every night.
 */
class SyncPlanningStatus
{
    /** The statuses this sync may set or change. */
    public const MANAGED = [ProjectStatus::Ordered, ProjectStatus::ToBePlanned, ProjectStatus::ToBeExecuted, ProjectStatus::Planned, ProjectStatus::InProgress];

    public static function appliesTo(Project $project): bool
    {
        return $project->isIntervention() || ($project->category !== null && $project->category->code === settings()->get(BatteryDossier::CATEGORY_SETTING));
    }

    public function forProject(?int $projectId): void
    {
        $project = $projectId ? Project::query()->with('category')->find($projectId) : null;

        if ($project !== null) {
            $this->sync($project);
        }
    }

    /**
     * Every 604 and intervention whose status can still follow the planning; returns how many changed.
     */
    public function all(): int
    {
        $changed = 0;

        Project::query()
            ->with('category')
            ->whereIn('status', array_map(fn (ProjectStatus $status): string => $status->value, self::MANAGED))
            ->whereNull('archived_at')
            ->whereHas('category', fn (Builder $query) => $query->where('numbering', 'intervention')->orWhere('code', settings()->get(BatteryDossier::CATEGORY_SETTING)))
            ->lazyById()
            ->each(function (Project $project) use (&$changed): void {
                $changed += $this->sync($project) ? 1 : 0;
            });

        return $changed;
    }

    /**
     * Returns whether the status changed.
     */
    public function sync(Project $project): bool
    {
        if (! self::appliesTo($project) || ! in_array($project->status, self::MANAGED, true)) {
            return false;
        }

        $first = $project->planningItems()->min('date');
        $last = $project->planningItems()->max('date');
        $today = today()->toDateString();

        $status = match (true) {
            $first === null => $project->status === ProjectStatus::Planned || $project->status === ProjectStatus::InProgress
                ? ($project->isIntervention() ? ProjectStatus::ToBeExecuted : ProjectStatus::ToBePlanned)
                : $project->status,
            // Only an intervention is executed by the date; a 604 by finishing its battery dossier.
            substr((string) $last, 0, 10) < $today && $project->isIntervention() => ProjectStatus::Executed,
            substr((string) $first, 0, 10) <= $today => ProjectStatus::InProgress,
            default => ProjectStatus::Planned,
        };

        if ($status === $project->status) {
            return false;
        }

        $project->update(['status' => $status]);

        return true;
    }
}
