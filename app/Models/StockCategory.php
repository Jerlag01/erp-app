<?php

namespace App\Models;

use Database\Factories\StockCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A category (Cables) or a subcategory of one (Cables › XVB). Two levels deep.
 */
#[Fillable(['parent_id', 'name', 'sort'])]
class StockCategory extends Model
{
    /** @use HasFactory<StockCategoryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<StockCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<StockCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('name');
    }

    /**
     * @return HasMany<StockItem, $this>
     */
    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    /**
     * @param  Builder<StockCategory>  $query
     */
    public function scopeTopLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * "Cables › XVB" for a subcategory, "Cables" for a category.
     */
    public function fullName(): string
    {
        return $this->parent ? "{$this->parent->name} › {$this->name}" : $this->name;
    }

    /**
     * This category and its subcategories, to filter items on a whole category.
     *
     * @return list<int>
     */
    public function selfAndChildIds(): array
    {
        return [$this->id, ...$this->children()->pluck('id')->all()];
    }

    /**
     * Options grouped per category, for selects: ['Cables' => [1 => 'Cables', 2 => 'Cables › XVB']].
     *
     * @return array<string, array<int, string>>
     */
    public static function groupedOptions(): array
    {
        return static::query()->topLevel()->with('children')->orderBy('sort')->orderBy('name')->get()
            ->mapWithKeys(fn (self $category): array => [$category->name => [
                $category->id => $category->name,
                ...$category->children->mapWithKeys(fn (self $child): array => [$child->id => "{$category->name} › {$child->name}"])->all(),
            ]])
            ->all();
    }
}
