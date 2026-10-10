<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What an employee filled in for one working day of the prestation sheet. The rows hold odometer
 * readings and times; hours and kilometres are calculated (see App\Support\Timesheets).
 */
#[Fillable(['user_id', 'date', 'lunch_break', 'rows', 'remarks', 'updated_by'])]
class TimesheetDay extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'lunch_break' => 'boolean',
            'rows' => 'array',
        ];
    }
}
