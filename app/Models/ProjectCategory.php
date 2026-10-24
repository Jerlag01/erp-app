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
#[Fillable(['code', 'name', 'description', 'numbering', 'next_number', 'has_short_description', 'is_active'])]
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
     * The next free project number: 0001, 0002, … or, for yearly numbering, 260001, 260002, … in 2026. With a
     * follow-up number set on the category, numbering continues from there instead.
     */
    public function nextNumber(): int
    {
        // Set by hand: count on from there, skipping numbers that are already in use.
        if ($this->next_number !== null && $this->numberFitsNumbering((int) $this->next_number)) {
            $taken = $this->projects()->where('number', '>=', (int) $this->next_number)->pluck('number')->map(fn (mixed $number): int => (int) $number)->flip();
            $number = (int) $this->next_number;

            while ($taken->has($number)) {
                $number++;
            }

            return $number;
        }

        if ($this->numbering === ProjectNumbering::Yearly) {
            $start = (int) now()->format('y') * ProjectNumbering::PER_YEAR;
            $last = $this->projects()->whereBetween('number', [$start, $start + ProjectNumbering::PER_YEAR - 1])->max('number');

            return $last ? (int) $last + 1 : $start + 1;
        }

        return (int) $this->projects()->max('number') + 1;
    }

    /**
     * Yearly numbers belong to this year (260001–269999 in 2026); a starting number of another year is ignored.
     */
    public function numberFitsNumbering(int $number): bool
    {
        if ($this->numbering !== ProjectNumbering::Yearly) {
            return $number >= 1;
        }

        $start = (int) now()->format('y') * ProjectNumbering::PER_YEAR;

        return $number > $start && $number < $start + ProjectNumbering::PER_YEAR;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numbering' => ProjectNumbering::class,
            'next_number' => 'integer',
            'has_short_description' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
