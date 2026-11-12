<?php

namespace App\Models;

use Database\Factories\OrderReferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * A reference taken after the order was placed: the orders that mention it get linked.
     */
    protected static function booted(): void
    {
        static::created(function (self $reference): void {
            DistributorOrder::query()
                ->whereNull('order_reference_id')
                ->where(fn ($query) => $query->where('customer_reference', 'like', '%'.$reference->number.'%')->orWhere('notes', 'like', '%'.$reference->number.'%'))
                ->get()
                ->each(function (DistributorOrder $order): void {
                    if ($order->linkReference()) {
                        $order->save();
                    }
                });
        });
    }

    /**
     * @return HasMany<DistributorOrder, $this>
     */
    public function distributorOrders(): HasMany
    {
        return $this->hasMany(DistributorOrder::class);
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
