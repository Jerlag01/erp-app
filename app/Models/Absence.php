<?php

namespace App\Models;

use App\Enums\AbsenceType;
use App\Support\WorkingDays;
use Database\Factories\AbsenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * An absence recorded by an administrator (medical, overtime as paid leave, family leave).
 * The certificate is uploaded by the administrator or, while there is none from the
 * administrator, by the employee; an administrator's certificate can only be downloaded.
 */
#[Fillable(['user_id', 'type', 'start_date', 'end_date', 'half_day', 'days', 'note', 'document', 'document_name', 'document_source', 'document_uploaded_at', 'created_by'])]
class Absence extends Model
{
    /** @use HasFactory<AbsenceFactory> */
    use HasFactory;

    public const DISK = 'local';

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_EMPLOYEE = 'employee';

    /**
     * Any picture type and PDF.
     */
    public const DOCUMENT_TYPES = ['image/*', 'application/pdf'];

    public const DOCUMENT_MAX_KILOBYTES = 12288;

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

    public function hasDocument(): bool
    {
        return filled($this->document);
    }

    /**
     * The employee may upload (or replace their own) certificate as long as the administrator has not.
     */
    public function employeeMayUpload(): bool
    {
        return $this->document_source !== self::SOURCE_ADMIN;
    }

    public static function directoryFor(int $userId): string
    {
        return "absences/{$userId}";
    }

    /**
     * Sets a newly uploaded certificate and removes the previous file.
     */
    public function attachDocument(string $path, ?string $originalName, string $source): void
    {
        $previous = $this->document;

        $this->update([
            'document' => $path,
            'document_name' => $originalName ?: basename($path),
            'document_source' => $source,
            'document_uploaded_at' => now(),
        ]);

        if (filled($previous) && $previous !== $path) {
            Storage::disk(self::DISK)->delete($previous);
        }
    }

    /**
     * The same people as for vacation: the user and their team members, or everyone for administrators.
     *
     * @param  Builder<Absence>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesOnlyOwnTeams()) {
            $query->where(fn (Builder $query) => $query
                ->where('absences.user_id', $user->id)
                ->orWhereHas('user.teams', fn (Builder $query) => $query->whereIn('teams.id', $user->teamIds())));
        }
    }

    /**
     * @param  Builder<Absence>  $query
     */
    public function scopeOverlapping(Builder $query, string $start, string $end): void
    {
        $query->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start);
    }

    /**
     * What a viewer sees as the reason: the type for the person and administrators, and for
     * colleagues only "absent" when the reason is private.
     */
    public function labelFor(User $viewer): string
    {
        return $this->type->isPrivate() && $viewer->id !== $this->user_id && ! $viewer->isAdmin()
            ? __('erp.absences.absent')
            : $this->type->getLabel();
    }

    protected static function booted(): void
    {
        static::saving(function (self $absence): void {
            if ($absence->isDirty(['start_date', 'end_date', 'half_day']) || ! $absence->exists) {
                $absence->days = WorkingDays::count($absence->start_date, $absence->end_date, (bool) $absence->half_day);
            }

            if (blank($absence->document)) {
                $absence->document_name = null;
                $absence->document_source = null;
                $absence->document_uploaded_at = null;
            }
        });

        static::updated(function (self $absence): void {
            if ($absence->wasChanged('document') && filled($old = $absence->getOriginal('document'))) {
                Storage::disk(self::DISK)->delete($old);
            }
        });

        static::deleted(function (self $absence): void {
            if (filled($absence->document)) {
                Storage::disk(self::DISK)->delete($absence->document);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AbsenceType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'half_day' => 'boolean',
            'days' => 'decimal:1',
            'document_uploaded_at' => 'datetime',
        ];
    }
}
