<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One article of a distributor order: references, quantity ordered and delivered, and the stock item it
 * matches (by the distributor's reference, else the manufacturer's).
 */
#[Fillable([
    'distributor_order_id', 'line_number', 'distributor_reference', 'manufacturer_reference', 'description',
    'quantity', 'quantity_delivered', 'unit', 'net_price', 'stock_item_id', 'expected_delivery', 'delivered_at',
])]
class DistributorOrderLine extends Model
{
    /**
     * @return BelongsTo<DistributorOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(DistributorOrder::class, 'distributor_order_id');
    }

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function isDelivered(): bool
    {
        return (float) $this->quantity > 0 && (float) $this->quantity_delivered >= (float) $this->quantity;
    }

    /**
     * The stock item with this line's references (of the order's distributor first).
     */
    public function matchStockItem(): void
    {
        if ($this->stock_item_id !== null) {
            return;
        }

        $distributorId = $this->order?->distributor_id;

        foreach (['distributor_reference', 'manufacturer_reference'] as $field) {
            if (blank($this->{$field})) {
                continue;
            }

            $item = StockItem::query()
                ->where($field, $this->{$field})
                ->when($field === 'distributor_reference' && $distributorId, fn ($query) => $query->orderByRaw('distributor_id = ? desc', [$distributorId]))
                ->value('id');

            if ($item) {
                $this->stock_item_id = $item;

                return;
            }
        }
    }

    protected static function booted(): void
    {
        static::saving(fn (self $line) => $line->matchStockItem());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'quantity_delivered' => 'decimal:3',
            'net_price' => 'decimal:4',
            'expected_delivery' => 'date',
            'delivered_at' => 'date',
        ];
    }
}
