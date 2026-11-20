<?php

namespace App\Models;

use App\Actions\Gpw\SyncGpwStatus;
use App\Enums\BatteryTechnology;
use App\Enums\PlanningKind;
use App\Support\Interventions;
use App\Support\OutlookCalendar;
use App\Support\Planning;
use Carbon\CarbonInterface;
use Database\Factories\PlanningItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item in an employee's planning: a timeslot on a day for an open project, a warehouse job
 * (description and task list) or something else.
 */
#[Fillable(['user_id', 'date', 'start_time', 'end_time', 'kind', 'is_draft', 'confirmed_at', 'confirmed_by', 'project_id', 'work_site_id', 'title', 'description', 'tasks', 'notes', 'created_by', 'updated_by'])]
class PlanningItem extends Model
{
    /** The global scope that hides drafts. */
    public const CONFIRMED_SCOPE = 'confirmed';

    /** @use HasFactory<PlanningItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The site the item goes to when it is not the project's own (a battery move: origin or destination; an
     * intervention on several COW codes: one of them).
     *
     * @return BelongsTo<WorkSite, $this>
     */
    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class);
    }

    /**
     * Where the work is done (the route details): the chosen site, else the project's.
     */
    public function site(): ?WorkSite
    {
        return $this->kind === PlanningKind::Project ? ($this->workSite ?? $this->project?->workSite) : null;
    }

    /**
     * The COW code of that site (or the project's COW code).
     */
    public function cowCode(): ?string
    {
        return $this->site()?->cow_code ?? ($this->kind === PlanningKind::Project ? $this->project?->cow_code : null);
    }

    /**
     * The address to drive to: the chosen site's, else the project's own address or its site's.
     */
    public function addressLine(): ?string
    {
        if ($this->kind !== PlanningKind::Project) {
            return null;
        }

        return $this->workSite?->addressLine() ?? $this->project?->addressLine() ?? $this->project?->workSite?->addressLine();
    }

    /**
     * The sites a project can be planned at when it has more than one, by id with a label: a battery move
     * (605) its origin and destination, an intervention in one WO/PO its COW codes. Empty for the others.
     *
     * @return array<int, string>
     */
    public static function siteOptions(?int $projectId): array
    {
        $project = $projectId ? Project::query()->with(['category', 'workSite', 'interventionWorkSites', 'interventionReports.workSite'])->find($projectId) : null;
        $interventions = app(Interventions::class);

        if ($project !== null && $interventions->appliesTo($project) && $interventions->hasSeveralSites($project)) {
            return collect($interventions->sites($project))
                ->filter(fn (array $site): bool => $site['work_site'] !== null)
                ->mapWithKeys(fn (array $site): array => [$site['work_site']->id => trim($site['work_site']->cow_code.' — '.($site['work_site']->addressLine() ?? ''), ' —')])
                ->all();
        }

        return self::moveSiteOptions($projectId);
    }

    /**
     * The site proposed when planning: a battery move's destination, an intervention's first COW code still
     * to close (else its first).
     */
    public static function defaultSiteId(?int $projectId): ?int
    {
        $options = self::siteOptions($projectId);

        if ($options === []) {
            return null;
        }

        $project = Project::query()->with(['category', 'workSite', 'interventionWorkSites', 'interventionReports.workSite'])->find($projectId);
        $interventions = app(Interventions::class);

        if ($project !== null && $interventions->appliesTo($project) && $interventions->hasSeveralSites($project)) {
            $open = collect($interventions->openSites($project))->first(fn (array $site): bool => array_key_exists((int) $site['work_site_id'], $options));

            return $open['work_site_id'] ?? array_key_first($options);
        }

        return array_key_last($options);
    }

    /**
     * The sites a battery move (605) can be planned at: its origin and destination, by id with a label.
     *
     * @return array<int, string>
     */
    public static function moveSiteOptions(?int $projectId): array
    {
        $dossier = $projectId ? MoveDossier::query()->with(['fromSite', 'toSite', 'project.workSite'])->where('project_id', $projectId)->latest('id')->first() : null;

        if ($dossier === null) {
            return [];
        }

        $label = fn (string $key, WorkSite $site): string => __($key).': '.trim($site->cow_code.' — '.($site->addressLine() ?? ''), ' —');
        $options = [];

        if ($dossier->fromSite !== null) {
            $options[$dossier->fromSite->id] = $label('erp.planning.move_origin', $dossier->fromSite);
        }

        if (($to = $dossier->destination()) !== null) {
            $options[$to->id] = $label('erp.planning.move_destination', $to);
        }

        return $options;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Who confirmed the draft (pushed it to the real planning).
     *
     * @return BelongsTo<User, $this>
     */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Confirmed items and drafts together (the planning page of those who plan).
     *
     * @return Builder<PlanningItem>
     */
    public static function withDrafts(): Builder
    {
        return static::query()->withoutGlobalScope(self::CONFIRMED_SCOPE);
    }

    /**
     * Only the drafts.
     *
     * @return Builder<PlanningItem>
     */
    public static function drafts(): Builder
    {
        return static::withDrafts()->where('planning_items.is_draft', true);
    }

    /**
     * Items of the people the viewer may see, plus the items on projects they lead.
     *
     * @param  Builder<PlanningItem>  $query
     */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        $userIds = app(Planning::class)->visibleUserIds($viewer);

        if ($userIds === null) {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->whereIn('planning_items.user_id', $userIds)
            ->orWhereHas('project.leaders', fn (Builder $query) => $query->whereKey($viewer->id)));
    }

    /**
     * @param  Builder<PlanningItem>  $query
     */
    public function scopeBetween(Builder $query, CarbonInterface $from, CarbonInterface $until): void
    {
        $query->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $until->toDateString());
    }

    /**
     * Items of the same person on the same day whose timeslot overlaps start–end.
     *
     * @param  Builder<PlanningItem>  $query
     */
    public function scopeClashingWith(Builder $query, int $userId, string $date, string $start, string $end): void
    {
        $query->where('user_id', $userId)
            ->whereDate('date', $date)
            ->where('start_time', '<', self::normaliseTime($end))
            ->where('end_time', '>', self::normaliseTime($start));
    }

    public function startsAt(): string
    {
        return substr((string) $this->start_time, 0, 5);
    }

    public function endsAt(): string
    {
        return substr((string) $this->end_time, 0, 5);
    }

    public function timeRange(): string
    {
        return $this->startsAt().'–'.$this->endsAt();
    }

    /**
     * What the item is about: the project reference, "Warehouse" or the free title.
     */
    public function topic(): string
    {
        return match ($this->kind) {
            PlanningKind::Project => $this->project?->reference ?? __('erp.planning.removed_project'),
            PlanningKind::Warehouse => filled($this->title) ? __('erp.enums.planning_kind.warehouse').': '.$this->title : __('erp.enums.planning_kind.warehouse'),
            PlanningKind::Other => (string) $this->title,
        };
    }

    /**
     * The project's description or client, under the topic. Not for 604 projects: they show their
     * batteries instead (batteries()).
     */
    public function subtitle(): ?string
    {
        return $this->kind === PlanningKind::Project && ! $this->isBatteryProject() ? $this->project?->subtitle() : null;
    }

    /**
     * A 604 project (battery replacement) while the battery module is on.
     */
    public function isBatteryProject(): bool
    {
        return $this->kind === PlanningKind::Project
            && $this->project !== null
            && modules()->batteries()
            && $this->project->category?->code === settings()->get(BatteryDossier::CATEGORY_SETTING);
    }

    /**
     * On a 604 project: the batteries that are removed and placed, "2 x 500Ah OPzS -> 2 x 490Ah OPzV",
     * from its battery dossier (or only the new ones from its BAE order while there is no dossier).
     */
    public function batteries(): ?string
    {
        if (! $this->isBatteryProject()) {
            return null;
        }

        $project = $this->project;

        if ($summary = $project->batteryDossier?->swapSummary()) {
            return $summary;
        }

        $order = $project->baeOrder;
        $placed = $order?->battery_model_id ? BatteryDossier::batteryText($order->battery_count, $order->capacity_ah, BatteryTechnology::tryFrom((string) $order->technology)) : null;

        return $placed ? '-> '.$placed : null;
    }

    /**
     * "Mon 12/10/2026 07:30–16:00 · 604-0001-02GAM", in the given language.
     */
    public function summary(?string $locale = null): string
    {
        $date = $this->date->locale($locale ?? app()->getLocale())->translatedFormat('D d/m/Y');

        return "{$date} {$this->timeRange()} · ".trim($this->topic().(filled($subtitle = $this->subtitle()) ? " ({$subtitle})" : ''));
    }

    /**
     * @return list<array{text: string, done: bool}>
     */
    public function taskList(): array
    {
        return self::normaliseTasks($this->tasks ?? []);
    }

    /**
     * Accepts the plain list of texts from the form, or stored tasks; keeps "done" for texts that already existed.
     *
     * @param  array<int, mixed>  $tasks
     * @param  list<array{text: string, done: bool}>  $previous
     * @return list<array{text: string, done: bool}>
     */
    public static function normaliseTasks(array $tasks, array $previous = []): array
    {
        $done = collect($previous)->filter(fn (array $task): bool => $task['done'])->pluck('text')->all();

        return collect($tasks)
            ->map(fn (mixed $task): array => is_array($task)
                ? ['text' => trim((string) ($task['text'] ?? '')), 'done' => (bool) ($task['done'] ?? false)]
                : ['text' => trim((string) $task), 'done' => false])
            ->filter(fn (array $task): bool => $task['text'] !== '')
            ->map(fn (array $task): array => [...$task, 'done' => $task['done'] || in_array($task['text'], $done, true)])
            ->values()
            ->all();
    }

    public static function normaliseTime(string $time): string
    {
        return strlen($time) === 5 ? "{$time}:00" : $time;
    }

    protected static function booted(): void
    {
        // Drafts exist only on the planning page of those who plan: everything else (mobile app,
        // timesheets, GPW, dashboards, client portal, Outlook) sees the confirmed planning only.
        static::addGlobalScope(self::CONFIRMED_SCOPE, fn (Builder $query) => $query->where('planning_items.is_draft', false));

        // Mirrored in the employee's Outlook calendar, after the response (see OutlookCalendar); drafts are not.
        static::created(function (self $item): void {
            if (! $item->is_draft) {
                defer(fn () => app(OutlookCalendar::class)->sync($item->fresh() ?? $item));
            }
        });
        static::updated(function (self $item): void {
            if (! $item->is_draft && $item->wasChanged(['is_draft', 'user_id', 'date', 'start_time', 'end_time', 'kind', 'project_id', 'title', 'description', 'tasks', 'notes'])) {
                defer(fn () => app(OutlookCalendar::class)->sync($item->fresh() ?? $item));
            }
        });
        static::deleted(function (self $item): void {
            [$mailbox, $event] = [$item->outlook_mailbox, $item->outlook_event_id];

            if (filled($event)) {
                defer(fn () => app(OutlookCalendar::class)->remove($mailbox, $event));
            }
        });

        // The GPW requests of the project follow its (confirmed) planning (planned, in progress; see SyncGpwStatus).
        static::saved(function (self $item): void {
            if ($item->is_draft) {
                return;
            }

            if ($item->wasRecentlyCreated || $item->wasChanged(['is_draft', 'project_id', 'date', 'kind'])) {
                foreach (array_unique(array_filter([$item->project_id, $item->getOriginal('project_id')])) as $projectId) {
                    app(SyncGpwStatus::class)->forProject((int) $projectId);
                }
            }
        });
        static::deleted(function (self $item): void {
            if ($item->project_id !== null && ! $item->is_draft) {
                app(SyncGpwStatus::class)->forProject($item->project_id);
            }
        });

        static::saving(function (self $item): void {
            // Projects have no task list; warehouse jobs and other items have no project.
            if ($item->kind === PlanningKind::Project) {
                $item->tasks = null;
            } else {
                $item->project_id = null;
                $item->tasks = filled($item->tasks) ? $item->tasks : null;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'kind' => PlanningKind::class,
            'tasks' => 'array',
            'is_draft' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }
}
