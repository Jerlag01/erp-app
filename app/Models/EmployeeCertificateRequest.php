<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A certificate an employee added themselves. On approval it becomes (or renews) the certificate of
 * that type in the employee register.
 */
#[Fillable([
    'employee_id', 'submitted_by', 'type', 'categories', 'obtained_on', 'expires_on', 'document', 'document_name', 'notes',
    'status', 'handled_by', 'handled_at', 'rejection_reason', 'employee_certificate_id',
])]
class EmployeeCertificateRequest extends Model
{
    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isPending(): bool
    {
        return $this->status === RequestStatus::Pending;
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

    /**
     * Adds the certificate to the register, or renews the one of the same type (its old PDF is replaced).
     */
    public function approve(User $by): EmployeeCertificate
    {
        return DB::transaction(function () use ($by): EmployeeCertificate {
            $certificate = $this->employee->certificates()->firstOrNew(['type' => $this->type]);
            $certificate->fill([
                'categories' => $this->categories,
                'obtained_on' => $this->obtained_on,
                'notes' => $this->notes,
                ...(filled($this->document) ? ['document' => $this->document, 'document_name' => $this->document_name] : []),
            ])->save();

            $this->update([
                'status' => RequestStatus::Approved,
                'handled_by' => $by->id,
                'handled_at' => now(),
                'employee_certificate_id' => $certificate->id,
            ]);

            return $certificate;
        });
    }

    public function reject(?string $reason, User $by): void
    {
        if (filled($this->document)) {
            Storage::disk(Employee::DISK)->delete($this->document);
        }

        $this->update([
            'status' => RequestStatus::Rejected,
            'rejection_reason' => $reason,
            'document' => null,
            'handled_by' => $by->id,
            'handled_at' => now(),
        ]);
    }

    protected static function booted(): void
    {
        // Shown to whoever approves it: the expiry date that will follow from the type's validity.
        static::saving(function (self $request): void {
            if ($type = $request->certificateType()) {
                $request->expires_on = $type->expiryFor($request->obtained_on);
            }
        });

        // A withdrawn (deleted) pending request takes its PDF along; an approved one handed it to the certificate.
        static::deleted(function (self $request): void {
            if (filled($request->document) && ! $request->employee_certificate_id) {
                Storage::disk(Employee::DISK)->delete($request->document);
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
            'status' => RequestStatus::class,
            'handled_at' => 'datetime',
        ];
    }
}
