<?php

namespace App\Models;

use App\Enums\ProjectNumbering;
use Database\Factories\ProjectCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A main project category such as 604 or 605. Its numbering decides how project references
 * are built. A category that holds projects cannot be deleted, and its code and numbering
 * are locked so existing references stay valid.
 */
#[Fillable(['code', 'name', 'description', 'numbering', 'has_short_description', 'is_active'])]
class ProjectCategory extends Model
{
    /** @use HasFactory<ProjectCategoryFactory> */
    use HasFactory;

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function hasProjects(): bool
    {
        return $this->exists && $this->projects()->exists();
    }

    public function label(): string
    {
        return $this->name !== $this->code ? "{$this->code} · {$this->name}" : $this->code;
    }

    /**
     * The next free project number: 001, 002, … or, for yearly numbering, 26001, 26002, … in 2026.
     */
    public function nextNumber(): int
    {
        if ($this->numbering === ProjectNumbering::Yearly) {
            $start = (int) now()->format('y') * 1000;
            $last = $this->projects()->whereBetween('number', [$start, $start + 999])->max('number');

            return $last ? (int) $last + 1 : $start + 1;
        }

        return (int) $this->projects()->max('number') + 1;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numbering' => ProjectNumbering::class,
            'has_short_description' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
