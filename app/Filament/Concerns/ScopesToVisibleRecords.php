<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Limits a resource to the records the signed-in user may see (their own teams' records
 * for non-administrators), so other teams' records are neither listed nor reachable by URL.
 */
trait ScopesToVisibleRecords
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return ($user = auth()->user()) ? $query->visibleTo($user) : $query->whereRaw('1 = 0');
    }
}
