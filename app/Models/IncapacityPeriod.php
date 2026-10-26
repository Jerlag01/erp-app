<?php

namespace App\Models;

use App\Enums\EmploymentCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A period with the statute "arbeidsongeschikt": opened when the statute is set, closed with the last
 * day of sickness when it changes back. Every working day in it is sickness (Z) on the prestation sheet.
 */
#[Fillable(['employee_id', 'start_date', 'end_date', 'previous_category', 'next_category', 'started_by', 'ended_by'])]
class IncapacityPeriod extends Model
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
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function ender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function isOpen(): bool
    {
        return $this->end_date === null;
    }

    /**
     * @param  Builder<IncapacityPeriod>  $query
     */
    public function scopeOverlapping(Builder $query, string $from, string $until): void
    {
        $query->whereDate('start_date', '<=', $until)->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $from));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'previous_category' => EmploymentCategory::class,
            'next_category' => EmploymentCategory::class,
        ];
    }
}
