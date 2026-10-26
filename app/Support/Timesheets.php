<?php

namespace App\Support;

use App\Enums\AbsenceType;
use App\Enums\EmploymentCategory;
use App\Enums\VacationStatus;
use App\Models\Absence;
use App\Models\Holiday;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\TimesheetDay;
use App\Models\TimesheetWeek;
use App\Models\Training;
use App\Models\User;
use App\Models\VacationRequest;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The prestation sheet ("Prestatiefiche") rules, as in the Excel:
 * - Per working day: start at the warehouse, the COW site, any number of extra sites (three by default,
 *   the employee adds more) and the end at the warehouse. Hours (and kilometres) of the day count the start, site and end rows only; the
 *   extra sites in between are listed but not counted. With the lunch break on, 0.5 h is deducted.
 * - Vacation, medical leave, training, public holidays and company IR days (inhaalrust) count as a full day (8 h; half a day 4 h),
 *   family leave and overtime recuperation as 0 h: a short day then comes out of the overtime balance.
 *   The lunch break is always on for vacation, medical leave, training and holidays.
 * - Per week: total versus the expected hours (40) gives the overtime of the week; the overtime and
 *   kilometres carry over from week to week ("Report" → "Nu" → "Nieuw").
 * - Employees with the statute "bediende" register no times and no kilometres: every working day counts
 *   the day hours (8), less the part of the day an absence takes (see fixedDay()). The rest is the same.
 */
class Timesheets
{
    /**
     * The kinds of the six rows of a day.
     */
    public const KINDS = ['start', 'site', 'extra', 'extra', 'extra', 'end'];

    /**
     * Kinds of rows that count for the day totals: start at the warehouse, the site and the end.
     */
    public const COUNTED = ['start', 'site', 'end'];

    /**
     * Most extra sites a day can have.
     */
    public const MAX_EXTRA = 15;

    public const DAYS = ['maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag'];

    public function enabled(): bool
    {
        return modules()->timesheets();
    }

    public function dayHours(): float
    {
        return (float) settings()->get('timesheets.day_hours', 8);
    }

    public function lunchHours(): float
    {
        return (float) settings()->get('timesheets.lunch_hours', 0.5);
    }

    public function expectedHours(): float
    {
        return (float) settings()->get('timesheets.expected_hours', 40);
    }

    /** @var array<int, bool> per user, for one request */
    protected array $fixed = [];

    /**
     * Employees with the statute "bediende" (employee register) have fixed days: no times or kilometres.
     */
    public function fixedDay(User $user): bool
    {
        return $this->fixed[$user->id] ??= $user->employee()->first()?->employment_category === EmploymentCategory::Employee;
    }

    /**
     * The worked hours of a fixed day: the day hours, or what an absence leaves of them (a half day of
     * leave 4 h, a full day of leave, family leave or a holiday nothing; the absence credits its own hours).
     *
     * @param  array{credit: float}|null  $status
     */
    public function fixedWorked(?array $status): float
    {
        if ($status === null) {
            return $this->dayHours();
        }

        $credit = (float) $status['credit'];

        return $credit > 0 ? max(0.0, round($this->dayHours() - $credit, 2)) : 0.0;
    }

    public function canView(User $viewer, User $employee): bool
    {
        return $this->enabled() && ($viewer->id === $employee->id || $viewer->isAdmin() || $viewer->hasPermission('timesheets.see_all'));
    }

    /**
     * Employees change their own days until the week has been sent; administrators and
     * "timesheets.edit_all" always.
     */
    public function canEdit(User $viewer, User $employee, ?TimesheetWeek $week): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        if ($viewer->isAdmin() || $viewer->hasPermission('timesheets.edit_all')) {
            return true;
        }

