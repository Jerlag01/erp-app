<?php

namespace App\Models;

use App\Enums\MedicalResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A visit to the occupational doctor. The next one is due a year later unless the doctor set
 * another date.
 */
#[Fillable(['employee_id', 'checked_on', 'result', 'next_due_on', 'doctor', 'remarks', 'document', 'document_name'])]
class EmployeeMedicalCheck extends Model
{
    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $check): void {
            $check->next_due_on ??= $check->checked_on?->copy()->addMonths(Employee::MEDICAL_INTERVAL_MONTHS);
        });

        static::updated(function (self $check): void {
            if ($check->wasChanged('document') && filled($old = $check->getOriginal('document'))) {
                Storage::disk(Employee::DISK)->delete($old);
            }
        });

        static::deleted(function (self $check): void {
            if (filled($check->document)) {
                Storage::disk(Employee::DISK)->delete($check->document);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_on' => 'date',
            'next_due_on' => 'date',
            'result' => MedicalResult::class,
        ];
    }
}
