<?php

namespace App\Models;

use App\Models\Concerns\DeletesStoredFiles;
use Database\Factories\StockItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * An article in the catalogue. Every location has its own stock level for it.
 */
#[Fillable([
    'name', 'description', 'stock_category_id', 'manufacturer_id', 'distributor_id', 'unit_id', 'low_stock_threshold',
    'manufacturer_reference', 'distributor_reference', 'ean', 'market_price', 'price_updated_at', 'price_source',
    'image', 'is_active', 'notes',
])]
class StockItem extends Model
{
    use DeletesStoredFiles;

    /** @use HasFactory<StockItemFactory> */
    use HasFactory;

    public function storedFiles(): array
    {
        return ['image'];
    }

    /**
     * @return BelongsTo<StockCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(StockCategory::class, 'stock_category_id');
    }

    /**
     * @return BelongsTo<Manufacturer, $this>
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return HasMany<StockLevel, $this>
     */
    public function levels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * @return HasManyThrough<StockMovement, StockLevel, $this>
     */
    public function movements(): HasManyThrough
    {
        return $this->hasManyThrough(StockMovement::class, StockLevel::class);
    }

    /**
     * @return HasMany<StockItemPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(StockItemPrice::class)->latest('fetched_at');
    }

    /**
     * The short link printed in the QR code; it opens the item for stock changes.
     */
    public function qrUrl(): string
    {
        return route('stock.qr', $this);
    }

    /**
     * Records a new market price and keeps the previous ones as history.
     */
    public function recordPrice(float $price, string $source): void
    {
        $this->prices()->create(['price' => $price, 'source' => $source, 'fetched_at' => now()]);
        $this->update(['market_price' => $price, 'price_updated_at' => now(), 'price_source' => $source]);
    }

    /**
     * Items in a category, including its subcategories.
     *
     * @param  Builder<StockItem>  $query
     */
    public function scopeInCategory(Builder $query, StockCategory|int $category): void
    {
        $category = $category instanceof StockCategory ? $category : StockCategory::findOrFail($category);

        $query->whereIn('stock_category_id', $category->selfAndChildIds());
    }

    /**
     * Every location gets a stock row for a new item, starting at zero.
     */
    protected static function booted(): void
    {
        static::created(function (self $item): void {
            StockLevel::insertOrIgnore(Location::query()->pluck('id')->map(fn (int $locationId): array => [
                'stock_item_id' => $item->id,
                'location_id' => $locationId,
                'quantity' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'low_stock_threshold' => 'decimal:3',
            'market_price' => 'decimal:4',
            'price_updated_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
