<?php

namespace App\Models;

use App\Enums\WasteRegion;
use Database\Factories\WasteEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in the waste registry: what went to which processor on which date, in kilograms.
 * Only battery waste keeps a region, certificate of destruction number, COW code and PO number.
 */
#[Fillable([
    'date', 'waste_category_id', 'weight_kg', 'waste_processor_id', 'processor_reference',
    'region', 'destruction_certificate', 'po_number', 'work_site_id', 'cow_code', 'notes', 'created_by',
])]
class WasteEntry extends Model
{
    /** @use HasFactory<WasteEntryFactory> */
    use HasFactory;

    public const CERTIFICATE_PATTERN = '/^\d{3}$/';

    /**
     * @return BelongsTo<WasteCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'waste_category_id');
    }

    /**
     * @return BelongsTo<WasteProcessor, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(WasteProcessor::class, 'waste_processor_id');
    }

    /**
     * @return BelongsTo<WorkSite, $this>
     */
    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isBatteries(): bool
    {
        return (bool) $this->category?->isBatteries();
    }

    public static function formatKg(float|string|null $kg): string
    {
        return number_format((float) $kg, 2, ',', '.').' kg';
    }

    protected static function booted(): void
    {
        static::saving(function (self $entry): void {
            // The battery-only fields are cleared for other waste, so the regional lists stay clean.
            if (! $entry->isBatteries()) {
                $entry->region = null;
                $entry->destruction_certificate = null;
                $entry->po_number = null;
                $entry->work_site_id = null;
                $entry->cow_code = null;
            } elseif ($entry->isDirty('work_site_id') && $entry->work_site_id !== null) {
                $entry->cow_code = $entry->workSite?->cow_code;
            }

            if (filled($entry->cow_code)) {
                $entry->cow_code = strtoupper(trim($entry->cow_code));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'weight_kg' => 'decimal:2',
            'region' => WasteRegion::class,
        ];
    }
}
