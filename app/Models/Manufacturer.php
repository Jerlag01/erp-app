<?php

namespace App\Models;

use App\Models\Concerns\DeletesStoredFiles;
use App\Models\Concerns\HasAddress;
use Database\Factories\ManufacturerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A brand that makes the items, e.g. Schneider Electric.
 */
#[Fillable(['name', 'website', 'email', 'phone', 'street', 'postal_code', 'city', 'country', 'logo', 'notes'])]
class Manufacturer extends Model
{
    use DeletesStoredFiles, HasAddress;

    /** @use HasFactory<ManufacturerFactory> */
    use HasFactory;

    public function storedFiles(): array
    {
        return ['logo'];
    }

    /**
     * @return BelongsToMany<Distributor, $this>
     */
    public function distributors(): BelongsToMany
    {
        return $this->belongsToMany(Distributor::class);
    }

    /**
     * @return HasMany<StockItem, $this>
     */
    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }
}
