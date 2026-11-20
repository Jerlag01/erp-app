<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A certificate an employee holds (BA4, VCA, driving licence, …) with its expiry date, calculated
 * from the date obtained and the validity of its type (none = does not expire), and a PDF of the certificate on the private disk.
 */
#[Fillable(['employee_id', 'type', 'categories', 'obtained_on', 'expires_on', 'document', 'document_name', 'notes'])]
class EmployeeCertificate extends Model
{
    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->lt(today());
    }

    public function expiresSoon(): bool
    {
        return $this->expires_on !== null && ! $this->isExpired() && $this->expires_on->lte(today()->addDays(Employee::EXPIRY_WARNING_DAYS));
    }

    /**
     * success / warning / danger, or gray when it does not expire.
     */
    public function expiryColor(): string
    {
        return match (true) {
            $this->expires_on === null => 'gray',
            $this->isExpired() => 'danger',
            $this->expiresSoon() => 'warning',
            default => 'success',
        };
    }

    /**
     * The type (managed list), by its code.
     */
    public function certificateType(): ?CertificateType
    {
        return CertificateType::forCode($this->type);
    }

    public function label(): string
    {
        $type = $this->certificateType();
        $label = $type?->getLabel() ?? (string) $this->type;

        return match (true) {
            (bool) $type?->isDrivingLicence() && filled($this->categories) => $label.' '.implode(', ', $this->categories),
            (bool) $type?->isSchoolDegree() && filled($this->notes) => $label.': '.$this->notes,
            default => $label,
        };
    }

    protected static function booted(): void
    {
        static::saving(function (self $certificate): void {
            // The expiry date follows from the type's validity and the date obtained (none: never expires).
            if ($type = $certificate->certificateType()) {
                $certificate->expires_on = $type->expiryFor($certificate->obtained_on);
            }
        });

        static::updated(function (self $certificate): void {
            if ($certificate->wasChanged('document') && filled($old = $certificate->getOriginal('document'))) {
                Storage::disk(Employee::DISK)->delete($old);
            }
        });

        static::deleted(function (self $certificate): void {
            if (filled($certificate->document)) {
                Storage::disk(Employee::DISK)->delete($certificate->document);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'obtained_on' => 'date',
            'expires_on' => 'date',
        ];
    }
}
