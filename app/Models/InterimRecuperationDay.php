<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutlook;
use App\Support\OutlookCalendar;
use App\Support\WorkingDays;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An IR day (inhaalrust) or public holiday adjusted for one interim: an IR day of their own ("is_day_off"),
 * or a company IR day or public holiday taken away so they work that day. See App\Support\RecuperationDays.
 */
#[Fillable(['user_id', 'date', 'is_day_off', 'name', 'created_by'])]
class InterimRecuperationDay extends Model
{
    use SyncsToOutlook;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The name as shown in calendars, e.g. "IR · Brugdag".
     */
    public function label(): string
    {
        return trim('IR · '.($this->name ?: __('erp.recuperation_days.default_name')), ' ·');
    }

    /**
     * The company IR day or public holiday on the same date, whose Outlook events and day counts follow this change.
     */
    public function companyDay(): ?Holiday
    {
        return Holiday::query()->whereDate('date', $this->date)->first();
    }

    protected static function booted(): void
    {
        $changed = function (self $day, array $dates): void {
            // The leave and absences of this interim on those dates take a day more or less.
            foreach ($dates as $date) {
                VacationRequest::query()->where('user_id', $day->user_id)->overlapping($date, $date)->get()->toBase()
                    ->concat(Absence::query()->where('user_id', $day->user_id)->overlapping($date, $date)->get())
                    ->each(function (VacationRequest|Absence $entry): void {
                        $entry->days = WorkingDays::count($entry->start_date, $entry->end_date, (bool) $entry->half_day, $entry->user_id);
                        $entry->saveQuietly();
                    });
            }

            $companyDays = Holiday::query()->whereIn('date', $dates)->get();

            if ($companyDays->isNotEmpty() && app(OutlookCalendar::class)->enabled()) {
                defer(fn () => $companyDays->each(fn (Holiday $holiday) => app(OutlookCalendar::class)->syncEntry($holiday)));
            }
        };

        static::saved(fn (self $day) => $changed($day, array_values(array_unique(array_filter([$day->date?->toDateString(), $day->getOriginal('date')?->toDateString()])))));
        static::deleted(fn (self $day) => $changed($day, [$day->date->toDateString()]));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_day_off' => 'boolean',
        ];
    }
}
