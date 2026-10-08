<?php

namespace App\Models;

use App\Enums\ItCategoryType;
use Database\Factories\ItCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * IT category per kind of item: laptops (asset), mice (accessory), toner (consumable), RAM (component), Office (license).
 */
#[Fillable(['type', 'name'])]
class ItCategory extends Model
{
    /** @use HasFactory<ItCategoryFactory> */
    use HasFactory;

    /**
     * @param  Builder<ItCategory>  $query
     */
    public function scopeOfType(Builder $query, ItCategoryType $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Whether assets or other IT records still use this, so it cannot be deleted.
     */
    public function isInUse(): bool
    {
        return ItModel::where('it_category_id', $this->id)->exists()
            || ItLicense::where('it_category_id', $this->id)->exists()
            || ItItem::where('it_category_id', $this->id)->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ItCategoryType::class,
        ];
    }
}
