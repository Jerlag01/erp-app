<?php

namespace App\Support;

use App\Enums\AbsenceType;
use App\Enums\VacationStatus;
use App\Enums\VacationType;
use App\Models\Absence;
use App\Models\Employee;
use App\Models\TimesheetDay;
use App\Models\User;
use App\Models\VacationRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The leave an employee earns besides vacation and plans freely:
 *
 *  - mobility leave (MV): 1 day per 27 000 km registered in the prestations of the year before; the
 *    days are used in the year itself and are gone after 31 December;
 *  - seniority leave (AN): 1 day per calendar year once the employee is 25 years in service (from the
 *    date of employment; not for interim workers), from the day of that anniversary;
 *  - sollicitatieverlof (SV): during a notice period, one full day a week until its end date.
 */
class LeaveEntitlements
{
    public const KM_PER_MOBILITY_DAY = 27000;

    public const SENIORITY_YEARS = 25;

    /**
     * Kilometres of a year's prestations.
     */
    public function kilometres(User $user, int $year): int
    {
        return (int) TimesheetDay::query()
            ->where('user_id', $user->id)
            ->whereYear('date', $year)
            ->get(['rows'])
            ->sum(fn (TimesheetDay $day): int => Timesheets::km(Timesheets::normaliseRows((array) $day->rows)));
    }

    /**
     * @return array{km: int, earned: float, used: float, remaining: float, expires: string}
     */
    public function mobility(User $user, int $year): array
    {
        $km = $this->kilometres($user, $year - 1);
        $earned = (float) intdiv($km, self::KM_PER_MOBILITY_DAY);
        $used = $this->used($user, VacationType::Mobility, $year);

        return ['km' => $km, 'earned' => $earned, 'used' => $used, 'remaining' => max(0.0, $earned - $used), 'expires' => "{$year}-12-31"];
    }

    /**
     * The day of the 25th anniversary in service, or null (no register record, no employment date, interim).
     */
    public function seniorityDate(User $user): ?CarbonImmutable
    {
        $employee = $user->employee()->first();

        if (! $employee instanceof Employee || $employee->is_interim || $employee->employment_date === null) {
            return null;
        }

        return CarbonImmutable::instance($employee->employment_date)->addYears(self::SENIORITY_YEARS);
    }

    /**
     * @return array{from: ?string, earned: float, used: float, remaining: float}
     */
    public function seniority(User $user, int $year): array
    {
        $from = $this->seniorityDate($user);
        $earned = $from !== null && $from->year <= $year ? 1.0 : 0.0;
        $used = $this->used($user, VacationType::Seniority, $year);

        return ['from' => $from?->toDateString(), 'earned' => $earned, 'used' => $used, 'remaining' => max(0.0, $earned - $used)];
    }

    /**
     * The notice period in which sollicitatieverlof can be taken, or null.
     *
     * @return array{starts: string, ends: string}|null
     */
    public function noticePeriod(User $user): ?array
    {
        $employee = $user->employee()->first();

        return $employee?->notice_ends_on !== null && $employee->isEmployed()
            ? ['starts' => ($employee->notice_started_on ?? today())->toDateString(), 'ends' => $employee->notice_ends_on->toDateString()]
            : null;
    }

    /**
     * Whether the user has (or had) a day of sollicitatieverlof in the ISO week of the date.
     */
    public function jobSearchDayInWeek(User $user, CarbonInterface $date, ?int $ignoreRequestId = null): bool
    {
        $monday = CarbonImmutable::instance($date)->startOfWeek()->toDateString();
        $sunday = CarbonImmutable::instance($date)->endOfWeek()->toDateString();
        $ignoredAbsence = $ignoreRequestId ? VacationRequest::query()->whereKey($ignoreRequestId)->value('absence_id') : null;

        // Pending requests have no absence yet; approved ones (and those entered by administration) do.
        return VacationRequest::query()->where('user_id', $user->id)->where('type', VacationType::JobSearch)
            ->where('status', VacationStatus::Pending)
            ->when($ignoreRequestId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->overlapping($monday, $sunday)
            ->exists()
            || Absence::query()->where('user_id', $user->id)->where('type', AbsenceType::JobSearch)
                ->when($ignoredAbsence, fn ($query, int $id) => $query->whereKeyNot($id))
                ->overlapping($monday, $sunday)
                ->exists();
    }

    /**
     * Days of a kind used in a year: its absences (from approved requests or entered by administration)
     * plus the requests still pending.
     */
    public function used(User $user, VacationType $type, int $year): float
    {
        $absences = (float) Absence::query()->where('user_id', $user->id)->where('type', $type->absenceType())->whereYear('start_date', $year)->sum('days');
        $pending = (float) VacationRequest::query()->where('user_id', $user->id)->where('type', $type)->where('status', VacationStatus::Pending)->whereYear('start_date', $year)->sum('days');

        return round($absences + $pending, 1);
    }

    /**
     * The kinds of leave the user can ask now, with vacation and (with prestations) overtime always.
     *
     * @return list<VacationType>
     */
    public function requestable(User $user, bool $timesheets): array
    {
        $year = (int) today()->year;

        return array_values(array_filter([
            VacationType::Vacation,
            $timesheets ? VacationType::Overtime : null,
            $timesheets && $this->mobility($user, $year)['earned'] > 0 ? VacationType::Mobility : null,
            $this->seniority($user, $year)['earned'] > 0 ? VacationType::Seniority : null,
            $this->noticePeriod($user) !== null ? VacationType::JobSearch : null,
        ]));
    }

    /**
     * What is left of a kind of leave, as a sentence for the form and the app (null for vacation and
     * overtime, which have their own preview).
     */
    public function summary(User $user, VacationType $type, int $year): ?string
    {
        $format = fn (float $value): string => rtrim(rtrim(number_format(max(0, $value), 1, ',', ''), '0'), ',');

        return match ($type) {
            VacationType::Mobility => (fn (array $mobility): string => __('erp.vacations.mobility_balance', [
                'km' => number_format($mobility['km'], 0, ',', '.'),
                'previous' => $year - 1,
                'earned' => $format($mobility['earned']),
                'remaining' => $format($mobility['remaining']),
                'expires' => CarbonImmutable::parse($mobility['expires'])->format('d/m/Y'),
            ]))($this->mobility($user, $year)),
            VacationType::Seniority => (fn (array $seniority): string => __('erp.vacations.seniority_balance', [
                'remaining' => $format($seniority['remaining']),
                'year' => $year,
                'from' => $seniority['from'] ? CarbonImmutable::parse($seniority['from'])->format('d/m/Y') : '—',
            ]))($this->seniority($user, $year)),
            VacationType::JobSearch => ($notice = $this->noticePeriod($user)) !== null
                ? __('erp.vacations.job_search_balance', ['end' => CarbonImmutable::parse($notice['ends'])->format('d/m/Y')])
                : null,
            default => null,
        };
    }
}
