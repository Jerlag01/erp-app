<?php

namespace App\Models;

use App\Enums\RequestStatus;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Changes an employee made to their own personal details. They only reach the employee register once
 * an administrator approves them. An employee has at most one pending request; editing again replaces it.
 */
#[Fillable(['employee_id', 'submitted_by', 'new_values', 'old_values', 'status', 'handled_by', 'handled_at', 'rejection_reason'])]
class EmployeeChangeRequest extends Model
{
    /**
     * The fields employees may change themselves.
     */
    public const FIELDS = [
        'first_name', 'last_name', 'street', 'house_number', 'addition', 'postal_code', 'city', 'country_id',
        'private_email', 'private_phone', 'national_number', 'bank_account', 'place_of_birth', 'date_of_birth',
        'mother_tongue', 'education_level', 'size_pants', 'size_shirt', 'size_sweater', 'size_shoes',
        'emergency1_first_name', 'emergency1_last_name', 'emergency1_phone', 'emergency1_relation',
        'emergency2_first_name', 'emergency2_last_name', 'emergency2_phone', 'emergency2_relation',
    ];

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
     * A value as it is compared and stored: dates as Y-m-d, enums as their value, national number
     * and IBAN without formatting, empty as null.
     */
    public static function normalize(string $field, mixed $value): mixed
    {
        $value = match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_string($value) => trim($value),
            default => $value,
        };

        if ($value === '' || $value === null) {
            return null;
        }

        return match ($field) {
            'national_number' => preg_replace('/\D/', '', (string) $value),
            'bank_account' => strtoupper((string) preg_replace('/\s+/', '', (string) $value)),
            'country_id' => (int) $value,
            'date_of_birth' => substr((string) $value, 0, 10),
            default => is_scalar($value) ? (string) $value : $value,
        };
    }

    /**
     * The fields that differ from the register: field => [current, new].
     *
     * @param  array<string, mixed>  $data  form data of the employee
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function diff(Employee $employee, array $data): array
    {
        $diff = [];

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $current = self::normalize($field, $employee->{$field});
            $new = self::normalize($field, $data[$field]);

            if ($current !== $new) {
                $diff[$field] = [$current, $new];
            }
        }

        return $diff;
    }

    /**
     * Applies the changes to the employee register.
     */
    public function approve(User $by): void
    {
        $this->employee->update(array_intersect_key($this->new_values, array_flip(self::FIELDS)));

        $this->update(['status' => RequestStatus::Approved, 'handled_by' => $by->id, 'handled_at' => now()]);
    }

    public function reject(?string $reason, User $by): void
    {
        $this->update(['status' => RequestStatus::Rejected, 'rejection_reason' => $reason, 'handled_by' => $by->id, 'handled_at' => now()]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'new_values' => 'encrypted:array',
            'old_values' => 'encrypted:array',
            'status' => RequestStatus::class,
            'handled_at' => 'datetime',
        ];
    }
}
