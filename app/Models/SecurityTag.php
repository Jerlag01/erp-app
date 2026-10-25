<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The security system tag (2 digits) of one employee: the name set in the system, access to the
 * warehouse and the office, and whether they received keys for the doors.
 */
#[Fillable(['employee_id', 'tag_number', 'system_name', 'access_warehouse', 'access_office', 'door_keys'])]
class SecurityTag extends Model
{
    public const TAG_PATTERN = '/^\d{2}$/';

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isActive(): bool
    {
        return (bool) $this->employee?->isEmployed();
    }

    /**
     * @param  Builder<SecurityTag>  $query
     */
    public function scopeActive(Builder $query, bool $active = true): void
    {
        $query->whereHas('employee', fn (Builder $query) => $active ? $query->whereNull('left_on') : $query->whereNotNull('left_on'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_warehouse' => 'boolean',
            'access_office' => 'boolean',
            'door_keys' => 'boolean',
        ];
    }
}
