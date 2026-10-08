<?php

namespace App\Models;

use App\Enums\ItStatusType;
use App\Models\Concerns\DeletesStoredFiles;
use Carbon\CarbonInterface;
use Database\Factories\ItAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A piece of IT hardware (laptop, monitor, phone…). It can be checked out to a user,
 * a location or another asset (a monitor on a desktop), like in Snipe-IT.
 */
#[Fillable([
    'asset_tag', 'name', 'it_model_id', 'serial', 'it_status_label_id', 'location_id',
    'assigned_type', 'assigned_id', 'assigned_at', 'expected_checkin',
    'it_supplier_id', 'order_number', 'purchase_date', 'purchase_cost', 'warranty_months',
    'last_audit_date', 'next_audit_date', 'hostname', 'ip_address', 'mac_address', 'operating_system',
    'specs', 'requestable', 'image', 'notes',
])]
class ItAsset extends Model
{
    use DeletesStoredFiles;

    /** @use HasFactory<ItAssetFactory> */
    use HasFactory;

    public function storedFiles(): array
    {
        return ['image'];
    }

    /**
     * @return BelongsTo<ItModel, $this>
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(ItModel::class, 'it_model_id');
    }

    /**
     * @return BelongsTo<ItStatusLabel, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(ItStatusLabel::class, 'it_status_label_id');
    }

    /**
     * Where the asset is kept (its default location).
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<ItSupplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(ItSupplier::class, 'it_supplier_id');
    }

    /**
     * Who or what the asset is checked out to: a User, a Location or another ItAsset.
     *
     * @return MorphTo<Model, $this>
     */
    public function assigned(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Assets checked out to this asset (e.g. monitors on a desktop).
     *
     * @return MorphMany<ItAsset, $this>
     */
    public function children(): MorphMany
    {
        return $this->morphMany(self::class, 'assigned');
    }

    /**
     * @return HasMany<ItMaintenance, $this>
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(ItMaintenance::class)->latest('start_date');
    }

    /**
     * @return HasMany<ItLicenseSeat, $this>
     */
    public function licenseSeats(): HasMany
    {
        return $this->hasMany(ItLicenseSeat::class);
    }

    /**
     * Components installed in this asset.
     *
     * @return HasMany<ItItemAssignment, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(ItItemAssignment::class)->whereNull('returned_at');
    }

    /**
     * @return MorphMany<ItLog, $this>
     */
    public function logs(): MorphMany
    {
        return $this->morphMany(ItLog::class, 'loggable')->latest('created_at')->latest('id');
    }

    public function isCheckedOut(): bool
    {
        return $this->assigned_type !== null;
    }

    public function isDeployable(): bool
    {
        return $this->status?->type === ItStatusType::Deployable;
    }

    /**
     * "LT-0012 · Dell Latitude 5440".
     */
    public function label(): string
    {
        return trim($this->asset_tag.' · '.($this->name ?: $this->model?->fullName()), ' ·');
    }

    public function warrantyExpires(): ?CarbonInterface
    {
        return $this->purchase_date && $this->warranty_months ? $this->purchase_date->copy()->addMonthsNoOverflow($this->warranty_months) : null;
    }

    public function endOfLife(): ?CarbonInterface
    {
        return $this->purchase_date && $this->model?->eol_months ? $this->purchase_date->copy()->addMonthsNoOverflow($this->model->eol_months) : null;
    }

    /**
     * Name of whoever/whatever has the asset, for lists.
     */
    public function assignedName(): ?string
    {
        return match (true) {
            $this->assigned instanceof User => $this->assigned->name,
            $this->assigned instanceof Location => $this->assigned->name,
            $this->assigned instanceof self => $this->assigned->label(),
            default => null,
        };
    }

    /**
     * Assets the user may see: their own, their team members', those at their teams' locations,
     * or everything for administrators.
     *
     * @param  Builder<ItAsset>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->seesOnlyOwnTeams()) {
            return;
        }

        $people = $user->teamMemberIds();
        $locations = modules()->locations() ? $user->locationIds() : [];

        $query->where(function (Builder $query) use ($people, $locations): void {
            $query->where(fn (Builder $query) => $query->where('it_assets.assigned_type', User::class)->whereIn('it_assets.assigned_id', $people));

            if ($locations !== []) {
                $query->orWhereIn('it_assets.location_id', $locations)
                    ->orWhere(fn (Builder $query) => $query->where('it_assets.assigned_type', Location::class)->whereIn('it_assets.assigned_id', $locations));
            }
        });
    }

    /**
     * @param  Builder<ItAsset>  $query
     */
    public function scopeAuditDue(Builder $query): void
    {
        $query->whereNotNull('next_audit_date')->whereDate('next_audit_date', '<=', today()->addDays(settings()->warningDays()));
    }

    protected static function booted(): void
    {
        static::saving(function (self $asset): void {
            $asset->asset_tag = strtoupper(trim((string) $asset->asset_tag));
            $asset->mac_address = filled($asset->mac_address) ? strtoupper(str_replace('-', ':', $asset->mac_address)) : null;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'expected_checkin' => 'date',
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
            'warranty_months' => 'integer',
            'last_audit_date' => 'date',
            'next_audit_date' => 'date',
            'specs' => 'array',
            'requestable' => 'boolean',
        ];
    }
}