        return $viewer->id === $employee->id && ! $week?->isSent();
    }

    public static function monday(CarbonInterface|string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date)->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
    }

    /**
     * Six empty rows.
     *
     * @return list<array<string, mixed>>
     */
    public static function emptyRows(): array
    {
        return array_map(fn (string $kind): array => [
            'kind' => $kind, 'km_departure' => null, 'km_arrival' => null, 'arrival' => null, 'departure' => null,
            'project_id' => null, 'imputation' => null, 'description' => null,
        ], self::KINDS);
    }

    /**
     * Cleans submitted rows: odometer readings as whole kilometres, times as HH:MM.
     *
     * @param  array<int|string, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    public static function normaliseRows(array $rows): array
    {
        $rows = array_values(array_filter($rows, 'is_array'));
        $km = fn (mixed $value): ?int => is_numeric($value) && $value >= 0 && $value < 10_000_000 ? (int) round((float) $value) : null;
        $time = fn (mixed $value): ?string => is_string($value) && preg_match('/^([01]?\d|2[0-3]):([0-5]\d)/', $value, $m) ? sprintf('%02d:%02d', $m[1], $m[2]) : null;
        $text = fn (mixed $value, int $max): ?string => filled($value) ? mb_substr(trim((string) $value), 0, $max) : null;
        $clean = fn (array $row, string $kind): array => [
            'kind' => $kind,
            'km_departure' => $km($row['km_departure'] ?? null),
            'km_arrival' => $km($row['km_arrival'] ?? null),
            'arrival' => $time($row['arrival'] ?? null),
            'departure' => $time($row['departure'] ?? null),
            'project_id' => is_numeric($row['project_id'] ?? null) ? (int) $row['project_id'] : null,
            'imputation' => $text($row['imputation'] ?? null, 30),
            'description' => $text($row['description'] ?? null, 255),
        ];

        // Rows without a kind (older data, six fixed rows) get it from their position.
        $kindOf = fn (array $row, int $index, int $count): string => in_array($row['kind'] ?? null, ['start', 'site', 'extra', 'end'], true)
            ? $row['kind']
            : ($index === 0 ? 'start' : ($index === 1 ? 'site' : ($index === $count - 1 ? 'end' : 'extra')));
        $byKind = ['start' => [], 'site' => [], 'extra' => [], 'end' => []];

        foreach ($rows as $index => $row) {
            $byKind[$kindOf($row, $index, count($rows))][] = $row;
        }

        // Always one start, one site and one end row; the extra sites in between, in their order.
        return [
            $clean($byKind['start'][0] ?? [], 'start'),
            $clean($byKind['site'][0] ?? [], 'site'),
            ...array_map(fn (array $row): array => $clean($row, 'extra'), array_slice($byKind['extra'], 0, self::MAX_EXTRA)),
            $clean($byKind['end'][0] ?? [], 'end'),
        ];
    }

    /**
     * Hours worked from the counted rows, minus the lunch break when anything was worked.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function workedHours(array $rows, bool $lunch): float
    {
        $worked = array_sum(array_map(fn (array $row): float => in_array($row['kind'], self::COUNTED, true) ? (self::hours($row['arrival'], $row['departure']) ?? 0.0) : 0.0, $rows));

        return round($worked > 0 && $lunch ? max(0, $worked - $this->lunchHours()) : $worked, 2);
    }

    /**
     * Kilometres of the counted rows.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public static function km(array $rows): int
    {
        return (int) array_sum(array_map(fn (array $row): int => in_array($row['kind'], self::COUNTED, true) ? (self::rowKm($row) ?? 0) : 0, $rows));
    }

    /**
     * Hours between arrival and departure (decimal, e.g. 06:15 → 13:30 = 7.25), or null when incomplete.
     */
    public static function hours(?string $arrival, ?string $departure): ?float
    {
        if (blank($arrival) || blank($departure)) {
            return null;
        }

        [$ah, $am] = array_map('intval', explode(':', $arrival));
        [$dh, $dm] = array_map('intval', explode(':', $departure));
        $minutes = ($dh * 60 + $dm) - ($ah * 60 + $am);

        return $minutes >= 0 ? round($minutes / 60, 2) : null;
    }

    /**
     * "06:15" → 6.25 (how the Excel shows times).
     */
    public static function decimal(?string $time): ?float
    {
        if (blank($time)) {
            return null;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return round($hours + $minutes / 60, 2);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function rowKm(array $row): ?int
    {
        return is_int($row['km_departure'] ?? null) && is_int($row['km_arrival'] ?? null) && $row['km_arrival'] >= $row['km_departure']
            ? $row['km_arrival'] - $row['km_departure']
            : null;
    }

    /**
     * "600-914": category and number of the project.
     */
    public static function imputation(Project $project): string
    {
        return $project->category
            ? $project->category->code.'-'.$project->category->numbering->formatNumber((int) $project->number)
            : (string) $project->reference;
    }

    /**
     * Projects in the employee's planning that day (offered first for the imputation).
     *
     * @return Collection<int, Project>
     */
    public function plannedProjects(User $user, CarbonInterface $date): Collection
    {
        if (! modules()->planning()) {
            return new Collection;
        }

        return Project::query()
            ->whereHas('planningItems', fn (Builder $query) => $query->where('user_id', $user->id)->whereDate('date', $date->toDateString()))
            ->with(['category', 'workSite'])
            ->get();
    }

    /**
     * Projects the employee can see, by reference, number, COW code or description.
     *
     * @return Collection<int, Project>
     */
    public function searchProjects(User $user, string $search, int $limit = 50): Collection
    {
        $term = '%'.addcslashes(trim($search), '%_\\').'%';

        return Project::query()
            ->visibleTo($user)
            ->where(fn (Builder $query) => $query->where('reference', 'like', $term)->orWhere('short_description', 'like', $term)->orWhere('cow_code', 'like', $term)->orWhere('number', 'like', $term))
            ->with(['category', 'workSite'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Whether the employee may book on a project: one they can see or that is in their planning.
     */
    public function mayBookOn(User $user, Project $project): bool
    {
        return Project::query()->visibleTo($user)->whereKey($project->id)->exists()
            || (modules()->planning() && $project->planningItems()->where('user_id', $user->id)->exists());
    }

    /**
     * The licence plate of the vehicle of the employee's team (fleet module).
     */
    public function plateFor(User $user): ?string
    {
        if (! modules()->fleet() || $user->teamIds() === []) {
            return null;
        }

        return Vehicle::query()->whereIn('team_id', $user->teamIds())->whereNotNull('plate_number')->orderBy('plate_number')->value('plate_number');
    }

    /**
     * Rows for a new day, with the projects of the employee's planning that day: the first one on the
     * site row, the next ones on the extra rows.
     *
     * @return list<array<string, mixed>>
     */
    public function prefillRows(User $user, CarbonInterface $date): array
    {
        $rows = self::emptyRows();

        if (! modules()->planning()) {
            return $rows;
        }

        $projects = PlanningItem::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date->toDateString())
            ->whereNotNull('project_id')
            ->with('project.category', 'project.workSite')
            ->orderBy('start_time')
            ->get()
            ->pluck('project')
            ->filter()
            ->unique('id')
            ->values();

        // The site row, then the extra rows (more are added when the planning has more projects).
        $end = array_pop($rows);

        foreach ($projects as $offset => $project) {
            $index = $offset + 1;
            $rows[$index] = [...($rows[$index] ?? [...$end, 'kind' => 'extra', 'km_departure' => null, 'km_arrival' => null, 'arrival' => null, 'departure' => null]),
                'project_id' => $project->id, 'imputation' => self::imputation($project), 'description' => $project->workSite?->cow_code ?? $project->cow_code];
        }

        return [...array_slice($rows, 0, 1 + self::MAX_EXTRA + 1), $end];
    }

    /**
     * Every working day (Monday–Friday) between two dates with what counts: worked hours, credited hours
     * for absences, the total, kilometres and the automatic note.
     *
     * @return array<string, array{date: CarbonImmutable, record: ?TimesheetDay, rows: list<array<string, mixed>>, lunch: bool, status: ?array{type: string, note: string, credit: float, lunch_locked: bool}, worked: float, credit: float, total: float, km: int, remarks: ?string}>
     */
    public function days(User $user, CarbonInterface $from, CarbonInterface $until): array
    {
        $from = CarbonImmutable::instance($from)->startOfDay();
        $until = CarbonImmutable::instance($until)->startOfDay();
        $statuses = $this->statuses($user, $from, $until);
        $records = TimesheetDay::query()->where('user_id', $user->id)->whereBetween('date', [$from->toDateString(), $until->toDateString()])->get()
            ->keyBy(fn (TimesheetDay $day): string => $day->date->toDateString());
        $days = [];

        foreach (CarbonPeriod::create($from, $until) as $date) {
            $date = CarbonImmutable::instance($date);

            if ($date->isWeekend()) {
                continue;
            }

            $key = $date->toDateString();
            $record = $records->get($key);
            $status = $statuses[$key] ?? null;
            $rows = $record ? self::normaliseRows((array) $record->rows) : self::emptyRows();
            $lunch = ($status['lunch_locked'] ?? false) || ($record?->lunch_break ?? true);
            $fixed = $this->fixedDay($user);
            $worked = $fixed ? $this->fixedWorked($status) : $this->workedHours($rows, $lunch);
            $credit = (float) ($status['credit'] ?? 0);

            $days[$key] = [
                'date' => $date,
                'record' => $record,
                'rows' => $rows,
                'lunch' => $lunch,
                'status' => $status,
                'worked' => round($worked, 2),
                'credit' => $credit,
                'total' => round($worked + $credit, 2),
                'km' => $fixed ? 0 : self::km($rows),
                'remarks' => $record?->remarks,
            ];
        }

        return $days;
    }

    /**
     * The week of a date with everything the form and the PDF show.
     *
     * @return array{monday: CarbonImmutable, friday: CarbonImmutable, number: int, plate: ?string, record: ?TimesheetWeek, days: list<array<string, mixed>>, km: int, hours: float, expected: float, km_carried: int, km_new: int, overtime_carried: float, overtime_now: float, overtime_new: float, notes: list<string>}
     */
    public function week(User $user, CarbonInterface|string $date): array
    {
        $monday = self::monday($date);
        $friday = $monday->addDays(4);
        $record = TimesheetWeek::query()->where('user_id', $user->id)->whereDate('week_start', $monday->toDateString())->first();
        $days = array_values($this->days($user, $monday, $friday));
        [$kmCarried, $overtimeCarried] = $this->carried($user, $monday, $record);
        $hours = round(array_sum(array_column($days, 'total')), 2);
        $km = (int) array_sum(array_column($days, 'km'));
        $expected = (float) ($record?->expected_hours ?? $this->expectedHours());

        $notes = [];
        foreach ($days as $index => $day) {
            $prefix = mb_substr(self::DAYS[$index], 0, 2).' '.$day['date']->format('d/m').': ';

            if ($day['status']) {
                $notes[] = $prefix.$day['status']['note'];
            }

            if (filled($day['remarks'])) {
                $notes[] = $prefix.trim((string) $day['remarks']);
            }
        }

        if (filled($record?->remarks)) {
            $notes[] = trim((string) $record->remarks);
        }

        return [
            'monday' => $monday,
            'friday' => $friday,
            'number' => $monday->isoWeek(),
            'plate' => $record?->plate_number ?? $this->plateFor($user),
            'record' => $record,
            'days' => $days,
            'km' => $km,
            'hours' => $hours,
            'expected' => $expected,
            'km_carried' => $kmCarried,
            'km_new' => $kmCarried + $km,
            'overtime_carried' => $overtimeCarried,
            'overtime_now' => round($hours - $expected, 2),
            'overtime_new' => round($overtimeCarried + $hours - $expected, 2),
            'notes' => $notes,
        ];
    }

    /**
     * The overtime an employee can still take as paid leave: the balance carried into this week, minus
     * overtime leave that is requested (pending) or approved from this week on (those days are not in
     * the balance yet). One day of leave uses the hours of a working day.
     *
     * @return array{balance: float, reserved: float, available: float, day_hours: float}
     */
    public function overtimeBalance(User $user, ?int $ignoreRequestId = null): array
    {
        $monday = self::monday(today()->toImmutable());
        $record = TimesheetWeek::query()->where('user_id', $user->id)->whereDate('week_start', $monday->toDateString())->first();
        $balance = $this->carried($user, $monday, $record)[1];
        $days = (float) VacationRequest::query()
            ->overtime()
            ->where('user_id', $user->id)
            ->whereIn('status', [VacationStatus::Pending, VacationStatus::Approved])
            ->whereDate('end_date', '>=', $monday->toDateString())
            ->when($ignoreRequestId, fn (Builder $query, int $id) => $query->whereKeyNot($id))
            ->sum('days');
        $reserved = round($days * $this->dayHours(), 2);

        return [
            'balance' => $balance,
            'reserved' => $reserved,
            'available' => round($balance - $reserved, 2),
            'day_hours' => $this->dayHours(),
        ];
    }

    /**
     * Kilometres and overtime carried into a week: set by an administrator on that week, or the
     * "new" values of the previous week, counted from the employee's first week.
     *
     * @return array{0: int, 1: float}
     */
    public function carried(User $user, CarbonImmutable $monday, ?TimesheetWeek $record = null): array
    {
        $weeks = TimesheetWeek::query()->where('user_id', $user->id)->whereDate('week_start', '<=', $monday->toDateString())->get()
            ->keyBy(fn (TimesheetWeek $week): string => $week->week_start->toDateString());
        $firstDay = TimesheetDay::query()->where('user_id', $user->id)->min('date');
        $starts = array_filter([$weeks->keys()->min(), $firstDay ? self::monday($firstDay)->toDateString() : null]);

        if ($starts === [] || min($starts) >= $monday->toDateString()) {
            return [(int) ($record?->km_carried ?? 0), (float) ($record?->overtime_carried ?? 0)];
        }

        $start = CarbonImmutable::parse(min($starts));
        $days = $this->days($user, $start, $monday->subDays(3));
        [$km, $overtime] = [0, 0.0];

        for ($week = $start; $week->lessThan($monday); $week = $week->addWeek()) {
            $override = $weeks->get($week->toDateString());
            $km = $override?->km_carried ?? $km;
            $overtime = $override?->overtime_carried !== null ? (float) $override->overtime_carried : $overtime;
            $weekDays = array_filter($days, fn (array $day): bool => $day['date']->betweenIncluded($week, $week->addDays(4)));
            $km += (int) array_sum(array_column($weekDays, 'km'));
            $overtime += array_sum(array_column($weekDays, 'total')) - (float) ($override?->expected_hours ?? $this->expectedHours());
        }

        return [
            (int) ($record?->km_carried ?? $km),
            round($record?->overtime_carried !== null ? (float) $record->overtime_carried : $overtime, 2),
        ];
    }

    /**
     * Absences, vacation, trainings and public holidays per date: what is noted and credited.
     *
     * @return array<string, array{type: string, note: string, credit: float, lunch_locked: bool}>
     */
    public function statuses(User $user, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $day = $this->dayHours();
        $statuses = [];
        $set = function (CarbonInterface $start, CarbonInterface $end, string $type, string $note, float $credit, bool $lunchLocked) use (&$statuses, $from, $until): void {
            foreach (CarbonPeriod::create(CarbonImmutable::instance($start)->max($from), CarbonImmutable::instance($end)->min($until)) as $date) {
                $statuses[$date->toDateString()] ??= ['type' => $type, 'note' => $note, 'credit' => $credit, 'lunch_locked' => $lunchLocked];
            }
        };

        // In order of priority: the first one found for a date wins.
        Holiday::query()->whereBetween('date', [$from->toDateString(), $until->toDateString()])->get()
            ->each(fn (Holiday $holiday) => $holiday->isRecuperation()
                ? $set($holiday->date, $holiday->date, 'recuperation', str_starts_with(mb_strtolower($holiday->name), 'inhaalrust') ? $holiday->name : trim('Inhaalrust: '.$holiday->name, ': '), $day, true)
                : $set($holiday->date, $holiday->date, 'holiday', 'Feestdag: '.$holiday->name, $day, true));

        if (modules()->vacations()) {
            $absences = Absence::query()->where('user_id', $user->id)->overlapping($from->toDateString(), $until->toDateString())->get();

            $absences->where('type', AbsenceType::Medical)
                ->each(fn (Absence $absence) => $set($absence->start_date, $absence->end_date, 'medical', $absence->half_day ? 'Ziekte (halve dag)' : 'Ziekte', $absence->half_day ? $day / 2 : $day, true));

            VacationRequest::query()->vacation()->where('user_id', $user->id)->where('status', VacationStatus::Approved)
                ->whereDate('start_date', '<=', $until->toDateString())->whereDate('end_date', '>=', $from->toDateString())->get()
                ->each(fn (VacationRequest $request) => $set($request->start_date, $request->end_date, 'vacation', $request->half_day ? 'Verlof (halve dag)' : 'Verlof', $request->half_day ? $day / 2 : $day, true));
        }

        if (modules()->planning()) {
            Training::query()->overlapping($from, $until)->whereHas('participants', fn ($query) => $query->whereKey($user->id))->get()
                ->each(fn (Training $training) => $set($training->start_date, $training->end_date, 'training', $training->note(), $day, true));
        }

        if (modules()->vacations()) {
            $absences->where('type', AbsenceType::Family)
                ->each(fn (Absence $absence) => $set($absence->start_date, $absence->end_date, 'family', 'Familiaal verlof', 0, false));
            $absences->where('type', AbsenceType::Unpaid)
                ->each(fn (Absence $absence) => $set($absence->start_date, $absence->end_date, 'unpaid', __('erp.enums.absence_type.unpaid'), 0, false));
            $absences->where('type', AbsenceType::Overtime)
                ->each(fn (Absence $absence) => $set($absence->start_date, $absence->end_date, 'overtime', 'Recuperatie overuren', 0, false));
        }

        return $statuses;
    }

    /**
     * Whether every employee also gets their own weekly sheet by e-mail (Settings > Prestations).
     */
    public function sendsToEmployees(): bool
    {
        return (bool) settings()->get('timesheets.send_to_employee', true);
    }

    /**
     * Who gets the weekly sheets, for the confirmation: "the employee themself, payroll@…".
     */
    public function recipientsDescription(): string
    {
        return implode(', ', array_filter([
            $this->sendsToEmployees() ? __('erp.timesheets.recipient_employee') : null,
            ...$this->recipients(),
        ])) ?: '—';
    }

    /**
     * General recipients of the weekly PDFs of everyone (Settings > Prestations).
     *
     * @return list<string>
     */
    public function recipients(): array
    {
        return collect((array) settings()->get('timesheets.recipients', []))
            ->map(fn (mixed $email): string => strtolower(trim((string) $email)))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
