<?php

namespace App\Models;

use App\Enums\MaterialDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_item_id', 'description', 'quantity', 'unit_id', 'direction'])]
class InterventionReportMaterial extends Model
{
    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function quantityLabel(): string
    {
        $quantity = rtrim(rtrim(number_format((float) $this->quantity, 3, ',', '.'), '0'), ',');

        return trim($quantity.' '.($this->unit?->abbreviation ?? ''));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'direction' => MaterialDirection::class,
        ];
    }
}
