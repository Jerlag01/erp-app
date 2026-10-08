<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Classifies a control or inspection date as overdue, due soon or fine.
 */
class DueDate
{
    public static function status(?CarbonInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        if ($date->isBefore(today())) {
            return 'overdue';
        }

        return $date->lte(today()->addDays(settings()->warningDays())) ? 'soon' : 'ok';
    }

    public static function color(?CarbonInterface $date): string
    {
        return match (static::status($date)) {
            'overdue' => 'danger',
            'soon' => 'warning',
            'ok' => 'success',
            default => 'gray',
        };
    }

    public static function description(?CarbonInterface $date): ?string
    {
        return match (static::status($date)) {
            'overdue' => __('erp.due.overdue', ['days' => (int) $date->diffInDays(today())]),
            'soon' => __('erp.due.soon', ['days' => (int) today()->diffInDays($date)]),
            default => null,
        };
    }
}
