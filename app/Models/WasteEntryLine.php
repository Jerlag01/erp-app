<?php

namespace App\Models;

use App\Enums\WasteRegion;
use Database\Factories\WasteEntryLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A line of a battery waste entry: one destruction certificate with its region, COW code, PO number,
 * project reference (of the platform or typed in freely), weight and its own PDF. The entry weighs the sum of its lines; the regional battery lists are made of lines.
 */
#[Fillable(['waste_entry_id', 'region', 'destruction_certificate', 'work_site_id', 'cow_code', 'po_number', 'project_reference', 'project_id', 'weight_kg', 'pdf_path', 'pdf_name', 'sort'])]
class WasteEntryLine extends Model
{
    /** @use HasFactory<WasteEntryLineFactory> */
    use HasFactory;

    /** The PDF of the line, on the private disk. */
    public const DISK = 'local';

    public function hasPdf(): bool
    {
        return filled($this->pdf_path) && Storage::disk(self::DISK)->exists($this->pdf_path);
    }

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
        static::deleted(function (self $line): void {
            $line->entry?->syncWeightFromLines();

            if (filled($line->pdf_path)) {
                Storage::disk(self::DISK)->delete($line->pdf_path);
            }
        });

        // A replaced or removed PDF goes from the disk too.
        static::updated(function (self $line): void {
            if ($line->wasChanged('pdf_path') && filled($old = $line->getOriginal('pdf_path'))) {
                Storage::disk(self::DISK)->delete($old);
            }
        });
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
