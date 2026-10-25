<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The badge (7 digits) and electronic key (4 digits) of one employee, with whether they have CLAD
 * permission and which clearance. An employee who left shows as deactivated.
 */
#[Fillable(['employee_id', 'badge_number', 'key_number', 'has_clad', 'clearance'])]
class AccessBadge extends Model
{
    public const BADGE_PATTERN = '/^\d{7}$/';

    public const KEY_PATTERN = '/^\d{4}$/';

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
     * @param  Builder<AccessBadge>  $query
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
        return ['has_clad' => 'boolean'];
    }
}
