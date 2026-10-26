<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A security system tag (2 digits): the name set in the system, access to the warehouse and the
 * office, and whether door keys were handed over. It belongs to an employee of the register, to a user
 * with a login who is not in the register, or to nobody (in stock); a lost tag is marked so.
 */
#[Fillable(['employee_id', 'user_id', 'tag_number', 'system_name', 'access_warehouse', 'access_office', 'door_keys', 'is_lost', 'lost_at'])]
class SecurityTag extends Model
{
    public const TAG_PATTERN = '/^\d{2}$/';

    public const HOLDER_EMPLOYEE = 'employee';

    public const HOLDER_USER = 'user';

    public const HOLDER_NONE = 'none';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DEACTIVATED = 'deactivated';

    public const STATUS_UNASSIGNED = 'unassigned';

    public const STATUS_LOST = 'lost';

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function holderType(): string
    {
        return match (true) {
            $this->employee_id !== null => self::HOLDER_EMPLOYEE,
            $this->user_id !== null => self::HOLDER_USER,
            default => self::HOLDER_NONE,
        };
    }

    /**
     * The employee's full name, the user's name, or null for a tag that is not assigned.
     */
    public function holderName(): ?string
    {
        return $this->employee?->fullName() ?? $this->user?->name;
    }

    /**
     * Lost first; then unassigned; then active while the employee still works here (or the user's
     * login is on), else deactivated.
     */
    public function status(): string
    {
        return match (true) {
            $this->is_lost => self::STATUS_LOST,
            $this->holderType() === self::HOLDER_NONE => self::STATUS_UNASSIGNED,
            $this->employee_id !== null => $this->employee?->isEmployed() ? self::STATUS_ACTIVE : self::STATUS_DEACTIVATED,
            default => $this->user?->is_active ? self::STATUS_ACTIVE : self::STATUS_DEACTIVATED,
        };
    }

    public function isActive(): bool
    {
        return $this->status() === self::STATUS_ACTIVE;
    }

    /**
     * Assigned, not lost, and the holder is still here (or, with false, no longer here).
     *
     * @param  Builder<SecurityTag>  $query
     */
    public function scopeActive(Builder $query, bool $active = true): void
    {
        $query->where('is_lost', false)->where(fn (Builder $query) => $query
            ->whereHas('employee', fn (Builder $query) => $active ? $query->whereNull('left_on') : $query->whereNotNull('left_on'))
            ->orWhere(fn (Builder $query) => $query->whereNull('employee_id')->whereHas('user', fn (Builder $query) => $query->where('is_active', $active))));
    }

    /**
     * @param  Builder<SecurityTag>  $query
     */
    public function scopeUnassigned(Builder $query): void
    {
        $query->where('is_lost', false)->whereNull('employee_id')->whereNull('user_id');
    }

    /**
     * @param  Builder<SecurityTag>  $query
     */
    public function scopeLost(Builder $query): void
    {
        $query->where('is_lost', true);
    }

    protected static function booted(): void
    {
        static::saving(function (self $tag): void {
            // One holder at most, and the moment it was reported lost.
            if ($tag->employee_id !== null) {
                $tag->user_id = null;
            }

            if ($tag->isDirty('is_lost')) {
                $tag->lost_at = $tag->is_lost ? ($tag->lost_at ?? now()) : null;
            }
        });
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
            'is_lost' => 'boolean',
            'lost_at' => 'datetime',
        ];
    }
}
