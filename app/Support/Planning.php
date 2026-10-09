<?php

namespace App\Support;

use App\Enums\PlanningKind;
use App\Enums\ProjectStatus;
use App\Enums\VacationStatus;
use App\Models\Absence;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\User;
use App\Models\VacationRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Who may see and fill in whose planning, and which days are blocked.
 *
 * Seeing: everyone sees their own planning, that of their team members (while the teams module is
 * on) and of colleagues who shared theirs with them. Administrators and users with
 * "planning.view_all" or "planning.plan" see everybody; project leaders also see the items on
 * their projects.
 * Planning: administrators and "planning.plan" plan open projects and warehouse jobs; project
 * leaders plan their own open projects. Anything else needs "planning.other".
 * Blocked: days with approved vacation or an absence (medical, overtime as leave, family leave).
 */
class Planning
{
    public function enabled(): bool
    {
        return modules()->planning();
    }

    public function seesEveryone(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('planning.view_all') || $user->hasPermission('planning.plan');
    }

    /**
     * Ids of the people whose planning the user sees, or null for everybody.
     *
     * @return list<int>|null
     */
    public function visibleUserIds(User $user): ?array
    {
        if ($this->seesEveryone($user)) {
            return null;
        }

        $ids = modules()->teams() ? $user->teamMemberIds() : [$user->id];
        $sharedWithMe = DB::table('planning_shares')->where('viewer_id', $user->id)->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();

        return array_values(array_unique([$user->id, ...$ids, ...$sharedWithMe]));
    }

    public function canSeePlanningOf(User $viewer, User $employee): bool
    {
        $ids = $this->visibleUserIds($viewer);

        return $ids === null || in_array($employee->id, $ids, true);
    }

    public function isPlanner(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('planning.plan');
    }

    public function canPlanOther(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('planning.other');
    }

    /**
     * Whether the user may add items at all.
     */
    public function canPlan(User $user): bool
    {
        if (! $this->enabled() || ! $user->is_active) {
            return false;
        }

        return $this->isPlanner($user) || $this->canPlanOther($user) || $this->plannableProjects($user)->exists();
    }

    /**
     * @return list<PlanningKind>
     */
    public function kindsFor(User $user): array
    {
        return array_values(array_filter([
            modules()->projects() && ($this->isPlanner($user) || $this->plannableProjects($user)->exists()) ? PlanningKind::Project : null,
            $this->isPlanner($user) ? PlanningKind::Warehouse : null,
            $this->canPlanOther($user) ? PlanningKind::Other : null,
        ]));
    }

    public function canPlanKind(User $user, PlanningKind $kind): bool
    {
        return in_array($kind, $this->kindsFor($user), true);
    }

    /**
     * Open projects the user may put in the planning: all of them for planners, the ones they lead otherwise.
     *
     * @return Builder<Project>
     */
    public function plannableProjects(User $user): Builder
    {
        return Project::query()
            ->whereIn('status', ProjectStatus::open())
            ->when(! $this->isPlanner($user), fn (Builder $query) => $query->whereHas('leaders', fn (Builder $query) => $query->whereKey($user->id)))
            ->when(! modules()->projects(), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    /**
     * Changing or removing an item: planners always, project leaders for items on their projects,
     * "planning.other" for the other items.
     */
    public function canManage(User $user, PlanningItem $item): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return match (true) {
            $this->isPlanner($user) => true,
            $item->kind === PlanningKind::Project => $item->project !== null && $item->project->isLedBy($user),
            $item->kind === PlanningKind::Other => $this->canPlanOther($user),
            default => false,
        };
    }

    /**
     * The employee ticks off tasks of their own items; planners may too.
     */
    public function canTickTasks(User $user, PlanningItem $item): bool
    {
        return $this->enabled() && ($item->user_id === $user->id || $this->canManage($user, $item));
    }

    /**
     * Blocked days per person: [user id => [Y-m-d => label]], with the label the viewer may see
     * (colleagues only see "absent" for medical and family leave).
     *
     * @param  list<int>|null  $userIds  null for everybody
     * @return array<int, array<string, string>>
     */
    public function blockedDays(?array $userIds, CarbonInterface $from, CarbonInterface $until, User $viewer): array
    {
        if (! modules()->vacations()) {
            return [];
        }

        $blocked = [];
        $mark = function (int $userId, CarbonInterface $start, CarbonInterface $end, string $label) use (&$blocked, $from, $until): void {
            $start = CarbonImmutable::instance($start)->max($from);
            $end = CarbonImmutable::instance($end)->min($until);

            foreach (CarbonPeriod::create($start, $end) as $day) {
                $blocked[$userId][$day->toDateString()] ??= $label;
            }
        };

        Absence::query()
            ->when($userIds !== null, fn (Builder $query) => $query->whereIn('user_id', $userIds))
            ->overlapping($from->toDateString(), $until->toDateString())
            ->get()
            ->each(fn (Absence $absence) => $mark($absence->user_id, $absence->start_date, $absence->end_date, $absence->labelFor($viewer)));

        VacationRequest::query()
            ->when($userIds !== null, fn (Builder $query) => $query->whereIn('user_id', $userIds))
            ->where('status', VacationStatus::Approved)
            ->whereDate('start_date', '<=', $until->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get()
            ->each(fn (VacationRequest $request) => $mark($request->user_id, $request->start_date, $request->end_date, __('erp.planning.blocked_vacation')));

        return $blocked;
    }

    /**
     * Why a day is blocked for the employee, or null when it is free.
     */
    public function blockedReason(User $employee, CarbonInterface $date, User $viewer): ?string
    {
        $day = CarbonImmutable::instance($date)->startOfDay();

        return $this->blockedDays([$employee->id], $day, $day, $viewer)[$employee->id][$day->toDateString()] ?? null;
    }

    /**
     * Items of the employee that overlap the timeslot on that day.
     */
    public function clashes(int $userId, string $date, string $start, string $end, ?int $ignoreId = null): bool
    {
        return PlanningItem::query()
            ->clashingWith($userId, $date, $start, $end)
            ->when($ignoreId, fn (Builder $query, int $id) => $query->whereKeyNot($id))
            ->exists();
    }

    /**
     * The preset timeslots from the settings, e.g. "Morning 07:30–12:00".
     *
     * @return list<array{label: string, start: string, end: string}>
     */
    public function slots(): array
    {
        return collect((array) settings()->get('planning.slots', []))
            ->filter(fn (mixed $slot): bool => is_array($slot) && filled($slot['start'] ?? null) && filled($slot['end'] ?? null))
            ->map(fn (array $slot): array => ['label' => (string) ($slot['label'] ?? ''), 'start' => substr((string) $slot['start'], 0, 5), 'end' => substr((string) $slot['end'], 0, 5)])
            ->values()
            ->all();
    }

    /**
     * Extra addresses that get a copy of every planning mail.
     *
     * @return list<string>
     */
    public function notifyAddresses(): array
    {
        return collect((array) settings()->get('planning.notify_emails', []))
            ->map(fn (mixed $email): string => trim((string) $email))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
