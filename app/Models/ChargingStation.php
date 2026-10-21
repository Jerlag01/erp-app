<?php

namespace App\Models;

use App\Enums\ChargingConnectivity;
use App\Enums\ChargingStationType;
use App\Enums\ChargingSystem;
use App\Enums\SiteLocationType;
use Database\Factories\ChargingStationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A charging station at a COW site (608 projects), address and city coming from the site, or at a
 * third-party client with its own name and installation address. The
 * password and recovery code are encrypted and only shown with "charging.secrets".
 */
#[Fillable([
    'work_site_id', 'location_type', 'client_name', 'client_company', 'client_email', 'client_phone',
    'street', 'house_number', 'addition', 'postal_code', 'city', 'country_id', 'project_id', 'fully_executed', 'ace_number', 'password', 'recovery_code', 'sim_ean', 'sim_ssn',
    'connectivity', 'license_key', 'type', 'online', 'backoffice', 'installation_year', 'brand', 'quantity',
    'cluster_number', 'system', 'in_service', 'commissioned_by', 'onsite_warranty_end', 'plans_updated', 'notes',
])]
#[Hidden(['password', 'recovery_code'])]
class ChargingStation extends Model
{
    /** @use HasFactory<ChargingStationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<WorkSite, $this>
     */
    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function isThirdParty(): bool
    {
        return $this->location_type === SiteLocationType::Other;
    }

    /**
     * Street and number, postal code and city: of the COW site, or the third-party installation address.
     */
    public function address(): ?string
    {
        $place = $this->isThirdParty() ? $this : $this->workSite;

        return $place ? implode(', ', array_filter([
            trim(implode(' ', array_filter([$place->street, $place->house_number, $place->addition]))),
            trim(implode(' ', array_filter([$place->postal_code, $place->city]))),
        ])) ?: null : null;
    }

    public function cityName(): ?string
    {
        return $this->isThirdParty() ? $this->city : $this->workSite?->city;
    }

    /**
     * The COW code, or the third-party client's company or name.
     */
    public function locationLabel(): ?string
    {
        return $this->isThirdParty() ? ($this->client_company ?: $this->client_name) : $this->workSite?->cow_code;
    }

    protected static function booted(): void
    {
        // A station is either at a COW site or at a third-party address, never both.
        static::saving(function (self $station): void {
            $station->location_type ??= SiteLocationType::Cow;

            if ($station->isThirdParty()) {
                $station->work_site_id = null;

                return;
            }

            foreach (['client_name', 'client_company', 'client_email', 'client_phone', 'street', 'house_number', 'addition', 'postal_code', 'city', 'country_id'] as $field) {
                $station->{$field} = null;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location_type' => SiteLocationType::class,
            'password' => 'encrypted',
            'recovery_code' => 'encrypted',
            'fully_executed' => 'boolean',
            'online' => 'boolean',
            'in_service' => 'boolean',
            'plans_updated' => 'boolean',
            'connectivity' => ChargingConnectivity::class,
            'type' => ChargingStationType::class,
            'system' => ChargingSystem::class,
            'installation_year' => 'integer',
            'quantity' => 'integer',
            'cluster_number' => 'integer',
            'onsite_warranty_end' => 'date',
        ];
    }
}
