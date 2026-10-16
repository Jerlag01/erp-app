<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CableStrippingLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One line of the cable stripping list. The calculated columns follow the Excel "Kabel ontmantelen":
 * - total hours = end − start;
 * - cable before stripping = weight before − empty bin;
 * - stripped cable = weight after − empty bin;
 * - waste = cable before − stripped cable.
 */
#[Fillable(['date', 'imputation', 'start_time', 'end_time', 'weight_before', 'empty_bin_weight', 'weight_after', 'bin_number', 'notes', 'created_by'])]
class CableStrippingLine extends Model
{
    /** @use HasFactory<CableStrippingLineFactory> */
    use HasFactory;

    /**
     * "Werknemer('s)".
     *
     * @return BelongsToMany<User, $this>
     */
    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cable_stripping_line_user');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hours(): ?float
    {
        if (blank($this->start_time) || blank($this->end_time)) {
            return null;
        }

        $start = CarbonImmutable::parse($this->start_time);
        $end = CarbonImmutable::parse($this->end_time);

        return round($start->diffInMinutes($end, false) / 60, 2);
    }

    public function cableBefore(): ?float
    {
        return $this->difference($this->weight_before, $this->empty_bin_weight);
    }

    public function strippedCable(): ?float
    {
        return $this->difference($this->weight_after, $this->empty_bin_weight);
    }

    public function waste(): ?float
    {
        $before = $this->cableBefore();
        $stripped = $this->strippedCable();

        return $before === null || $stripped === null ? null : round($before - $stripped, 2);
    }

    public function workerNames(): string
    {
        return $this->workers->pluck('name')->implode(', ');
    }

    protected function difference(mixed $from, mixed $minus): ?float
    {
        return $from === null || $minus === null ? null : round((float) $from - (float) $minus, 2);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'weight_before' => 'decimal:2',
            'empty_bin_weight' => 'decimal:2',
            'weight_after' => 'decimal:2',
        ];
    }
}
