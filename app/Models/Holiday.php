<?php

namespace App\Models;

use App\Support\WorkingDays;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A company-wide day off: a public holiday or an "inhaalrust" (IR) day, the same for all employees.
 * Neither is a working day, so they never count as vacation days and nothing can be planned on them.
 */
#[Fillable(['date', 'type', 'name'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    public const HOLIDAY = 'holiday';

    public const RECUPERATION = 'recuperation';

    public function isRecuperation(): bool
    {
        return $this->type === self::RECUPERATION;
    }

    /**
     * The name as shown in calendars, e.g. "IR · Brugdag".
     */
    public function label(): string
    {
        return $this->isRecuperation() ? trim('IR · '.$this->name, ' ·') : $this->name;
    }

    /**
     * @param  Builder<Holiday>  $query
     */
    public function scopePublicHolidays(Builder $query): void
    {
        $query->where('type', self::HOLIDAY);
    }

    /**
     * @param  Builder<Holiday>  $query
     */
    public function scopeRecuperationDays(Builder $query): void
    {
        $query->where('type', self::RECUPERATION);
    }

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
