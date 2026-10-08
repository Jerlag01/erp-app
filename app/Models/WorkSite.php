<?php

namespace App\Models;

use App\Enums\BuildingType;
use App\Models\Concerns\DeletesStoredFiles;
use Database\Factories\WorkSiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A work site location identified by its COW code (e.g. "02GAM"), with address, building type,
 * area, contact person, pictures, free remarks and a log of building type changes.
 */
#[Fillable([
    'cow_code', 'building_type', 'country_id', 'street', 'house_number', 'addition', 'postal_code', 'city', 'state',
    'work_site_area_id', 'work_site_contact_id', 'images',
])]
class WorkSite extends Model
{
    use DeletesStoredFiles;

    /** @use HasFactory<WorkSiteFactory> */
    use HasFactory;

    /**
     * Two digits followed by three letters, e.g. "02GAM".
     */
    public const COW_CODE_PATTERN = '/^\d{2}[A-Za-z]{3}$/';

    public function storedFiles(): array
    {
        return ['images'];
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<WorkSiteArea, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(WorkSiteArea::class, 'work_site_area_id');
    }

    /**
     * The site's own contact person (see effectiveContact() for the fallback to the area).
     *
     * @return BelongsTo<WorkSiteContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(WorkSiteContact::class, 'work_site_contact_id');
    }

    /**
     * @return HasMany<WorkSiteRemark, $this>
     */
    public function remarks(): HasMany
    {
        return $this->hasMany(WorkSiteRemark::class)->latest()->latest('id');
    }

    /**
     * @return HasMany<WorkSiteTypeChange, $this>
     */
    public function typeChanges(): HasMany
    {
        return $this->hasMany(WorkSiteTypeChange::class)->latest('changed_on')->latest('id');
    }

    /**
     * The site's own contact person, or else the contact person of its area.
     */
    public function effectiveContact(): ?WorkSiteContact
    {
        return $this->contact ?? $this->area?->contact;
    }

    public function isDecommissioned(): bool
    {
        return $this->building_type === BuildingType::Decommissioned;
    }

    /**
     * Changes the building type and logs it (with the details from the change form).
     * The first type of a new site is logged the same way, without a previous type.
     */
    public function changeBuildingType(BuildingType $type, string $changedOn, ?string $reason = null, ?string $remarks = null): WorkSiteTypeChange
    {
        return DB::transaction(function () use ($type, $changedOn, $reason, $remarks): WorkSiteTypeChange {
            $from = $this->exists ? $this->getOriginal('building_type') : null;

            $this->update(['building_type' => $type]);

            return $this->typeChanges()->create([
                'from_type' => $from instanceof BuildingType ? $from->value : $from,
                'to_type' => $type->value,
                'changed_on' => $changedOn,
                'reason' => $reason,
                'remarks' => $remarks,
                'user_id' => auth()->id(),
            ]);
        });
    }

    /**
     * "Rue de la Gare 5 bus 2, 4000 Liège, België".
     */
    public function addressLine(): ?string
    {
        $streetLine = trim(implode(' ', array_filter([$this->street, $this->house_number])).(filled($this->addition) ? ' '.$this->addition : ''));
        $place = trim(implode(' ', array_filter([$this->postal_code, $this->city])));

        return implode(', ', array_filter([$streetLine, $place, $this->country?->localName()])) ?: null;
    }

    /**
     * The address without the addition (box/floor), which only confuses navigation apps.
     */
    protected function searchableAddress(): ?string
    {
        $parts = array_filter([
            trim(implode(' ', array_filter([$this->street, $this->house_number]))),
            trim(implode(' ', array_filter([$this->postal_code, $this->city]))),
            $this->country?->localName('en'),
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    public function googleMapsUrl(): ?string
    {
        $address = $this->searchableAddress();

        return $address ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address) : null;
    }

    public function wazeUrl(): ?string
    {
        $address = $this->searchableAddress();

        return $address ? 'https://waze.com/ul?q='.rawurlencode($address).'&navigate=yes' : null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $site): void {
            $site->cow_code = strtoupper(trim((string) $site->cow_code));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'building_type' => BuildingType::class,
            'images' => 'array',
        ];
    }
}
