<?php

namespace App\Models;

use Database\Factories\BatteryRoomDossierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * The battery room of a 609 project, from the Excel "Leveringsnota 14EIN": the delivery note
 * (quantities per item), extra info from the client, the checklist (the same everywhere: OK/NOK and the
 * action to take per point) and the photos, ventilation calculation and BTV report. The texts are in
 * lang/{nl,fr}/battery_rooms.php; the documents are printed in the language of the region.
 */
#[Fillable([
    'project_id', 'document_locale', 'room_number', 'note_date', 'client_reference', 'quantities', 'remarks', 'signed_by',
    'extra_info', 'checklist_date', 'checklist', 'photos', 'ventilation_file', 'ventilation_file_name', 'btv_file', 'btv_file_name',
    'created_by',
])]
class BatteryRoomDossier extends Model
{
    /** @use HasFactory<BatteryRoomDossierFactory> */
    use HasFactory;

    public const DISK = 'local';

    public const CATEGORY = '609';

    public const DOCUMENT_LOCALES = ['nl', 'fr'];

    /**
     * The delivery note: per section its lines with their unit (m, st or u), as in the Excel.
     *
     * @var array<string, array<string, string>>
     */
    public const SECTIONS = [
        'lighting' => ['tube' => 'm', 'cable' => 'm', 'box' => 'st', 'fixtures' => 'st', 'hours' => 'u'],
        'ventilation' => ['hours' => 'u'],
        'esd' => ['hours' => 'u'],
        'pictograms' => ['hours' => 'u'],
        'fuse_box' => ['hours' => 'u'],
        'cable_ladder' => ['hours' => 'u'],
        'emergency_light' => ['light' => 'st', 'cable' => 'm', 'box' => 'st', 'tube' => 'm', 'hours' => 'u'],
        'epoxy' => ['strips' => 'm', 'cable' => 'm', 'sleeve' => 'st', 'tube' => 'm'],
        'temperature' => ['cable' => 'm', 'hours' => 'u'],
        'air_grille' => ['hours' => 'u', 'grille' => 'st'],
    ];

    /**
     * The points of the checklist, the same in every battery room; some come with their pictogram.
     *
     * @var array<string, ?string>
     */
    public const CHECKLIST = [
        'lighting' => null,
        'ventilation' => null,
        'esd' => null,
        'btv' => null,
        'picto_room' => 'pdf/battery-room-picto-room.png',
        'picto_wet' => 'pdf/battery-room-picto-wet.png',
        'picto_exit' => 'pdf/battery-room-picto-exit.png',
        'fuse_box' => null,
        'cable_ladder' => null,
        'emergency_light' => null,
        'epoxy' => null,
        'temperature' => null,
        'air_grille' => null,
        'other' => null,
    ];

    public const MAX_PHOTOS = 40;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @param  Builder<BatteryRoomDossier>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('project', fn (Builder $query) => $query->visibleTo($user));
    }

    /**
     * The line of explanation under a section of the delivery note, if it has one.
     */
    public static function sectionText(string $section): ?string
    {
        $key = "battery_rooms.sections.{$section}.text";
        $text = __($key);

        return is_string($text) && $text !== $key && $text !== '' ? $text : null;
    }

    public function quantity(string $section, string $line): float
    {
        return (float) (((array) $this->quantities)[$section][$line] ?? 0);
    }

    /**
     * "0,25" or "1": a quantity as printed on the delivery note.
     */
    public function formattedQuantity(string $section, string $line): string
    {
        $value = $this->quantity($section, $line);

        return rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',') ?: '0';
    }

    public function isOk(string $point): bool
    {
        return (bool) (((array) $this->checklist)[$point]['ok'] ?? false);
    }

    public function action(string $point): ?string
    {
        $action = trim((string) (((array) $this->checklist)[$point]['action'] ?? ''));

        return $action !== '' ? $action : null;
    }

    /**
     * How many points are OK, e.g. [12, 14].
     *
     * @return array{0: int, 1: int}
     */
    public function checklistProgress(): array
    {
        return [count(array_filter(array_keys(self::CHECKLIST), fn (string $point): bool => $this->isOk($point))), count(self::CHECKLIST)];
    }

    /**
     * "Ref. klant": filled in by hand, else the (first) PO number of the project, else null ("in aanvraag").
     */
    public function clientReference(): ?string
    {
        return filled($this->client_reference) ? $this->client_reference : $this->project?->poNumbers()->value('number');
    }

    public function reference(): string
    {
        return $this->project?->reference ?? '—';
    }

    public function cowCode(): ?string
    {
        return $this->project?->workSite?->cow_code ?? $this->project?->cow_code;
    }

    /**
     * "14EIN A-1A006": COW code and room number, at the top of the checklist.
     */
    public function cowAndRoom(): string
    {
        return trim(implode(' ', array_filter([$this->cowCode(), $this->room_number])));
    }

    /**
     * "609/0001": category and number of the project, as dossier number on the delivery note.
     */
    public function dossierNumber(): string
    {
        $project = $this->project;

        return $project?->category ? $project->category->code.'/'.$project->category->numbering->formatNumber((int) $project->number) : (string) $project?->reference;
    }

    /**
     * Dutch in Flanders, the Brussels language of Settings > Batteries in Brussels, unless chosen.
     */
    public function documentLocale(): string
    {
        if (in_array($this->document_locale, self::DOCUMENT_LOCALES, true)) {
            return $this->document_locale;
        }

        $site = $this->project?->workSite;

        return BatteryDossier::regionLocale($site?->postal_code ?? $this->project?->postal_code, $site?->area?->name);
    }

    /**
     * The stored file of an upload ("ventilation" or "btv"), or of a photo by its position.
     */
    public function filePath(string $kind, ?int $index = null): ?string
    {
        return match ($kind) {
            'ventilation' => $this->ventilation_file,
            'btv' => $this->btv_file,
            'photo' => $index !== null ? (((array) $this->photos)[$index] ?? null) : null,
            default => null,
        };
    }

    public function fileName(string $kind, ?int $index = null): ?string
    {
        $path = $this->filePath($kind, $index);

        return match ($kind) {
            'ventilation' => $this->ventilation_file_name,
            'btv' => $this->btv_file_name,
            default => null,
        } ?: ($path ? basename($path) : null);
    }

    protected static function booted(): void
    {
        // Replaced or removed uploads go from the disk too.
        static::updated(function (self $dossier): void {
            $disk = Storage::disk(self::DISK);

            foreach (['ventilation_file', 'btv_file'] as $field) {
                if ($dossier->wasChanged($field) && filled($old = $dossier->getOriginal($field))) {
                    $disk->delete($old);
                }
            }

            if ($dossier->wasChanged('photos')) {
                $disk->delete(array_values(array_diff((array) json_decode((string) $dossier->getRawOriginal('photos'), true), (array) $dossier->photos)));
            }
        });

        static::deleted(fn (self $dossier) => Storage::disk(self::DISK)->delete(array_values(array_filter([...(array) $dossier->photos, $dossier->ventilation_file, $dossier->btv_file]))));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'note_date' => 'date',
            'checklist_date' => 'date',
            'quantities' => 'array',
            'checklist' => 'array',
            'photos' => 'array',
        ];
    }
}
