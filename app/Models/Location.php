<?php

namespace App\Models;

use App\Models\Concerns\HasAddress;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A site of the organisation (head office, depot, warehouse) with its teams, vehicles, assets and stock.
 */
#[Fillable(['name', 'code', 'street', 'postal_code', 'city', 'country', 'phone', 'email', 'notes', 'is_active'])]
class Location extends Model
{
    use HasAddress;

    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    /**
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * @return HasMany<StockLevel, $this>
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * Locations the user may see: those of their teams, or all of them for administrators.
     *
     * @param  Builder<Location>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesOnlyOwnTeams()) {
            $query->whereIn('locations.id', $user->locationIds());
        }
    }

    /**
     * Every location keeps a stock row for every item, starting at zero.
     */
    protected static function booted(): void
    {
        static::created(function (self $location): void {
            StockItem::query()->select('id')->chunkById(500, function ($items) use ($location): void {
                StockLevel::insertOrIgnore($items->map(fn (StockItem $item): array => [
                    'stock_item_id' => $item->id,
                    'location_id' => $location->id,
                    'quantity' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
