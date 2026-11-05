<?php

namespace App\Support;

use App\Enums\VacationStatus;
use App\Models\User;
use App\Models\VacationRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * Whether planning notifications to an employee (phone push, e-mail) wait: while they are on leave (an approved leave request
 * for whole days) or have a day off (public holiday, IR day), they are held until the last day of
 * that time off, at DELIVER_AT, so they know their planning before they are back. Weekends between or
 * right after days off belong to the time off (leave until Friday: delivered on Sunday).
 */
class LeavePushDelay
{
    /** The time on the last day of the leave at which held notifications are sent. */
    public const DELIVER_AT = '18:00';

    /** How far ahead the end of a leave is looked for. */
    protected const HORIZON_DAYS = 120;

    /**
     * When the employee's planning notifications may be sent, or null when that is now (not on leave, or the
     * last day of the leave is already past DELIVER_AT).
     */
    public function until(User $employee, ?CarbonInterface $now = null): ?CarbonImmutable
    {
        $now = CarbonImmutable::instance($now ?? now());
        $today = $now->startOfDay();
        $off = $this->daysOff($employee, $today->subDays(3), $today->addDays(self::HORIZON_DAYS));
        $away = fn (CarbonImmutable $day): bool => isset($off[$day->toDateString()]);

        if (! $this->isAwayOn($today, $away)) {
            return null;
        }

        // The last day of the time off: the day before the first working day that is not off.
        $last = $today;
        while ($last->lt($today->addDays(self::HORIZON_DAYS)) && ($away($last->addDay()) || $last->addDay()->isWeekend())) {
            $last = $last->addDay();
        }

        $deliver = $last->setTimeFromTimeString(self::DELIVER_AT);

        return $deliver->gt($now) ? $deliver : null;
    }

    /**
     * Off that day, or a weekend day joined (through weekend days only) to a day off.
     *
     * @param  callable(CarbonImmutable): bool  $away
     */
    protected function isAwayOn(CarbonImmutable $day, callable $away): bool
    {
        if ($away($day)) {
            return true;
        }

        if (! $day->isWeekend()) {
            return false;
        }

        foreach ([-1, 1] as $step) {
            $next = $day->addDays($step);
            while ($next->isWeekend() && ! $away($next)) {
                $next = $next->addDays($step);
            }
            if ($away($next)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whole days of approved leave, public holidays and IR days, as [Y-m-d => true].
     *
     * @return array<string, true>
     */
    protected function daysOff(User $employee, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $days = array_fill_keys(RecuperationDays::daysOff($employee->id, $from, $until), true);

        if (modules()->vacations()) {
            VacationRequest::query()
                ->where('user_id', $employee->id)
                ->where('status', VacationStatus::Approved)
                ->where('half_day', false)
                ->whereDate('start_date', '<=', $until->toDateString())
                ->whereDate('end_date', '>=', $from->toDateString())
                ->get()
                ->each(function (VacationRequest $request) use (&$days): void {
                    foreach (CarbonPeriod::create($request->start_date, $request->end_date) as $day) {
                        $days[$day->toDateString()] = true;
                    }
                });
        }

        return $days;
    }
}
