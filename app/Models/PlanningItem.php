<?php

namespace App\Models;

use App\Enums\PlanningKind;
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
#[Fillable(['user_id', 'date', 'start_time', 'end_time', 'kind', 'project_id', 'title', 'description', 'tasks', 'notes', 'created_by', 'updated_by'])]
class PlanningItem extends Model
{
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
     * The project's description or client, under the topic.
     */
    public function subtitle(): ?string
    {
        return $this->kind === PlanningKind::Project ? $this->project?->subtitle() : null;
    }

    /**
     * "Mon 12/10/2026 07:30–16:00 · 604-001-02GAM", in the given language.
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
        ];
    }
}
