<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A week of the prestation sheet: the licence plate at the time, the kilometres and overtime carried
 * over ("Report", set by an administrator or taken from the previous week), the expected hours,
 * remarks, and when the week PDF was sent (after which the employee can no longer change it).
 */
#[Fillable(['user_id', 'week_start', 'plate_number', 'km_carried', 'overtime_carried', 'expected_hours', 'remarks', 'sent_at', 'pdf_path'])]
class TimesheetWeek extends Model
{
    public const DISK = 'local';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'km_carried' => 'integer',
            'overtime_carried' => 'decimal:2',
            'expected_hours' => 'decimal:2',
            'sent_at' => 'datetime',
        ];
    }
}
