<?php

namespace App\Models;

use Database\Factories\CustomAbsenceTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An absence type added by administration (Human resources > Absence types): its name (per language), its
 * short code on the prestation overviews and how the prestation sheet counts a day of it:
 *  - worked: a paid day off, the hours of the day are credited;
 *  - keeps_overtime: not worked (e.g. paid by the RVA), taken off the expected hours, the overtime stays;
 *  - unworked: not worked and not credited (it comes off the overtime, like family leave).
 */
#[Fillable(['code', 'name', 'name_fr', 'name_en', 'color', 'counting', 'blocks_planning', 'is_private', 'takes_document', 'is_active', 'sort'])]
class CustomAbsenceType extends Model
{
    /** @use HasFactory<CustomAbsenceTypeFactory> */
    use HasFactory;

    public const WORKED = 'worked';

    public const KEEPS_OVERTIME = 'keeps_overtime';

    public const UNWORKED = 'unworked';

    public const COUNTINGS = [self::WORKED, self::KEEPS_OVERTIME, self::UNWORKED];

    /**
     * @return HasMany<Absence, $this>
     */
    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    /**
     * @param  Builder<CustomAbsenceType>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The name in the given language (the app's by default), Dutch when it has none.
     */
    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) (match ($locale) {
            'fr' => $this->name_fr,
            'en' => $this->name_en,
            default => null,
        } ?: $this->name);
    }

    /**
     * Id => "WV · Weerverlet", the active ones and the one already chosen.
     *
     * @return array<int, string>
     */
    public static function options(?int $keep = null): array
    {
        return self::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->when($keep, fn (Builder $query, int $id) => $query->orWhere('id', $id)))
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (self $type): array => [$type->id => $type->code.' · '.$type->label()])
            ->all();
    }

    protected static function booted(): void
    {
        static::saving(function (self $type): void {
            $type->code = mb_strtoupper(trim((string) $type->code));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks_planning' => 'boolean',
            'is_private' => 'boolean',
            'takes_document' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
