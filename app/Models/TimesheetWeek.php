<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A week of the prestation sheet: the licence plate at the time, the kilometres and overtime carried
 * over ("Report", set by an administrator or taken from the previous week), the expected hours,
 * remarks, and when the week PDF was sent (after which the employee can no longer change it). An interim's
 * week waits for its interim office (interim_office_id) until it is verified; then it goes to the office.
 */
#[Fillable(['user_id', 'week_start', 'plate_number', 'km_carried', 'overtime_carried', 'expected_hours', 'remarks', 'sent_at', 'pdf_path', 'interim_office_id', 'interim_verified_at', 'interim_verified_by', 'interim_sent_at'])]
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

    /**
     * @return BelongsTo<InterimOffice, $this>
     */
    public function interimOffice(): BelongsTo
    {
        return $this->belongsTo(InterimOffice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function interimVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interim_verified_by');
    }

    /**
     * Interim weeks still to be verified before they go to the interim office.
     *
     * @param  Builder<TimesheetWeek>  $query
     */
    public function scopeAwaitingInterimVerification(Builder $query): void
    {
        $query->whereNotNull('interim_office_id')->whereNull('interim_verified_at');
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
            'interim_verified_at' => 'datetime',
            'interim_sent_at' => 'datetime',
            'km_carried' => 'integer',
            'overtime_carried' => 'decimal:2',
            'expected_hours' => 'decimal:2',
            'sent_at' => 'datetime',
        ];
    }
}
