<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * How much of one item is in stock at one location.
 */
#[Fillable(['stock_item_id', 'location_id', 'quantity', 'min_quantity'])]
class StockLevel extends Model
{
    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('created_at')->latest('id');
    }

    /**
     * The quantity at or below which this row counts as low stock: the location's own minimum
     * when one is set, otherwise the item's low stock threshold. Null means never flagged.
     */
    public function threshold(): ?string
    {
        return $this->min_quantity ?? $this->item?->low_stock_threshold;
    }

    /**
     * Low stock is flagged on reaching the threshold, before the stock runs out.
     */
    public function isLow(): bool
    {
        $threshold = $this->threshold();

        return $threshold !== null && (float) $this->quantity <= (float) $threshold;
    }

    /**
     * Stock rows at the locations of the user's teams, or everywhere for administrators.
     * Without the locations module nothing links people to locations, so nothing is limited.
     *
     * @param  Builder<StockLevel>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesOnlyOwnTeams() && modules()->locations()) {
            $query->whereIn('stock_levels.location_id', $user->locationIds());
        }
    }

    /**
     * Rows at or below their threshold (see threshold()), as a query.
     *
     * @param  Builder<StockLevel>  $query
     */
    public function scopeLow(Builder $query): void
    {
        $query->whereRaw('stock_levels.quantity <= COALESCE(stock_levels.min_quantity, (select stock_items.low_stock_threshold from stock_items where stock_items.id = stock_levels.stock_item_id))');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'min_quantity' => 'decimal:3',
        ];
    }
}
