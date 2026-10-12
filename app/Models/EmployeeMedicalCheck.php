<?php

namespace App\Models;

use App\Enums\MedicalResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A visit to the occupational doctor. The next one is due a year later unless the doctor set
 * another date. A check-up can hold several files (the evaluation form, attestations…).
 */
#[Fillable(['employee_id', 'checked_on', 'result', 'next_due_on', 'doctor', 'remarks', 'documents', 'document_names'])]
class EmployeeMedicalCheck extends Model
{
    public const FILE_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

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
            if ($check->wasChanged('documents')) {
                $removed = array_diff((array) json_decode((string) $check->getRawOriginal('documents'), true), $check->documents ?? []);
                Storage::disk(Employee::DISK)->delete(array_values($removed));
            }
        });

        static::deleted(function (self $check): void {
            Storage::disk(Employee::DISK)->delete($check->documents ?? []);
        });
    }

    /**
     * The original file name of a stored file.
     */
    public function documentName(string $path): string
    {
        return (string) (($this->document_names ?? [])[$path] ?? basename($path));
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
            'documents' => 'array',
            'document_names' => 'array',
        ];
    }
}
