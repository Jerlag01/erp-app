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
     * The suppliers it can be bought from, each with its own article number and price; the preferred one first.
     *
     * @return HasMany<StockItemSupplier, $this>
     */
    public function suppliers(): HasMany
    {
        return $this->hasMany(StockItemSupplier::class)->orderByDesc('is_preferred')->orderBy('sort')->orderBy('id');
    }

    /**
     * The item's distributor, reference and market price follow its preferred supplier (the first one when
     * none is marked; its price, or else the lowest price of the others).
     */
    public function syncFromSuppliers(): void
    {
        $suppliers = $this->suppliers()->get();
        $preferred = $suppliers->firstWhere('is_preferred', true) ?? $suppliers->first();

        if ($preferred !== null && ! $preferred->is_preferred) {
            $preferred->updateQuietly(['is_preferred' => true]);
        }

        $priced = $preferred?->price !== null ? $preferred : $suppliers->whereNotNull('price')->sortBy(fn (StockItemSupplier $supplier): float => (float) $supplier->price)->first();

        $this->forceFill([
            'distributor_id' => $preferred?->distributor_id,
            'distributor_reference' => $preferred?->reference,
            ...($priced !== null ? [
                'market_price' => $priced->price,
                'price_updated_at' => $priced->price_updated_at,
                'price_source' => $priced->price_source,
            ] : []),
        ])->saveQuietly();
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
        // A distributor set on the item itself (an import, older code) becomes its preferred supplier.
        static::saved(function (self $item): void {
            if ($item->distributor_id !== null && ($item->wasRecentlyCreated || $item->wasChanged('distributor_id'))) {
                if (! StockItemSupplier::query()->where('stock_item_id', $item->id)->where('distributor_id', $item->distributor_id)->exists()) {
                    StockItemSupplier::query()->create([
                        'stock_item_id' => $item->id,
                        'distributor_id' => $item->distributor_id,
                        'reference' => $item->distributor_reference,
                        'price' => $item->market_price,
                        'price_source' => $item->price_source,
                        'price_updated_at' => $item->price_updated_at,
                        'is_preferred' => true,
                    ]);
                }
            }
        });

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
