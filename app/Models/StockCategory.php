<?php

namespace App\Models;

use App\Support\Translation\Translator;
use Database\Factories\StockCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A category (Cables) or a subcategory of one (Cables › XVB). Two levels deep. "name" is the Dutch name;
 * the French and English names are translated automatically (Translator, MyMemory) when they are empty or
 * when the Dutch name changes, unless they were changed by hand in the same save.
 */
#[Fillable(['parent_id', 'name', 'name_fr', 'name_en', 'sort'])]
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
     * The name in a language (the user's by default), the Dutch name when there is no translation.
     */
    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) (in_array($locale, ['fr', 'en'], true) && filled($this->{'name_'.$locale}) ? $this->{'name_'.$locale} : $this->name);
    }

    /**
     * "Cables › XVB" for a subcategory, "Cables" for a category.
     */
    public function fullName(?string $locale = null): string
    {
        return $this->parent ? $this->parent->label($locale).' › '.$this->label($locale) : $this->label($locale);
    }

    /**
     * Fills the French and English names from the Dutch one where needed (see the class description).
     */
    public function translateNames(): void
    {
        $missing = array_values(array_filter(['fr', 'en'], fn (string $locale): bool => blank($this->{'name_'.$locale})
            || ($this->isDirty('name') && ! $this->isDirty('name_'.$locale))));

        if ($missing === [] || blank($this->name)) {
            return;
        }

        $translations = app(Translator::class)->fromDutch((string) $this->name);

        foreach ($missing as $locale) {
            if (filled($translations[$locale] ?? null)) {
                $this->{'name_'.$locale} = $translations[$locale];
            }
        }
    }

    protected static function booted(): void
    {
        static::saving(fn (self $category) => $category->translateNames());
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
            // "+" keeps the ids as keys (spreading with "..." would renumber them).
            ->mapWithKeys(fn (self $category): array => [$category->label() => [$category->id => $category->label()]
                + $category->children->mapWithKeys(fn (self $child): array => [$child->id => $category->label().' › '.$child->label()])->all()])
            ->all();
    }
}
