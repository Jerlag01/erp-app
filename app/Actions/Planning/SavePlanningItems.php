<?php

namespace App\Actions\Planning;

use App\Enums\PlanningKind;
use App\Models\GpwRequest;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\User;
use App\Support\Planning;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds, changes and removes planning items, enforcing who may plan what, open projects only,
 * no items on blocked days (vacation, absences) and no overlapping timeslots for one person.
 * Items start as drafts unless the actor may confirm ("planning.confirm") and plans directly.
 * Drafts are never notified; a confirmed item and every change to it is (see NotifyPlanningChange).
 * "No GPW = no planning": each planning of a project (not an intervention) without an approved GPW request
 * needs a reason ("gpw_bypass_reason"; not needed with "planning.bypass_gpw") and is logged on the project.
 */
class SavePlanningItems
{
    /**
     * Longest period that can be planned in one go.
     */
    public const MAX_DAYS = 62;

    /** Whether the last call could send all its e-mails. */
    public bool $mailFailed = false;

    /** Shortest reason accepted for planning without an approved GPW request. */
    public const MIN_BYPASS_REASON = 10;

    /**
     * The project this call plans without an approved GPW request (logged once the items are saved).
     *
     * @var array{project: Project, reason: string|null}|null
     */
    protected ?array $gpwBypass = null;

    public function __construct(protected Planning $planning, protected NotifyPlanningChange $notifier) {}

