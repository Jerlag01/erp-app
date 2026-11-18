<?php

namespace App\Actions\Gpw;

use App\Enums\GpwStatus;
use App\Enums\PlanningKind;
use App\Enums\ProjectStatus;
use App\Models\GpwRequest;
use App\Models\PlanningItem;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps the status of an approved GPW request in step with its project, unless an administrator set it by
 * hand (status_locked_at):
 * - project archived: "executed";
 * - the period has begun and the project was in the planning on a day of it up to today: "in progress",
 *   and the project too (from "to be planned", "planned" or "to be executed");
 * - the project is in the planning within the period: "planned", and the project too (from "to be planned"
 *   or "to be executed");
 * - otherwise: back to "approved".
 * Waiting, refused or withdrawn requests are left alone. Checked on approval, on every planning change of
 * the project, when it is archived or reopened, and every night (gpw:sync-statuses) as the days go by.
 */
class SyncGpwStatus
{
    /**
     * The statuses this keeps up to date.
     *
     * @var list<GpwStatus>
     */
    public const AUTOMATIC = [GpwStatus::Approved, GpwStatus::Planned, GpwStatus::InProgress, GpwStatus::Executed];

    public function handle(GpwRequest $request): GpwStatus
    {
        if ($request->isLocked() || ! in_array($request->status, self::AUTOMATIC, true) || $request->project === null) {
            return $request->status;
        }

        $project = $request->project;
        $status = $this->statusFor($request, $project);

        if ($status !== $request->status) {
            $first = $status === GpwStatus::Planned && $request->planned_at === null ? $this->planning($request)->oldest('id')->first() : null;

            $request->update([
                'status' => $status,
                ...($first ? ['planned_at' => now(), 'planned_by' => $first->created_by] : []),
            ]);
        }

        $moves = match ($status) {
            GpwStatus::InProgress => [[ProjectStatus::ToBePlanned, ProjectStatus::Planned, ProjectStatus::ToBeExecuted], ProjectStatus::InProgress],
            GpwStatus::Planned => [[ProjectStatus::ToBePlanned, ProjectStatus::ToBeExecuted], ProjectStatus::Planned],
            default => null,
        };

        if ($moves !== null && $project->archived_at === null && in_array($project->status, $moves[0], true)) {
            $project->update(['status' => $moves[1]]);
        }

        return $status;
    }

    /**
     * Every request of a project that follows it.
     */
    public function forProject(Project|int $project): void
    {
        $this->requests()->where('project_id', $project instanceof Project ? $project->id : $project)->each(fn (GpwRequest $request): GpwStatus => $this->handle($request));
    }

    /**
     * Every request that follows its project (the nightly run).
     */
    public function all(): int
    {
        $changed = 0;

        $this->requests()->whereHas('project')->each(function (GpwRequest $request) use (&$changed): void {
            $before = $request->status;
            $changed += (int) ($this->handle($request) !== $before);
        });

        return $changed;
    }

    protected function statusFor(GpwRequest $request, Project $project): GpwStatus
    {
        if ($project->archived_at !== null) {
            return GpwStatus::Executed;
        }

        $today = today();

        if ($today->greaterThanOrEqualTo($request->starts_on) && $this->planning($request)->whereDate('date', '<=', $today->min($request->ends_on))->exists()) {
            return GpwStatus::InProgress;
        }

        return $this->planning($request)->exists() ? GpwStatus::Planned : GpwStatus::Approved;
    }

    /**
     * @return Builder<PlanningItem>
     */
    protected function planning(GpwRequest $request): Builder
    {
        return PlanningItem::query()
            ->where('project_id', $request->project_id)
            ->where('kind', PlanningKind::Project)
            ->whereBetween('date', [$request->starts_on->toDateString(), $request->ends_on->toDateString()]);
    }

    /**
     * @return Builder<GpwRequest>
     */
    protected function requests(): Builder
    {
        return GpwRequest::query()->with('project')->whereIn('status', self::AUTOMATIC)->whereNull('status_locked_at');
    }
}
