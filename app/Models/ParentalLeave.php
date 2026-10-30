<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Parental leave enabled by administration for one employee (RVA, "ouderschapsverlof"): for a child under
 * 12 at the start, in one of four forms, each with its longest duration, and with the certificate. Within
 * the period administration enters the OV days, which count as not worked (paid by the RVA).
 */
#[Fillable(['employee_id', 'child_name', 'child_birth_date', 'form', 'starts_on', 'ends_on', 'document', 'document_name', 'notes', 'created_by'])]
class ParentalLeave extends Model
{
    public const DISK = 'local';

    /** Form => the longest duration in months. */
    public const FORMS = ['full' => 4, 'half' => 8, 'fifth' => 20, 'tenth' => 40];

    /** The child may not be 12 yet when the leave starts. */
    public const MAX_CHILD_AGE = 12;

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<Absence, $this>
     */
    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    public function label(): string
    {
        return __("erp.parental_leave.forms.{$this->form}").' · '.$this->child_name.' · '.$this->starts_on->format('d/m/Y').' – '.$this->ends_on->format('d/m/Y');
    }

    public function covers(string $from, string $until): bool
    {
        return $this->starts_on->toDateString() <= $from && $this->ends_on->toDateString() >= $until;
    }

    /**
     * Why the leave is not possible, or null: a child of 12 or older at the start, or longer than the form allows.
     */
    public static function problem(string $form, string $birthDate, string $from, string $until): ?string
    {
        $start = Carbon::parse($from);

        if (Carbon::parse($birthDate)->addYears(self::MAX_CHILD_AGE)->lte($start)) {
            return __('erp.parental_leave.errors.child_age');
        }

        if (Carbon::parse($until)->gt($start->copy()->addMonthsNoOverflow(self::FORMS[$form] ?? 0)->subDay())) {
            return __('erp.parental_leave.errors.too_long', ['months' => self::FORMS[$form] ?? 0]);
        }

        return null;
    }

    protected static function booted(): void
    {
        static::deleted(fn (self $leave) => filled($leave->document) ? Storage::disk(self::DISK)->delete($leave->document) : null);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'child_birth_date' => 'date',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
