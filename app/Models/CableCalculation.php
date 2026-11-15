<?php

namespace App\Models;

use Database\Factories\CableCalculationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cable calculation note: a name, optionally a project, and several circuits, AC or DC each, from a
 * start point to an end point (see CableCalculationCircuit), each with its own result.
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
}
