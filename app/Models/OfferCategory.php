<?php

namespace App\Models;

use Database\Factories\OfferCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A chapter of an offer ("A Plaatsen omvormer", "C Bekabeling AC & DC", …). General costs (study,
 * visit, travel) are outside the price of the works and added to it for the total.
 */
#[Fillable(['code', 'name', 'is_general_costs', 'is_active', 'sort_order'])]
class OfferCategory extends Model
{
    /** @use HasFactory<OfferCategoryFactory> */
    use HasFactory;

    /**
     * @return HasMany<StandardOfferLine, $this>
     */
    public function standardLines(): HasMany
    {
        return $this->hasMany(StandardOfferLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function label(): string
    {
        return trim($this->code.' '.$this->name);
    }

    /**
     * @param  Builder<OfferCategory>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('code');
    }

    /**
     * The active categories (and the ones already used), for a select.
     *
     * @param  list<int>  $include
     * @return array<int, string>
     */
    public static function options(array $include = []): array
    {
        return self::query()->where(fn (Builder $query) => $query->where('is_active', true)->orWhereIn('id', $include))
            ->ordered()->get()->mapWithKeys(fn (self $category): array => [$category->id => $category->label()])->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_general_costs' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
