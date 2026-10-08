<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The ten legal public holidays in Belgium, as a starting point that admins can adjust freely.
 */
class BelgianHolidays
{
    /**
     * @return array<string, string> date => name (in the current locale)
     */
    public static function forYear(int $year): array
    {
        $easter = Carbon::create($year, 3, 21)->addDays(easter_days($year));

        $dates = [
            'new_year' => Carbon::create($year, 1, 1),
            'easter_monday' => $easter->copy()->addDay(),
            'labour_day' => Carbon::create($year, 5, 1),
            'ascension' => $easter->copy()->addDays(39),
            'whit_monday' => $easter->copy()->addDays(50),
            'national_day' => Carbon::create($year, 7, 21),
            'assumption' => Carbon::create($year, 8, 15),
            'all_saints' => Carbon::create($year, 11, 1),
            'armistice' => Carbon::create($year, 11, 11),
            'christmas' => Carbon::create($year, 12, 25),
        ];

        return collect($dates)
            ->mapWithKeys(fn (Carbon $date, string $key): array => [$date->toDateString() => __("erp.holidays.names.{$key}")])
            ->all();
    }
}
