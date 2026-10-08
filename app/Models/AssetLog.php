<?php

namespace App\Models;

use App\Enums\AssetLogType;
use App\Enums\DamageSeverity;
use App\Models\Concerns\DeletesStoredFiles;
use Database\Factories\AssetLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One entry in an asset's history: a damage, a repair (optionally linked to the
 * damage it fixes), an inspection or a plain note.
 */
#[Fillable([
    'asset_id', 'type', 'date', 'title', 'description', 'severity', 'damage_id',
    'resolved_at', 'performed_by', 'cost', 'attachments', 'user_id',
])]
class AssetLog extends Model
{
    use DeletesStoredFiles;

    /** @use HasFactory<AssetLogFactory> */
    use HasFactory;

    public function storedFiles(): array
    {
        return ['attachments'];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * The damage a repair fixes.
     *
     * @return BelongsTo<AssetLog, $this>
     */
    public function damage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'damage_id');
    }

    /**
     * The repairs that were done for a damage.
     *
     * @return HasMany<AssetLog, $this>
     */
    public function repairs(): HasMany
    {
        return $this->hasMany(self::class, 'damage_id');
    }

    /**
     * Who reported or recorded the entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpenDamage(): bool
    {
        return $this->type === AssetLogType::Damage && $this->resolved_at === null;
    }

    public function resolve(): void
    {
        $this->update(['resolved_at' => now()]);
    }

    public function reopen(): void
    {
        $this->update(['resolved_at' => null]);
    }

    /**
     * Log entries of the assets the user may see.
     *
     * @param  Builder<AssetLog>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesOnlyOwnTeams()) {
            $query->whereHas('asset', fn (Builder $query) => $query->visibleTo($user));
        }
    }

    protected static function booted(): void
    {
        static::saving(function (self $log): void {
            if ($log->type !== AssetLogType::Damage) {
                $log->severity = null;
                $log->resolved_at = null;
            }

            if ($log->type !== AssetLogType::Repair) {
                $log->damage_id = null;
            }
        });

        static::saved(function (self $log): void {
            // A repair linked to a damage resolves that damage.
            if ($log->type === AssetLogType::Repair && $log->damage_id && ($log->wasRecentlyCreated || $log->wasChanged('damage_id'))) {
                $log->damage()->whereNull('resolved_at')->update(['resolved_at' => $log->date]);
            }

            // An inspection moves the asset's inspection dates forward.
            if ($log->type === AssetLogType::Inspection && $log->wasRecentlyCreated) {
                $asset = $log->asset;

                if ($asset->inspection_date === null || $log->date->gte($asset->inspection_date)) {
                    $months = $asset->category?->inspection_interval_months;

                    $asset->update([
                        'inspection_date' => $log->date,
                        'next_inspection_date' => $months ? $log->date->copy()->addMonthsNoOverflow($months) : $asset->next_inspection_date,
                    ]);
                }
            }

            $log->asset?->syncStatusWithDamages();
        });

        static::deleted(function (self $log): void {
            $log->asset?->syncStatusWithDamages();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AssetLogType::class,
            'severity' => DamageSeverity::class,
            'date' => 'date',
            'resolved_at' => 'datetime',
            'cost' => 'decimal:2',
            'attachments' => 'array',
        ];
    }
}
