<?php

namespace App\Models;

use App\Enums\WasteRegion;
use Database\Factories\WasteEntryLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of a battery waste entry: one destruction certificate with its region, COW code, PO number,
 * project reference (of the platform or typed in freely) and weight. The entry weighs the sum of its lines; the regional battery lists are made of lines.
 */
#[Fillable(['waste_entry_id', 'region', 'destruction_certificate', 'work_site_id', 'cow_code', 'po_number', 'project_reference', 'project_id', 'weight_kg', 'sort'])]
class WasteEntryLine extends Model
{
    /** @use HasFactory<WasteEntryLineFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<WasteEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(WasteEntry::class, 'waste_entry_id');
    }

    /**
     * @return BelongsTo<WorkSite, $this>
     */
    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class);
    }

    /**
     * The project of the platform with this reference, when there is one.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            // A reference of the platform is linked to its project; anything else is kept as typed.
            $line->project_reference = filled($line->project_reference) ? trim($line->project_reference) : null;
            $line->project_id = $line->project_reference ? Project::query()->where('reference', $line->project_reference)->value('id') : null;

            if ($line->isDirty('work_site_id') && $line->work_site_id !== null) {
                $line->cow_code = $line->workSite?->cow_code;
            }

            $line->cow_code = filled($line->cow_code) ? strtoupper(trim($line->cow_code)) : null;
        });

        // The entry always weighs the sum of its lines.
        static::saved(fn (self $line) => $line->entry?->syncWeightFromLines());
        static::deleted(fn (self $line) => $line->entry?->syncWeightFromLines());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'region' => WasteRegion::class,
            'weight_kg' => 'decimal:2',
        ];
    }
}
