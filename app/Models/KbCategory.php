<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\KbCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A knowledge base category. Hidden categories are only shown to knowledge base editors.
 */
#[Fillable(['name', 'description', 'is_visible', 'sort_order'])]
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
