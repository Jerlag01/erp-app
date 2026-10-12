<?php

namespace App\Models;

use App\Enums\AssetCheckResult;
use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The yearly equipment control of one team: the team's equipment (directly or on a team vehicle),
 * each marked OK / not OK by the team. The team only sees the list while it is open.
 */
#[Fillable(['team_id', 'year', 'opened_at', 'opened_by', 'closed_at', 'closed_by'])]
class AssetCheck extends Model
{
    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return HasMany<AssetCheckItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(AssetCheckItem::class);
    }

    public function isOpen(): bool
    {
        return $this->opened_at !== null && $this->closed_at === null;
    }

    /**
     * @param  Builder<AssetCheck>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNotNull('opened_at')->whereNull('closed_at');
    }

    /**
     * Adds the team's equipment that is not on the list yet (retired equipment is left out).
     * Returns how many were added.
     */
    public function syncItems(): int
    {
        $present = $this->items()->pluck('asset_id')->all();

        $assets = Asset::query()
            ->where('status', '!=', AssetStatus::Retired)
            ->where(fn (Builder $query) => $query
                ->where('team_id', $this->team_id)
                ->orWhereIn('vehicle_id', Vehicle::query()->select('id')->where('team_id', $this->team_id)))
            ->whereNotIn('id', $present)
            ->pluck('id');

        $assets->each(fn (int $assetId) => $this->items()->create(['asset_id' => $assetId]));

        return $assets->count();
    }

    public function checkedCount(): int
    {
        return $this->items->whereNotNull('result')->count();
    }

    public function notOkCount(): int
    {
        return $this->items->where('result', AssetCheckResult::NotOk)->count();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
