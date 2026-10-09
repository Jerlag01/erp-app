<?php

namespace App\Actions\Planning;

use App\Enums\PlanningKind;
use App\Models\PlanningItem;
use App\Models\User;
use App\Support\Planning;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds, changes and removes planning items, enforcing who may plan what, open projects only,
 * no items on blocked days (vacation, absences) and no overlapping timeslots for one person.
 * Every change is notified to the employee (see NotifyPlanningChange).
 */
class SavePlanningItems
{
    /**
     * Longest period that can be planned in one go.
     */
    public const MAX_DAYS = 62;

    /** Whether the last call could send all its e-mails. */
    public bool $mailFailed = false;

    public function __construct(protected Planning $planning, protected NotifyPlanningChange $notifier) {}

    /**
     * One item per employee per day from "date" to "until" (weekends only when asked). Days that are
     * blocked or already taken in that timeslot are skipped and reported.
     *
     * @param  array<string, mixed>  $data
     * @return array{created: list<PlanningItem>, skipped: list<string>}
     */
    public function create(array $data, User $actor): array
    {
        $attributes = $this->attributes($data, $actor);
        $employees = User::query()->active()->whereKey((array) ($data['user_ids'] ?? []))->orderBy('name')->get();

        if ($employees->isEmpty()) {
            throw ValidationException::withMessages(['user_ids' => __('erp.planning.errors.no_employee')]);
        }

        $days = $this->days($data);
        $created = [];
        $skipped = [];

        DB::transaction(function () use ($employees, $days, $attributes, $actor, &$created, &$skipped): void {
            foreach ($employees as $employee) {
                foreach ($days as $day) {
                    if ($reason = $this->planning->blockedReason($employee, $day, $actor)) {
                        $skipped[] = "{$employee->name} · {$day->format('d/m/Y')}: {$reason}";

                        continue;
                    }

                    if ($this->planning->clashes($employee->id, $day->toDateString(), $attributes['start_time'], $attributes['end_time'])) {
                        $skipped[] = "{$employee->name} · {$day->format('d/m/Y')}: ".__('erp.planning.errors.clash');

                        continue;
                    }

                    $created[] = PlanningItem::query()->create([
                        ...$attributes,
                        'user_id' => $employee->id,
                        'date' => $day->toDateString(),
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]);
                }
            }
        });

        if ($created === []) {
            throw ValidationException::withMessages(['date' => implode(' — ', array_slice($skipped, 0, 5))]);
        }

        $this->mailFailed = false;

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
        $item->loadMissing('project', 'user');
        $before = clone $item;
        $attributes = $this->attributes($data, $actor, $item);
        $employee = User::query()->active()->find($data['user_id'] ?? $item->user_id) ?? throw ValidationException::withMessages(['user_id' => __('erp.planning.errors.no_employee')]);
        $date = CarbonImmutable::parse($data['date'] ?? $item->date);

        if ($reason = $this->planning->blockedReason($employee, $date, $actor)) {
            throw ValidationException::withMessages(['date' => __('erp.planning.errors.blocked', ['reason' => $reason])]);
        }

        if ($this->planning->clashes($employee->id, $date->toDateString(), $attributes['start_time'], $attributes['end_time'], $item->id)) {
            throw ValidationException::withMessages(['start_time' => __('erp.planning.errors.clash')]);
        }

        $attributes['tasks'] = $attributes['tasks'] === null ? null : PlanningItem::normaliseTasks($attributes['tasks'], $item->taskList());

        $item->fill([...$attributes, 'user_id' => $employee->id, 'date' => $date->toDateString(), 'updated_by' => $actor->id]);

        if (! $item->isDirty()) {
            return $item;
        }

        $item->save();
        $item->load('project', 'user');
        $mail = (bool) ($data['notify_mail'] ?? false);

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
        $item->loadMissing('project', 'user');
        $item->delete();

        $this->mailFailed = ! $this->notifier->handle($item->user, NotifyPlanningChange::DELETED, [$item], $actor, $mail);
    }

    /**
     * The validated item fields; throws when the actor may not plan this kind or project.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributes(array $data, User $actor, ?PlanningItem $item = null): array
    {
        $kind = PlanningKind::tryFrom((string) ($data['kind'] ?? '')) ?? throw ValidationException::withMessages(['kind' => __('validation.required', ['attribute' => __('erp.planning.kind')])]);

        // An item's kind may be kept when editing even if the editor could not create that kind.
        if (! $this->planning->canPlanKind($actor, $kind) && $kind !== $item?->kind) {
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

            if (! $unchanged && ! $this->planning->plannableProjects($actor)->whereKey($projectId)->exists()) {
                throw ValidationException::withMessages(['project_id' => __('erp.planning.errors.project_not_open')]);
            }
        }

        $tasks = PlanningItem::normaliseTasks((array) ($data['tasks'] ?? []));

        if ($kind === PlanningKind::Warehouse && (blank($data['description'] ?? null) || $tasks === [])) {
            throw ValidationException::withMessages(['description' => __('erp.planning.errors.warehouse_details')]);
        }

        if ($kind === PlanningKind::Other && blank($data['title'] ?? null)) {
            throw ValidationException::withMessages(['title' => __('validation.required', ['attribute' => __('erp.planning.title_field')])]);
        }

        return [
            'kind' => $kind,
            'project_id' => $projectId,
            'title' => $kind === PlanningKind::Project ? null : (filled($data['title'] ?? null) ? trim((string) $data['title']) : null),
            'description' => $kind === PlanningKind::Project ? null : ($data['description'] ?? null),
            'tasks' => $kind === PlanningKind::Project || $tasks === [] ? null : $tasks,
            'notes' => $data['notes'] ?? null,
            'start_time' => $start,
            'end_time' => $end,
        ];
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
