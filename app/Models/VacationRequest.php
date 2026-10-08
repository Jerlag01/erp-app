<?php

namespace App\Models;

use App\Enums\VacationStatus;
use App\Support\WorkingDays;
use Database\Factories\VacationRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'start_date', 'end_date', 'half_day', 'days', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note'])]
class VacationRequest extends Model
{
    /** @use HasFactory<VacationRequestFactory> */
    use HasFactory;

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
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === VacationStatus::Pending;
    }

    /**
     * The requester may withdraw a request that is still pending, or an approved one that has not started.
     */
    public function canBeCancelledBy(User $user): bool
    {
        return $this->user_id === $user->id
            && ($this->isPending() || ($this->status === VacationStatus::Approved && $this->start_date->isFuture()));
    }

    public function approve(User $reviewer, ?string $note = null): void
    {
        $this->review(VacationStatus::Approved, $reviewer, $note);
    }

    public function reject(User $reviewer, ?string $note = null): void
    {
        $this->review(VacationStatus::Rejected, $reviewer, $note);
    }

    protected function review(VacationStatus $status, User $reviewer, ?string $note): void
    {
        $this->update([
            'status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }

    /**
     * Requests of the user's team members (and their own), or all of them for administrators.
     *
     * @param  Builder<VacationRequest>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesOnlyOwnTeams()) {
            $query->where(fn (Builder $query) => $query
                ->where('vacation_requests.user_id', $user->id)
                ->orWhereHas('user.teams', fn (Builder $query) => $query->whereIn('teams.id', $user->teamIds())));
        }
    }

    /**
     * Requests that overlap a period and still count (pending or approved).
     *
     * @param  Builder<VacationRequest>  $query
     */
    public function scopeOverlapping(Builder $query, string $start, string $end): void
    {
        $query->whereIn('status', [VacationStatus::Pending, VacationStatus::Approved])
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start);
    }

    /**
     * The number of days is always derived from the period, never taken from the form.
     */
    protected static function booted(): void
    {
        static::saving(function (self $request): void {
            if ($request->isDirty(['start_date', 'end_date', 'half_day']) || $request->days === null) {
                $request->days = WorkingDays::count($request->start_date, $request->end_date, (bool) $request->half_day);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'half_day' => 'boolean',
            'days' => 'decimal:1',
            'status' => VacationStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
