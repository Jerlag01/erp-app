<?php

namespace App\Models;

use App\Support\CableCalculationDocuments;
use Database\Factories\CableCalculationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cable calculation note: a name, optionally a project, and several circuits, AC or DC each, from a
 * start point to an end point (see CableCalculationCircuit), each with its own result. With a project, its
 * PDF is kept in the project's extra files and as-built (see CableCalculationDocuments).
 */
#[Fillable(['name', 'project_id', 'notes', 'created_by'])]
class CableCalculation extends Model
{
    /** @use HasFactory<CableCalculationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The PDF in the project's extra files.
     *
     * @return BelongsTo<ProjectFile, $this>
     */
    public function projectFile(): BelongsTo
    {
        return $this->belongsTo(ProjectFile::class);
    }

    /**
     * The PDF in the "cable dimensions" of the project's as-built.
     *
     * @return BelongsTo<AsbuiltFile, $this>
     */
    public function asbuiltFile(): BelongsTo
    {
        return $this->belongsTo(AsbuiltFile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<CableCalculationCircuit, $this>
     */
    public function circuits(): HasMany
    {
        return $this->hasMany(CableCalculationCircuit::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The circuits without a fitting cable (too small a provided section, nothing up to 300 mm², missing input).
     */
    public function failingCircuits(): int
    {
        return $this->circuits->filter(fn (CableCalculationCircuit $circuit): bool => ! (((array) $circuit->result)['ok'] ?? false))->count();
    }

    protected static function booted(): void
    {
        static::deleting(fn (self $calculation) => app(CableCalculationDocuments::class)->remove($calculation));
    }
}
