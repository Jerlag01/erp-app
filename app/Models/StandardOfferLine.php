<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A default line to add to an offer, with its supply (leveren) and installation (plaatsen) unit price.
 * A labour line takes the hourly rate of the offer as installation price.
 */
#[Fillable(['offer_category_id', 'description', 'quantity', 'unit', 'supply_price', 'install_price', 'is_labour', 'is_active', 'sort_order'])]
class StandardOfferLine extends Model
{
    /**
     * @return BelongsTo<OfferCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(OfferCategory::class, 'offer_category_id');
    }

    /**
     * The line as it is added to an offer.
     *
     * @return array<string, mixed>
     */
    public function toOfferLine(float $hourlyRate): array
    {
        return [
            'offer_category_id' => $this->offer_category_id,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'supply_price' => $this->supply_price !== null ? (float) $this->supply_price : null,
            'install_price' => $this->is_labour ? $hourlyRate : ($this->install_price !== null ? (float) $this->install_price : null),
        ];
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
            'is_labour' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
