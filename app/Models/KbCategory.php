<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Support\ManualAccess;
use Database\Factories\KbCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A knowledge base category, possibly a subcategory of a main category (one level deep). Hidden
 * categories, and the subcategories of a hidden main category, are only shown to knowledge base editors.
 */
#[Fillable(['parent_id', 'module_key', 'name', 'description', 'is_visible', 'sort_order'])]
class KbCategory extends Model
{
    /** @use HasFactory<KbCategoryFactory> */
    use HasFactory, HasTranslations;

    /**
     * @return HasMany<KbArticle, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(KbArticle::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<KbCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<KbCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Visible itself, and so is its main category.
     */
    public function isShown(): bool
    {
        return $this->is_visible && ($this->parent === null || $this->parent->is_visible);
    }

    /**
     * Whether the user has access to the module the category is about (its own key, else its main
     * category's; see ManualAccess). Knowledge base editors see everything.
     */
    public function isAccessibleBy(User $user): bool
    {
        return $user->canEditKnowledgeBase() || ManualAccess::allows($user, $this->module_key ?? $this->parent?->module_key);
    }

    /**
     * "ERP-app handleiding › Planning", or the name of a main category.
     */
    public function path(?string $locale = null): string
    {
        return $this->parent ? $this->parent->translate('name', $locale).' › '.$this->translate('name', $locale) : $this->translate('name', $locale);
    }

    /**
     * Visible categories whose main category (if any) is visible too.
     *
     * @param  Builder<KbCategory>  $query
     */
    public function scopeShown(Builder $query): void
    {
        $query->where('is_visible', true)->where(fn (Builder $query) => $query->whereNull('parent_id')->orWhereHas('parent', fn (Builder $query) => $query->where('is_visible', true)));
    }

    /**
     * @param  Builder<KbCategory>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
