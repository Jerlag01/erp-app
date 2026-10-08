<?php

namespace App\Models;

use App\Support\WorkingDays;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A public holiday: not a working day, so it never counts as a vacation day.
 */
#[Fillable(['date', 'name'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    /**
     * Adding, moving or removing a holiday changes how many days the requests around it take.
     */
    protected static function booted(): void
    {
        static::saved(function (self $holiday): void {
            $dates = array_filter([$holiday->date?->toDateString(), $holiday->getOriginal('date')?->toDateString()]);
            static::recountRequestsOn($dates);
        });

        static::deleted(fn (self $holiday) => static::recountRequestsOn([$holiday->date->toDateString()]));
    }

    /**
     * @param  array<int, string>  $dates
     */
    public static function recountRequestsOn(array $dates): void
    {
        foreach (array_unique($dates) as $date) {
            VacationRequest::query()
                ->overlapping($date, $date)
                ->each(function (VacationRequest $request): void {
                    $request->days = WorkingDays::count($request->start_date, $request->end_date, $request->half_day);
                    $request->saveQuietly();
                });
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
