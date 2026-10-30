<?php

namespace App\Models;

use App\Enums\VacationStatus;
use App\Enums\VacationType;
use App\Support\WorkingDays;
use Database\Factories\VacationRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type', 'start_date', 'end_date', 'half_day', 'days', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note', 'absence_id'])]
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

    /**
     * The overtime absence an approved overtime request became.
     *
     * @return BelongsTo<Absence, $this>
     */
    public function absence(): BelongsTo
    {
        return $this->belongsTo(Absence::class);
    }

    public function isOvertime(): bool
    {
        return $this->type === VacationType::Overtime;
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
     * Vacation only (not overtime taken as leave, which counts as an overtime absence once approved).
     *
     * @param  Builder<VacationRequest>  $query
     */
    public function scopeVacation(Builder $query): void
    {
        $query->where('vacation_requests.type', VacationType::Vacation);
    }

    /**
     * @param  Builder<VacationRequest>  $query
     */
    public function scopeOvertime(Builder $query): void
    {
        $query->where('vacation_requests.type', VacationType::Overtime);
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

        static::saved(fn (self $request) => $request->syncAbsence());

        static::deleted(fn (self $request) => $request->absence_id ? Absence::query()->find($request->absence_id)?->delete() : null);
    }

    /**
     * An approved request other than vacation is an absence of its kind: overtime (R), mobility (MV),
     * seniority (AN) or sollicitatieverlof (SV). When it is no longer approved (cancelled, rejected,
     * turned into vacation) the absence goes.
     */
    public function syncAbsence(): void
    {
        $type = $this->type?->absenceType();

        if ($type !== null && $this->status === VacationStatus::Approved) {
            $absence = ($this->absence_id ? Absence::query()->find($this->absence_id) : null) ?? new Absence(['user_id' => $this->user_id, 'type' => $type]);
            $absence->fill([
                'type' => $type,
                'user_id' => $this->user_id,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'half_day' => (bool) $this->half_day,
                'note' => $this->reason,
                'created_by' => $absence->created_by ?? $this->reviewed_by,
            ])->save();

            if ($this->absence_id !== $absence->id) {
                $this->absence_id = $absence->id;
                $this->saveQuietly();
            }

            $this->setRelation('absence', $absence);

            return;
        }

        if ($this->absence_id !== null) {
            $absence = Absence::query()->find($this->absence_id);
            $this->absence_id = null;
            $this->saveQuietly();
            $this->setRelation('absence', null);
            $absence?->delete();
        }
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
            'type' => VacationType::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
