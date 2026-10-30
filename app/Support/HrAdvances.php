<?php

namespace App\Support;

use App\Enums\AbsenceType;
use App\Enums\EmploymentCategory;
use App\Enums\VacationStatus;
use App\Models\Absence;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\VacationRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * The monthly advance of an employee: the start value of their statute (workers €620, servants
 * €1000 by default) × the days worked in the period (by default the 1st up to the 15th of the month)
 * ÷ the working days of that period. Weekends never count.
 *
 * Only employees of the register, in service and not on interim.
 *
 * Counted as worked: normal prestations, medical leave, public holidays, IR days, overtime
 * recuperation and trainings. Not counted: unpaid leave, family leave and approved vacation — but a
 * single vacation day in the period still counts as worked.
 */
class HrAdvances
{
    public const WORKERS = 'workers';

    public const SERVANTS = 'servants';

    /**
     * @return list<EmploymentCategory>
     */
    public static function categories(string $group): array
    {
        return $group === self::WORKERS
            ? [EmploymentCategory::Worker]
            : [EmploymentCategory::Employee, EmploymentCategory::Manager];
    }

    public static function defaultBase(string $group): float
    {
        return (float) settings()->get($group === self::WORKERS ? 'hr.advance_worker' : 'hr.advance_servant', $group === self::WORKERS ? 620 : 1000);
    }

    /**
     * The default period of a month: the 1st up to the configured day.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function defaultPeriod(CarbonImmutable $month): array
    {
        $day = max(1, min((int) settings()->get('hr.advance_until_day', 15), $month->daysInMonth));

        return [$month->startOfMonth(), $month->startOfMonth()->setDay($day)];
    }

    /**
     * Employees of a group in the employee register, in service and not on interim, by name. Users
     * who are not in the register are never part of the calculation.
     *
     * @return Collection<int, Employee>
     */
    public function employees(string $group): Collection
    {
        return Employee::query()
            ->employed()
            ->where(fn ($query) => $query->where('is_interim', false)->orWhereNull('is_interim'))
            ->whereIn('employment_category', array_map(fn (EmploymentCategory $category): string => $category->value, self::categories($group)))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @return array{total: float, vacation: float, vacation_counted: bool, family: float, unpaid: float, rva: float, worked: float, base: float, advance: float}
     */
    public function calculate(Employee $employee, CarbonImmutable $from, CarbonImmutable $until, float $base): array
    {
        $workdays = collect(CarbonPeriod::create($from, $until))
            ->map(fn ($day): CarbonImmutable => CarbonImmutable::instance($day))
            ->reject(fn (CarbonImmutable $day): bool => $day->isWeekend())
            ->map(fn (CarbonImmutable $day): string => $day->toDateString())
            ->values();

        $total = (float) $workdays->count();
        $excluded = ['vacation' => 0.0, 'family' => 0.0, 'unpaid' => 0.0, 'rva' => 0.0];

        if ($employee->user_id !== null && $total > 0) {
            // Public holidays and IR days always count as worked.
            $holidays = Holiday::query()->whereBetween('date', [$from->toDateString(), $until->toDateString()])->pluck('date')
                ->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())->all();
            $days = $workdays->reject(fn (string $day): bool => in_array($day, $holidays, true))->all();

            $add = function (string $kind, $start, $end, bool $halfDay) use (&$excluded, $days): void {
                foreach ($days as $day) {
                    if ($day >= CarbonImmutable::parse($start)->toDateString() && $day <= CarbonImmutable::parse($end)->toDateString()) {
                        $excluded[$kind] += $halfDay ? 0.5 : 1.0;
                    }
                }
            };

            VacationRequest::query()->vacation()->where('user_id', $employee->user_id)->where('status', VacationStatus::Approved)
                ->whereDate('start_date', '<=', $until->toDateString())->whereDate('end_date', '>=', $from->toDateString())->get()
                ->each(fn (VacationRequest $request) => $add('vacation', $request->start_date, $request->end_date, (bool) $request->half_day));

            // Not worked: family and unpaid leave, a work accident without its certificate (unpaid), and
            // what the RVA pays (economically unemployed, parental leave).
            Absence::query()->where('user_id', $employee->user_id)->whereIn('type', [AbsenceType::Family, AbsenceType::Unpaid, AbsenceType::WorkAccident, AbsenceType::Economic, AbsenceType::Parental])
                ->overlapping($from->toDateString(), $until->toDateString())->get()
                ->reject(fn (Absence $absence): bool => $absence->isPaidWorkAccident())
                ->each(fn (Absence $absence) => $add(match ($absence->type) {
                    AbsenceType::Family => 'family',
                    AbsenceType::Economic, AbsenceType::Parental => 'rva',
                    default => 'unpaid',
                }, $absence->start_date, $absence->end_date, (bool) $absence->half_day));
        }

        // One vacation day (or less) in the period counts as a day worked.
        $vacationCounted = $excluded['vacation'] > 0 && $excluded['vacation'] <= 1;
        $notWorked = ($vacationCounted ? 0 : $excluded['vacation']) + $excluded['family'] + $excluded['unpaid'] + $excluded['rva'];
        $worked = max(0.0, $total - $notWorked);

        return [
            'total' => $total,
            'vacation' => $excluded['vacation'],
            'vacation_counted' => $vacationCounted,
            'family' => $excluded['family'],
            'unpaid' => $excluded['unpaid'],
            'rva' => $excluded['rva'],
            'worked' => $worked,
            'base' => $base,
            'advance' => $total > 0 ? round($base * $worked / $total, 2) : 0.0,
        ];
    }
}
