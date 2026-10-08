<?php

namespace App\Models;

use App\Enums\AssetLogType;
use App\Enums\AssetStatus;
use App\Models\Concerns\DeletesStoredFiles;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'asset_tag', 'name', 'asset_category_id', 'brand', 'model', 'serial_number', 'status',
    'purchase_date', 'purchase_price', 'warranty_until', 'inspection_date', 'next_inspection_date',
    'team_id', 'vehicle_id', 'user_id', 'location_id', 'photo', 'notes',
])]
class Asset extends Model
{
    use DeletesStoredFiles;

    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    public function storedFiles(): array
    {
        return ['photo'];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<AssetCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * The employee the asset is handed out to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AssetLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(AssetLog::class)->latest('date')->latest('id');
    }

    /**
     * Damages that have not been repaired or resolved yet.
     *
     * @return HasMany<AssetLog, $this>
     */
    public function openDamages(): HasMany
    {
        return $this->hasMany(AssetLog::class)
            ->where('type', AssetLogType::Damage)
            ->whereNull('resolved_at');
    }

    /**
     * Follow the damage log: an asset with open damages is "damaged", and goes back
     * into service once they are all resolved. Out of service and retired are left alone.
     */
    public function syncStatusWithDamages(): void
    {
        $hasOpenDamage = $this->openDamages()->exists();

        $status = match (true) {
            $hasOpenDamage && $this->status === AssetStatus::InService => AssetStatus::Damaged,
            ! $hasOpenDamage && in_array($this->status, [AssetStatus::Damaged, AssetStatus::InRepair], true) => AssetStatus::InService,
            default => $this->status,
        };

        if ($status !== $this->status) {
            $this->update(['status' => $status]);
        }
    }

    /**
     * Assets whose next inspection is overdue or falls within the warning period.
     *
     * @param  Builder<Asset>  $query
     */
    public function scopeInspectionDue(Builder $query): void
    {
        $query->whereNotNull('next_inspection_date')
            ->whereDate('next_inspection_date', '<=', today()->addDays(settings()->warningDays()))
            ->where('status', '!=', AssetStatus::Retired);
    }

    /**
     * Assets the user works with: handed to them, to one of their teams, or on a vehicle
     * they drive or that belongs to one of their teams.
     *
     * @param  Builder<Asset>  $query
     */
    public function scopeUsedBy(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id);

            if (modules()->teams()) {
                $query->orWhereIn('team_id', $user->teams()->select('teams.id'));
            }

            if (modules()->fleet()) {
                $query->orWhereIn('vehicle_id', $user->vehicles()->select('id'));

                if (modules()->teams()) {
                    $query->orWhereIn('vehicle_id', Vehicle::query()->select('id')->whereIn('team_id', $user->teams()->select('teams.id')));
                }
            }
        });
    }

    /**
     * Assets the user may see: those of their teams (directly or on a team vehicle)
     * and the ones handed to them personally.
     *
     * @param  Builder<Asset>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->seesOnlyOwnTeams()) {
            return;
        }

        $teamIds = $user->teamIds();

        $query->where(function (Builder $query) use ($user, $teamIds): void {
            $query->whereIn('assets.team_id', $teamIds)
                ->orWhere('assets.user_id', $user->id);

            if (modules()->fleet()) {
                $query->orWhereIn('assets.vehicle_id', Vehicle::query()->select('id')->whereIn('team_id', $teamIds));
            }
        });
    }

    protected static function booted(): void
    {
        static::saving(function (self $asset): void {
            $asset->asset_tag = strtoupper(trim((string) $asset->asset_tag));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
            'warranty_until' => 'date',
            'inspection_date' => 'date',
            'next_inspection_date' => 'date',
        ];
    }
}
