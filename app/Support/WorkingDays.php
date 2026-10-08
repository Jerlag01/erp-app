<?php

namespace App\Support;

use App\Models\Holiday;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

/**
 * Counts vacation days: weekdays in a period, minus public holidays.
 */
class WorkingDays
{
    public static function count(CarbonInterface|string|null $start, CarbonInterface|string|null $end, bool $halfDay = false): float
    {
        if ($start === null || $end === null) {
            return 0.0;
        }

        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();

        if ($end->lt($start)) {
            return 0.0;
        }

        $holidays = Holiday::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn (CarbonInterface $date): string => $date->toDateString())
            ->all();

        $days = 0;

        foreach (CarbonPeriod::create($start, $end) as $day) {
            if (! $day->isWeekend() && ! in_array($day->toDateString(), $holidays, true)) {
                $days++;
            }
        }

        // A half day only exists for a single-day request.
        return $halfDay && $days === 1 && $start->equalTo($end) ? 0.5 : (float) $days;
    }
}
