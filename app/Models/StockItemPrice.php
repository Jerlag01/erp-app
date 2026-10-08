<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A market price as it was fetched (or entered) at one moment, for price history.
 */
#[Fillable(['stock_item_id', 'price', 'source', 'fetched_at'])]
class StockItemPrice extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'fetched_at' => 'datetime',
        ];
    }
}
