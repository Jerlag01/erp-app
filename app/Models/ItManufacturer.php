<?php

namespace App\Models;

use App\Models\Concerns\DeletesStoredFiles;
use App\Models\Concerns\HasAddress;
use Database\Factories\ItManufacturerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A maker of IT equipment or software (Dell, Lenovo, Microsoft…). Separate from the stock module's manufacturers.
 */
#[Fillable([
    'name', 'website', 'email', 'phone', 'street', 'postal_code', 'city', 'country', 'logo', 'notes',
    'support_url', 'support_email', 'support_phone',
])]
class ItManufacturer extends Model
{
    use DeletesStoredFiles, HasAddress;

    /** @use HasFactory<ItManufacturerFactory> */
    use HasFactory;

    public function storedFiles(): array
    {
        return ['logo'];
    }

    /**
     * @return HasMany<ItModel, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(ItModel::class);
    }

    /**
     * @return HasMany<ItLicense, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(ItLicense::class);
    }

    /**
     * @return HasMany<ItItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ItItem::class);
    }

    /**
     * Whether IT records still refer to this manufacturer, so it cannot be deleted.
     */
    public function isInUse(): bool
    {
        return $this->models()->exists() || $this->licenses()->exists() || $this->items()->exists();
    }
}
