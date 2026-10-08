<?php

namespace App\Models;

use App\Models\Concerns\DeletesStoredFiles;
use Database\Factories\ItModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A hardware model, e.g. "Dell Latitude 5440", that assets are instances of.
 */
#[Fillable(['name', 'it_manufacturer_id', 'it_category_id', 'model_number', 'eol_months', 'image', 'notes'])]
class ItModel extends Model
{
    use DeletesStoredFiles;

    /** @use HasFactory<ItModelFactory> */
    use HasFactory;

    public function storedFiles(): array
    {
        return ['image'];
    }

    /**
     * @return BelongsTo<ItManufacturer, $this>
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(ItManufacturer::class, 'it_manufacturer_id');
    }

    /**
     * @return BelongsTo<ItCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ItCategory::class, 'it_category_id');
    }

    /**
     * @return HasMany<ItAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(ItAsset::class);
    }

    public function fullName(): string
    {
        return trim(($this->manufacturer?->name ? $this->manufacturer->name.' ' : '').$this->name);
    }

    /**
     * Whether assets or other IT records still use this, so it cannot be deleted.
     */
    public function isInUse(): bool
    {
        return $this->assets()->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'eol_months' => 'integer',
        ];
    }
}
