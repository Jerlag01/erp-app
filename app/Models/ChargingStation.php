<?php

namespace App\Models;

use App\Enums\ChargingConnectivity;
use App\Enums\ChargingStationType;
use App\Enums\ChargingSystem;
use Database\Factories\ChargingStationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A charging station at a COW site (608 projects). Address and city come from the site. The
 * password and recovery code are encrypted and only shown with "charging.secrets".
 */
#[Fillable([
    'work_site_id', 'project_id', 'fully_executed', 'ace_number', 'password', 'recovery_code', 'sim_ean', 'sim_ssn',
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
     * Street and number, postal code and city of the COW site.
     */
    public function address(): ?string
    {
        $site = $this->workSite;

        return $site ? implode(', ', array_filter([
            trim(implode(' ', array_filter([$site->street, $site->house_number, $site->addition]))),
            trim(implode(' ', array_filter([$site->postal_code, $site->city]))),
        ])) ?: null : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
