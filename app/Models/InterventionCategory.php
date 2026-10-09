<?php

namespace App\Models;

use App\Enums\InterventionCategoryKind;
use Database\Factories\InterventionCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An intervention category (PREPARE, CONNECT, DECONNECT, …) or subcategory (COLIBRI with its
 * phases and tags). Administrators add, change and remove them freely; closed reports keep the
 * names they had.
 */
#[Fillable(['kind', 'name', 'frees_material', 'phases', 'tags', 'sort', 'is_active'])]
class InterventionCategory extends Model
{
    /** @use HasFactory<InterventionCategoryFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    /**
     * @param  Builder<InterventionCategory>  $query
     */
    public function scopeOfKind(Builder $query, InterventionCategoryKind $kind): void
    {
        $query->where('kind', $kind->value)->orderBy('sort')->orderBy('name');
    }

    /**
     * Active ones of a kind, plus the given ids (so a stored choice stays visible).
     *
     * @param  list<int>  $keep
     * @return array<int, string>
     */
    public static function options(InterventionCategoryKind $kind, array $keep = []): array
    {
        return self::query()
            ->ofKind($kind)
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereIn('id', $keep ?: [0]))
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return list<string>
     */
    public function phaseList(): array
    {
        return array_values(array_filter((array) $this->phases, 'filled'));
    }

    /**
     * @return list<string>
     */
    public function tagList(): array
    {
        return array_values(array_filter((array) $this->tags, 'filled'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => InterventionCategoryKind::class,
            'frees_material' => 'boolean',
            'phases' => 'array',
            'tags' => 'array',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
