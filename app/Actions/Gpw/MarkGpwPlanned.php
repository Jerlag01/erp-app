<?php

namespace App\Actions\Gpw;

use App\Enums\GpwStatus;
use App\Enums\PlanningKind;
use App\Enums\ProjectStatus;
use App\Models\GpwRequest;
use App\Models\PlanningItem;

/**
 * An approved GPW request whose project is already in the planning within the approved period becomes
 * "planned" (like PlanGpw does), and so does the project when it was still waiting to be planned. Checked
 * when the request is approved and whenever the project is put in the planning.
 */
class MarkGpwPlanned
{
    public function handle(GpwRequest $request): bool
    {
        if ($request->status !== GpwStatus::Approved || $request->project_id === null) {
            return false;
        }

        $item = PlanningItem::query()
            ->where('project_id', $request->project_id)
            ->where('kind', PlanningKind::Project)
            ->whereBetween('date', [$request->starts_on->toDateString(), $request->ends_on->toDateString()])
            ->oldest('id')
            ->first();

        if ($item === null) {
            return false;
        }

        $request->update(['status' => GpwStatus::Planned, 'planned_at' => now(), 'planned_by' => $item->created_by]);

        $project = $request->project;

        if ($project !== null && in_array($project->status, [ProjectStatus::ToBePlanned, ProjectStatus::ToBeExecuted], true)) {
            $project->update(['status' => ProjectStatus::Planned]);
        }

        return true;
    }

    /**
     * All approved requests of a project (after it was put in the planning).
     */
    public function forProject(int $projectId): void
    {
        GpwRequest::query()
            ->where('project_id', $projectId)
            ->where('status', GpwStatus::Approved)
            ->get()
            ->each(fn (GpwRequest $request): bool => $this->handle($request));
    }
}
