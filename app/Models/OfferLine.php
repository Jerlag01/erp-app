<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of an offer: quantity × supply price and quantity × installation price. Without a quantity
 * it is a text line. A warehouse stock item brings its price as cost price; the supply price is then
 * cost price × the profit factor of the line.
 */
#[Fillable(['offer_id', 'offer_category_id', 'stock_item_id', 'description', 'quantity', 'unit', 'cost_price', 'factor', 'supply_price', 'install_price', 'sort_order'])]
class OfferLine extends Model
{
    /**
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * @return BelongsTo<OfferCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(OfferCategory::class, 'offer_category_id');
    }

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    /**
     * Cost price × profit factor, or null when either is missing.
     */
    public static function priceFromCost(mixed $costPrice, mixed $factor): ?float
    {
        return is_numeric($costPrice) && is_numeric($factor) ? round((float) $costPrice * (float) $factor, 2) : null;
    }

    /**
     * The profit factor a new stock line starts with (Settings > Offers).
     */
    public static function defaultFactor(): float
    {
        return (float) settings()->get('offers.default_factor', 1) ?: 1.0;
    }

    public static function totalFor(mixed $quantity, mixed $unitPrice): float
    {
        return round((float) $quantity * (float) $unitPrice, 2);
    }

    public function isText(): bool
    {
        return $this->quantity === null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            // A stock line: the supply price follows the cost price and the profit factor.
            if ($line->stock_item_id !== null && ($price = self::priceFromCost($line->cost_price, $line->factor)) !== null) {
                $line->supply_price = $price;
            }

            $line->supply_total = self::totalFor($line->quantity, $line->supply_price);
            $line->install_total = self::totalFor($line->quantity, $line->install_price);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'cost_price' => 'decimal:2',
            'factor' => 'decimal:3',
            'supply_price' => 'decimal:2',
            'install_price' => 'decimal:2',
            'supply_total' => 'decimal:2',
            'install_total' => 'decimal:2',
        ];
    }
}
