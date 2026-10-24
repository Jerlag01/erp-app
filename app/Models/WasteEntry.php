<?php

namespace App\Models;

use App\Enums\WasteRegion;
use Database\Factories\WasteEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * One line in the waste registry: what went to which processor on which date, in kilograms.
 * Battery waste has lines (WasteEntryLine): per destruction certificate its region, COW code, PO
 * number and weight; the entry then weighs the sum of its lines.
 */
#[Fillable([
    'date', 'waste_category_id', 'weight_kg', 'waste_processor_id', 'processor_reference',
    'notes', 'pdf_path', 'pdf_name', 'created_by',
])]
class WasteEntry extends Model
{
    /** @use HasFactory<WasteEntryFactory> */
    use HasFactory;

    public const CERTIFICATE_PATTERN = '/^\d{3}$/';

    /** The PDF of a battery entry, on the private disk. */
    public const DISK = 'local';

    public function hasPdf(): bool
    {
        return filled($this->pdf_path) && Storage::disk(self::DISK)->exists($this->pdf_path);
    }

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
     * The battery lines, in the order entered.
     *
     * @return HasMany<WasteEntryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(WasteEntryLine::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Battery waste weighs the sum of its lines.
     */
    public function syncWeightFromLines(): void
    {
        if (! $this->isBatteries() || ! $this->lines()->exists()) {
            return;
        }

        $total = round((float) $this->lines()->sum('weight_kg'), 2);

        if ((float) $this->weight_kg !== $total) {
            $this->forceFill(['weight_kg' => $total])->saveQuietly();
        }
    }

    /**
     * Distinct values of a line field, for the lists and the PDF.
     *
     * @return list<string>
     */
    public function lineValues(string $field, ?WasteRegion $region = null): array
    {
        return $this->lines
            ->when($region, fn ($lines) => $lines->where('region', $region))
            ->map(fn (WasteEntryLine $line): ?string => $line->{$field} instanceof WasteRegion ? $line->{$field}->getLabel() : $line->{$field})
            ->filter()
            ->unique()
            ->values()
            ->all();
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
            // Battery waste gets its weight from its lines (filled in once they are saved).
            $entry->weight_kg ??= 0;
        });

        // A replaced or removed PDF goes from the disk too.
        static::updated(function (self $entry): void {
            if ($entry->wasChanged('pdf_path') && filled($old = $entry->getOriginal('pdf_path'))) {
                Storage::disk(self::DISK)->delete($old);
            }
        });

        static::deleted(function (self $entry): void {
            if (filled($entry->pdf_path)) {
                Storage::disk(self::DISK)->delete($entry->pdf_path);
            }
        });

        // Other waste has no battery lines.
        static::saved(function (self $entry): void {
            if (! $entry->isBatteries()) {
                $entry->lines()->delete();
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
        ];
    }
}