    /**
     * One item per employee per day from "date" to "until" (weekends only when asked). Days closed by
     * sickness or family leave are skipped and reported; items may overlap. With an approved GPW request
     * its requester may plan that project even without planning rights (see PlanGpw for the period).
     *
     * @param  array<string, mixed>  $data
     * @return array{created: list<PlanningItem>, skipped: list<string>}
     */
    public function create(array $data, User $actor, ?GpwRequest $gpw = null): array
    {
        $attributes = $this->attributes($data, $actor, gpw: $gpw);
        $employees = User::query()->active()->withTimesheet()->whereKey((array) ($data['user_ids'] ?? []))->orderBy('name')->get();

        if ($employees->isEmpty()) {
            throw ValidationException::withMessages(['user_ids' => __('erp.planning.errors.no_employee')]);
        }

        $days = $this->days($data);
        $created = [];
        $skipped = [];
        // Only who may confirm plans directly in the real planning; they choose (as_draft), the others always make drafts.
        $draft = ! $this->planning->canConfirm($actor) || (bool) ($data['as_draft'] ?? false);

        DB::transaction(function () use ($employees, $days, $attributes, $actor, $draft, &$created, &$skipped): void {
            foreach ($employees as $employee) {
                foreach ($days as $day) {
                    if ($reason = $this->planning->blockedReason($employee, $day, $actor)) {
                        $skipped[] = "{$employee->name} · {$day->format('d/m/Y')}: {$reason}";

                        continue;
                    }

                    $created[] = PlanningItem::query()->create([
                        ...$attributes,
                        'is_draft' => $draft,
                        'confirmed_at' => $draft ? null : now(),
                        'confirmed_by' => $draft ? null : $actor->id,
                        'user_id' => $employee->id,
                        'date' => $day->toDateString(),
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]);
                }
            }

            if ($created !== []) {
                $this->logGpwBypass($actor, $created);
            }
        });

        if ($created === []) {
            throw ValidationException::withMessages(['date' => implode(' — ', array_slice($skipped, 0, 5))]);
        }

        $this->mailFailed = false;

        if ($draft) {
            return ['created' => $created, 'skipped' => $skipped];
        }

        foreach (collect($created)->groupBy('user_id') as $items) {
            $items = $items->each->load('project', 'user')->values()->all();
            $this->mailFailed = ! $this->notifier->handle($items[0]->user, NotifyPlanningChange::CREATED, $items, $actor, (bool) ($data['notify_mail'] ?? false)) || $this->mailFailed;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PlanningItem $item, array $data, User $actor): PlanningItem
    {
        if (! $item->is_draft && ! $this->planning->canConfirm($actor)) {
            throw ValidationException::withMessages(['date' => __('erp.planning.errors.confirmed_locked')]);
        }

        $item->loadMissing('project', 'user');
        $before = clone $item;
        $attributes = $this->attributes($data, $actor, $item);
        $employee = User::query()->active()->withTimesheet()->find($data['user_id'] ?? $item->user_id) ?? throw ValidationException::withMessages(['user_id' => __('erp.planning.errors.no_employee')]);
        $date = CarbonImmutable::parse($data['date'] ?? $item->date);

        if ($reason = $this->planning->blockedReason($employee, $date, $actor)) {
            throw ValidationException::withMessages(['date' => __('erp.planning.errors.blocked', ['reason' => $reason])]);
        }

        $attributes['tasks'] = $attributes['tasks'] === null ? null : PlanningItem::normaliseTasks($attributes['tasks'], $item->taskList());

        $item->fill([...$attributes, 'user_id' => $employee->id, 'date' => $date->toDateString(), 'updated_by' => $actor->id]);

        if (! $item->isDirty()) {
            return $item;
        }

        DB::transaction(function () use ($item, $actor): void {
            $item->save();
            $this->logGpwBypass($actor, [$item]);
        });
        $item->load('project', 'user');
        $mail = (bool) ($data['notify_mail'] ?? false);
        $this->mailFailed = false;

        // A draft is changed silently.
        if ($item->is_draft) {
            return $item;
        }

        // Moved to someone else: removed from the old planning, added to the new one.
        if ($item->wasChanged('user_id')) {
            $sent = $this->notifier->handle($before->user, NotifyPlanningChange::DELETED, [$before], $actor, $mail);
            $this->mailFailed = ! ($this->notifier->handle($item->user, NotifyPlanningChange::CREATED, [$item], $actor, $mail) && $sent);
        } else {
            $this->mailFailed = ! $this->notifier->handle($item->user, NotifyPlanningChange::UPDATED, [$item], $actor, $mail, [$before]);
        }

        return $item;
    }

    public function delete(PlanningItem $item, User $actor, bool $mail): void
    {
        if (! $item->is_draft && ! $this->planning->canConfirm($actor)) {
            throw ValidationException::withMessages(['date' => __('erp.planning.errors.confirmed_locked')]);
        }

        $item->loadMissing('project', 'user');
        $item->delete();
        $this->mailFailed = false;

        if (! $item->is_draft) {
            $this->mailFailed = ! $this->notifier->handle($item->user, NotifyPlanningChange::DELETED, [$item], $actor, $mail);
        }
    }

    /**
     * Pushes drafts to the real planning: they are sent to the employees (app, push, optional mail,
     * Outlook) like newly planned items. Drafts on a day that has meanwhile closed (sickness, family
     * leave) stay drafts and are reported.
     *
     * @param  iterable<PlanningItem>  $drafts
     * @return array{confirmed: list<PlanningItem>, skipped: list<string>}
     */
    public function confirm(iterable $drafts, User $actor, bool $mail = false): array
    {
        if (! $this->planning->canConfirm($actor)) {
            throw ValidationException::withMessages(['items' => __('erp.planning.errors.cannot_confirm')]);
        }

        $confirmed = [];
        $skipped = [];

        DB::transaction(function () use ($drafts, $actor, &$confirmed, &$skipped): void {
            foreach ($drafts as $item) {
                $item->loadMissing('user', 'project');

                if (! $item->is_draft || ! $this->planning->canConfirmItem($actor, $item)) {
                    continue;
                }

                if ($reason = $this->planning->blockedReason($item->user, $item->date, $actor)) {
                    $skipped[] = "{$item->user->name} · {$item->date->format('d/m/Y')}: {$reason}";

                    continue;
                }

                $item->forceFill(['is_draft' => false, 'confirmed_at' => now(), 'confirmed_by' => $actor->id, 'updated_by' => $actor->id])->save();
                $confirmed[] = $item;
            }
        });

        $this->mailFailed = false;

        foreach (collect($confirmed)->groupBy('user_id') as $items) {
            $items = $items->sortBy(fn (PlanningItem $item): string => $item->date->toDateString().$item->start_time)->values()->all();
            $this->mailFailed = ! $this->notifier->handle($items[0]->user, NotifyPlanningChange::CREATED, $items, $actor, $mail) || $this->mailFailed;
        }

        return ['confirmed' => $confirmed, 'skipped' => $skipped];
    }

    /**
     * The validated item fields; throws when the actor may not plan this kind or project.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributes(array $data, User $actor, ?PlanningItem $item = null, ?GpwRequest $gpw = null): array
    {
        $this->gpwBypass = null;
        $kind = PlanningKind::tryFrom((string) ($data['kind'] ?? '')) ?? throw ValidationException::withMessages(['kind' => __('validation.required', ['attribute' => __('erp.planning.kind')])]);
        $viaGpw = $gpw !== null && $kind === PlanningKind::Project && $gpw->project_id === (int) ($data['project_id'] ?? 0) && $gpw->canBePlannedBy($actor);

        // An item's kind may be kept when editing even if the editor could not create that kind.
        if (! $viaGpw && ! $this->planning->canPlanKind($actor, $kind) && $kind !== $item?->kind) {
            throw ValidationException::withMessages(['kind' => __('erp.planning.errors.kind_not_allowed')]);
        }

        $start = PlanningItem::normaliseTime(substr((string) ($data['start_time'] ?? ''), 0, 5));
        $end = PlanningItem::normaliseTime(substr((string) ($data['end_time'] ?? ''), 0, 5));

        if (! preg_match('/^\d{2}:\d{2}:00$/', $start) || ! preg_match('/^\d{2}:\d{2}:00$/', $end) || $end <= $start) {
            throw ValidationException::withMessages(['end_time' => __('erp.planning.errors.times')]);
        }

        $projectId = null;

        if ($kind === PlanningKind::Project) {
            $projectId = (int) ($data['project_id'] ?? 0);
            $unchanged = $item !== null && $item->project_id === $projectId;

            if (! $unchanged && ! $viaGpw && ! $this->planning->plannableProjects($actor)->whereKey($projectId)->exists()) {
                throw ValidationException::withMessages(['project_id' => __('erp.planning.errors.project_not_open')]);
            }

            if (! $unchanged) {
                $this->checkGpw(Project::query()->with('category')->findOrFail($projectId), $data, $actor);
            }
        }

        $tasks = PlanningItem::normaliseTasks((array) ($data['tasks'] ?? []));

        if ($kind === PlanningKind::Warehouse && (blank($data['description'] ?? null) || $tasks === [])) {
            throw ValidationException::withMessages(['description' => __('erp.planning.errors.warehouse_details')]);
        }

        if ($kind === PlanningKind::Other && blank($data['title'] ?? null)) {
            throw ValidationException::withMessages(['title' => __('validation.required', ['attribute' => __('erp.planning.title_field')])]);
        }

        // A battery move (605): its origin or destination; an intervention: one of its COW codes; anything else is not kept.
        $workSiteId = (int) ($data['work_site_id'] ?? 0);
        $workSiteId = $workSiteId > 0 && array_key_exists($workSiteId, PlanningItem::siteOptions($projectId)) ? $workSiteId : null;

        return [
            'kind' => $kind,
            'project_id' => $projectId,
            'work_site_id' => $workSiteId,
            'title' => $kind === PlanningKind::Project ? null : (filled($data['title'] ?? null) ? trim((string) $data['title']) : null),
            'description' => $kind === PlanningKind::Project ? null : ($data['description'] ?? null),
            'tasks' => $kind === PlanningKind::Project || $tasks === [] ? null : $tasks,
            'notes' => $data['notes'] ?? null,
            'start_time' => $start,
            'end_time' => $end,
        ];
    }

    /**
     * "No GPW = no planning": throws unless the project has an approved GPW request, is an
     * intervention or was in the planning before; otherwise remembers the bypass to log.
     *
     * @param  array<string, mixed>  $data
     */
    protected function checkGpw(Project $project, array $data, User $actor): void
    {
        $this->gpwBypass = null;

        if (! $project->needsGpwForPlanning()) {
            return;
        }

        $reason = trim((string) ($data['gpw_bypass_reason'] ?? ''));

        if (mb_strlen($reason) < self::MIN_BYPASS_REASON && ! $this->planning->canBypassGpw($actor)) {
            throw ValidationException::withMessages(['gpw_bypass_reason' => __('erp.planning.errors.gpw_required', ['min' => self::MIN_BYPASS_REASON])]);
        }

        $this->gpwBypass = ['project' => $project, 'reason' => $reason !== '' ? $reason : null];
    }

    /**
     * @param  list<PlanningItem>  $items  what was planned, kept in the log as "12/10/2026 – 14/10/2026 · Jan, Piet (6)"
     */
    protected function logGpwBypass(User $actor, array $items): void
    {
        if ($this->gpwBypass === null) {
            return;
        }

        $items = collect($items);
        $dates = $items->map(fn (PlanningItem $item): string => CarbonImmutable::parse($item->date)->toDateString())->sort()->values();
        $names = User::query()->whereKey($items->pluck('user_id')->unique())->orderBy('name')->pluck('name');
        $planned = GpwRequest::formatPeriod(CarbonImmutable::parse($dates->first()), CarbonImmutable::parse($dates->last()))
            .' · '.$names->implode(', ').' ('.$items->count().')';

        $this->gpwBypass['project']->bypassGpw($actor, $this->gpwBypass['reason'], $planned);
        $this->gpwBypass = null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<CarbonImmutable>
     */
    protected function days(array $data): array
    {
        $from = CarbonImmutable::parse((string) ($data['date'] ?? ''))->startOfDay();
        $until = filled($data['until'] ?? null) ? CarbonImmutable::parse((string) $data['until'])->startOfDay() : $from;

        if ($until->lessThan($from)) {
            throw ValidationException::withMessages(['until' => __('validation.after_or_equal', ['attribute' => __('erp.planning.until'), 'date' => $from->format('d/m/Y')])]);
        }

        if ($from->diffInDays($until) >= self::MAX_DAYS) {
            throw ValidationException::withMessages(['until' => __('erp.planning.errors.too_long', ['days' => self::MAX_DAYS])]);
        }

        $single = $from->equalTo($until);

        return collect(CarbonPeriod::create($from, $until))
            ->map(fn ($day): CarbonImmutable => CarbonImmutable::instance($day))
            ->filter(fn (CarbonImmutable $day): bool => $single || ($data['include_weekends'] ?? false) || ! $day->isWeekend())
            ->values()
            ->all();
    }
}
