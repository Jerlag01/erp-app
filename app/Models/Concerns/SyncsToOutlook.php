<?php

namespace App\Models\Concerns;

use App\Models\OutlookEvent;
use App\Support\OutlookCalendar;

/**
 * Mirrors the model (an absence, holiday or training) in the Outlook calendars of the employees
 * concerned, after the response, as planning items are (see OutlookCalendar::syncEntry).
 */
trait SyncsToOutlook
{
    protected static function bootSyncsToOutlook(): void
    {
        static::saved(function (self $model): void {
            if (app(OutlookCalendar::class)->enabled()) {
                // After the response: the participants of a training are saved after the training itself.
                defer(fn () => app(OutlookCalendar::class)->syncEntry($model->fresh() ?? $model));
            }
        });

        static::deleted(function (self $model): void {
            $events = OutlookEvent::query()->whereMorphedTo('source', $model)->get();

            if ($events->isNotEmpty()) {
                defer(fn () => app(OutlookCalendar::class)->removeEntries($events));
            }
        });
    }
}
