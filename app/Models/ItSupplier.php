<?php

namespace App\Models;

use App\Models\Concerns\DeletesStoredFiles;
use App\Models\Concerns\HasAddress;
use Database\Factories\ItSupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Where IT equipment and licenses are bought, or who repairs them. Separate from the stock module's distributors.
 */
#[Fillable([
    'name', 'website', 'store_url', 'email', 'phone', 'street', 'postal_code', 'city', 'country', 'logo', 'notes',
    'contact_name', 'contact_email', 'contact_phone',
])]
class ItSupplier extends Model
{
    use DeletesStoredFiles, HasAddress;

    /** @use HasFactory<ItSupplierFactory> */
    use HasFactory;

    public function storedFiles(): array
    {
        return ['logo'];
    }

    /**
     * Whether IT records still refer to this supplier, so it cannot be deleted.
     */
    public function isInUse(): bool
    {
        foreach ([ItAsset::class, ItLicense::class, ItItem::class, ItMaintenance::class] as $model) {
            if ($model::query()->where('it_supplier_id', $this->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return HasMany<ItAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(ItAsset::class);
    }
}
