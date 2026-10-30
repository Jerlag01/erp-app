<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supplier (distributor) of a stock item, with its article number and price. The price is fetched
 * through the supplier's connection (Rexel, Cebeo) or, without one, entered by hand ("manual"). Every
 * new price is kept in the item's price history.
 */
#[Fillable(['stock_item_id', 'distributor_id', 'reference', 'price', 'price_source', 'price_updated_at', 'is_preferred', 'sort'])]
class StockItemSupplier extends Model
{
    public const MANUAL = 'manual';

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * The price comes through the supplier's connection: it cannot be entered by hand.
     */
    public function hasConnection(): bool
    {
        return (bool) $this->distributor?->hasPriceConnection();
    }

    protected function recordHistory(): void
    {
        StockItemPrice::query()->create([
            'stock_item_id' => $this->stock_item_id,
            'distributor_id' => $this->distributor_id,
            'price' => $this->price,
            'source' => $this->price_source ?? self::MANUAL,
            'fetched_at' => $this->price_updated_at ?? now(),
        ]);
    }

    protected static function booted(): void
    {
        static::saving(function (self $supplier): void {
            // A price typed in (a fetched one comes with its source and time): manual, from now.
            if ($supplier->isDirty('price') && ! $supplier->isDirty('price_source') && ! $supplier->isDirty('price_updated_at')) {
                $supplier->price_source = $supplier->price === null ? null : self::MANUAL;
                $supplier->price_updated_at = $supplier->price === null ? null : now();
            }
        });

        static::saved(function (self $supplier): void {
            if ($supplier->is_preferred) {
                self::query()->where('stock_item_id', $supplier->stock_item_id)->whereKeyNot($supplier->id)->where('is_preferred', true)->update(['is_preferred' => false]);
            }

            $supplier->item?->syncFromSuppliers();
        });

        // Every new price goes in the item's price history.
        static::created(fn (self $supplier) => $supplier->price !== null && $supplier->recordHistory());
        static::updated(fn (self $supplier) => $supplier->wasChanged('price') && $supplier->price !== null && $supplier->recordHistory());

        static::deleted(fn (self $supplier) => $supplier->item?->syncFromSuppliers());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'price_updated_at' => 'datetime',
            'is_preferred' => 'boolean',
        ];
    }
}
