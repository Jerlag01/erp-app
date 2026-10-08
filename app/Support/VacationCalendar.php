<?php

namespace App\Support;

use App\Enums\VacationStatus;
use App\Models\Absence;
use App\Models\Holiday;
use App\Models\User;
use App\Models\VacationRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds a month grid (Monday to Sunday) with public holidays, the vacation requests and the
 * absences (medical, overtime as leave, family leave) the viewer may see. Pending and approved
 * requests are shown; rejected or withdrawn ones are not. Colleagues see private absence reasons
 * only as "absent", in a neutral colour.
 */
class VacationCalendar
{
    /**
     * Calendar colours per person, so the same colleague keeps the same colour.
     */
    public const PALETTE = ['#2563eb', '#16a34a', '#9333ea', '#0891b2', '#db2777', '#ca8a04', '#4f46e5', '#059669', '#dc2626', '#7c3aed'];

    /**
     * Colour of an absence whose reason the viewer may not see.
     */
    public const PRIVATE_ABSENCE_COLOR = '#64748b';

    /**
     * @return array{
     *     month: CarbonImmutable,
     *     weeks: list<list<array{date: CarbonImmutable, in_month: bool, is_today: bool, is_weekend: bool, holiday: ?string, entries: list<array{request: VacationRequest, color: string, pending: bool, half_day: bool, mine: bool}>}>>,
     *     people: list<array{name: string, color: string}>,
     * }
     */
    public function month(int $year, int $month, User $viewer, ?int $teamId = null): array
    {
        $first = CarbonImmutable::create($year, $month, 1);
        $gridStart = $first->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $first->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);

        $holidays = Holiday::query()
            ->whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->get()
            ->mapWithKeys(fn (Holiday $holiday): array => [$holiday->date->toDateString() => $holiday->name]);

        $requests = $this->requests($viewer, $gridStart, $gridEnd, $teamId);
        $absences = modules()->vacations() ? $this->absences($viewer, $gridStart, $gridEnd, $teamId) : collect();
        $people = $requests->pluck('user')->merge($absences->pluck('user'))->unique('id')->sortBy('name')->values();

        $weeks = [];

        foreach (CarbonPeriod::create($gridStart, $gridEnd) as $day) {
            $date = CarbonImmutable::instance($day);
            $key = $date->toDateString();
            $isWorkingDay = ! $date->isWeekend() && ! $holidays->has($key);

            $entries = $isWorkingDay
                ? $requests
                    ->filter(fn (VacationRequest $request): bool => $date->betweenIncluded($request->start_date, $request->end_date))
                    ->map(fn (VacationRequest $request): array => [
                        'request' => $request,
                        'color' => $this->colorFor($request->user_id),
                        'pending' => $request->status === VacationStatus::Pending,
                        'half_day' => $request->half_day,
                        'mine' => $request->user_id === $viewer->id,
                    ])
                    ->values()
                    ->all()
                : [];

            $dayAbsences = $isWorkingDay
                ? $absences
                    ->filter(fn (Absence $absence): bool => $date->betweenIncluded($absence->start_date, $absence->end_date))
                    ->map(fn (Absence $absence): array => [
                        'absence' => $absence,
                        'label' => $absence->labelFor($viewer),
                        'color' => $this->absenceColor($absence, $viewer),
                        'icon' => $absence->labelFor($viewer) === $absence->type->getLabel() ? $absence->type->getIcon() : null,
                        'half_day' => $absence->half_day,
                        'mine' => $absence->user_id === $viewer->id,
                    ])
                    ->values()
                    ->all()
                : [];

            $weeks[intdiv($gridStart->diffInDays($date), 7)][] = [
                'date' => $date,
                'in_month' => $date->month === $month,
                'is_today' => $date->isToday(),
                'is_weekend' => $date->isWeekend(),
                'holiday' => $holidays->get($key),
                'entries' => $entries,
                'absences' => $dayAbsences,
            ];
        }

        return [
            'month' => $first,
            'weeks' => array_values($weeks),
            'people' => $people->map(fn (User $user): array => ['name' => $user->name, 'color' => $this->colorFor($user->id)])->all(),
        ];
    }

    /**
     * @return Collection<int, VacationRequest>
     */
    protected function requests(User $viewer, CarbonImmutable $from, CarbonImmutable $until, ?int $teamId): Collection
    {
        return VacationRequest::query()
            ->visibleTo($viewer)
            ->overlapping($from->toDateString(), $until->toDateString())
            ->when($teamId, fn (Builder $query, int $teamId) => $query->whereHas('user.teams', fn (Builder $query) => $query->whereKey($teamId)))
            ->with('user')
            ->orderBy('start_date')
            ->get();
    }

    /**
     * @return Collection<int, Absence>
     */
    protected function absences(User $viewer, CarbonImmutable $from, CarbonImmutable $until, ?int $teamId): Collection
    {
        return Absence::query()
            ->visibleTo($viewer)
            ->overlapping($from->toDateString(), $until->toDateString())
            ->when($teamId, fn (Builder $query, int $teamId) => $query->whereHas('user.teams', fn (Builder $query) => $query->whereKey($teamId)))
            ->with('user')
            ->orderBy('start_date')
            ->get();
    }

    public function absenceColor(Absence $absence, User $viewer): string
    {
        return $absence->labelFor($viewer) === $absence->type->getLabel() ? $absence->type->hex() : self::PRIVATE_ABSENCE_COLOR;
    }

    public function colorFor(int $userId): string
    {
        return self::PALETTE[$userId % count(self::PALETTE)];
    }
}
