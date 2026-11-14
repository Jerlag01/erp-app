<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of an offer: quantity × supply price and quantity × installation price. Without a quantity
 * it is a text line.
 */
#[Fillable(['offer_id', 'offer_category_id', 'description', 'quantity', 'unit', 'supply_price', 'install_price', 'sort_order'])]
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
            'supply_price' => 'decimal:2',
            'install_price' => 'decimal:2',
            'supply_total' => 'decimal:2',
            'install_total' => 'decimal:2',
        ];
    }
}
