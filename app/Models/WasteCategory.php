<?php

namespace App\Models;

use Database\Factories\WasteCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A waste category or subcategory (one level deep) with its EURAL waste code. Subcategories
 * of the batteries category are battery waste as well.
 */
#[Fillable(['parent_id', 'name', 'waste_code', 'is_batteries', 'is_active', 'sort_order'])]
class WasteCategory extends Model
{
    /** @use HasFactory<WasteCategoryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<WasteCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<WasteCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return HasMany<WasteEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(WasteEntry::class);
    }

    /**
     * @param  Builder<WasteCategory>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    public function isBatteries(): bool
    {
        return $this->is_batteries || (bool) $this->parent?->is_batteries;
    }

    /**
     * "16 06 01* · Loodaccu's".
     */
    public function label(): string
    {
        return trim(($this->waste_code ? $this->waste_code.' · ' : '').$this->name);
    }

    /**
     * "Batterijen › Loodaccu's".
     */
    public function fullName(): string
    {
        return $this->parent ? "{$this->parent->name} › {$this->name}" : $this->name;
    }

    public function isHazardous(): bool
    {
        return str_contains((string) $this->waste_code, '*');
    }

    public function isInUse(): bool
    {
        return $this->exists && ($this->entries()->exists() || $this->children()->exists());
    }

    /**
     * Options for the entry form: subcategories grouped under their category; a category
     * without subcategories can be chosen itself.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function selectOptions(?int $include = null): array
    {
        $options = [];
        $categories = self::query()->whereNull('parent_id')->ordered()
            ->with(['children' => fn ($query) => $query->where(fn ($query) => $query->where('is_active', true)->when($include, fn ($query) => $query->orWhere('id', $include)))])
            ->get();

        foreach ($categories as $category) {
            if ($category->children->isNotEmpty()) {
                $options[$category->name] = $category->children->mapWithKeys(fn (self $child): array => [$child->id => $child->label()])->all();
            } elseif ($category->is_active || $category->id === $include) {
                $options[$category->id] = $category->label();
            }
        }

        return $options;
    }

    protected static function booted(): void
    {
        // A subcategory follows the batteries flag of its category.
        static::saving(function (self $category): void {
            if ($category->parent_id !== null && $category->parent) {
                $category->is_batteries = $category->parent->is_batteries;
            }
        });

        static::saved(function (self $category): void {
            if ($category->parent_id === null && $category->wasChanged('is_batteries')) {
                self::query()->where('parent_id', $category->id)->update(['is_batteries' => $category->is_batteries]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_batteries' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
