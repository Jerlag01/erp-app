<?php

namespace App\Models;

use Database\Factories\OrderReferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An internal order reference such as "466/1026/JL": a yearly sequence number,
 * the month and year it was taken in, and the initials of whoever took it.
 */
#[Fillable(['year', 'number', 'month', 'initials', 'reference', 'user_id', 'supplier', 'project', 'description'])]
class OrderReference extends Model
{
    /** @use HasFactory<OrderReferenceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function format(int $number, int $month, int $year, string $initials): string
    {
        return sprintf('%03d/%02d%02d/%s', $number, $month, $year % 100, $initials);
    }

    /**
     * Whether this is the most recent reference of its year (only that one can be withdrawn).
     */
    public function isLatestOfYear(): bool
    {
        return ! static::query()->where('year', $this->year)->where('number', '>', $this->number)->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'number' => 'integer',
            'month' => 'integer',
        ];
    }
}
