<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Estimated against actual workdays of a project: the estimate filled in on the project, and the days
 * (dates, up to today) on which the project is in the planning. Shown as "estimated/actual", red once
 * the actual days exceed the estimate. Only for users given "project_workdays.view" themselves (never
 * through a role).
 */
class ProjectWorkdays
{
    public const PERMISSION = 'project_workdays.view';

    public static function canView(?User $user): bool
    {
        return $user instanceof User && modules()->projects() && modules()->planning() && $user->hasPermission(self::PERMISSION);
    }

    /**
     * Adds "worked_days": the planned dates up to today, for lists.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public static function withWorkedDays(Builder $query): Builder
    {
        return $query->withCount(['planningItems as worked_days' => fn (Builder $query) => $query
            ->whereDate('date', '<=', today())
            ->select(DB::raw('count(distinct date)'))]);
    }

    public static function workedDays(Project $project): int
    {
        return (int) ($project->worked_days ?? $project->planningItems()->whereDate('date', '<=', today())->distinct()->count('date'));
    }

    /**
     * Over the estimate: only when there is one.
     */
    public static function isOver(Project $project): bool
    {
        return $project->estimated_workdays !== null && self::workedDays($project) > (float) $project->estimated_workdays;
    }

    /**
     * "10/12": estimated over actual ("—/12" without an estimate).
     */
    public static function label(Project $project): string
    {
        $estimate = $project->estimated_workdays === null ? '—' : rtrim(rtrim(number_format((float) $project->estimated_workdays, 1, ',', ''), '0'), ',');

        return $estimate.'/'.self::workedDays($project);
    }

    public static function color(Project $project): string
    {
        return match (true) {
            $project->estimated_workdays === null => 'gray',
            self::isOver($project) => 'danger',
            default => 'success',
        };
    }

    /**
     * @param  Builder<Project>  $query
     */
    public static function over(Builder $query): void
    {
        $query->whereNotNull('estimated_workdays')
            ->whereRaw('(select count(distinct planning_items.date) from planning_items where planning_items.project_id = projects.id and planning_items.date <= ?) > projects.estimated_workdays', [today()->toDateString()]);
    }
}
