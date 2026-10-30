<?php

namespace App\Support;

use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The yearly count (stocktake). While an administrator has it switched on, stock can only be counted:
 * adding and taking are blocked on the platform and in the app. Each stock row counted since the start
 * is marked, so the progress shows.
 */
class StockCount
{
    public function active(): bool
    {
        return (bool) settings()->get('stock.yearly_count', false);
    }

    public function startedAt(): ?Carbon
    {
        return filled($at = settings()->get('stock.yearly_count_started_at')) ? Carbon::parse($at) : null;
    }

    public function start(User $user): void
    {
        StockLevel::query()->whereNotNull('counted_at')->update(['counted_at' => null]);
        settings()->save('stock', [...(array) settings('stock', []), 'yearly_count' => true, 'yearly_count_started_at' => now()->toIso8601String(), 'yearly_count_started_by' => $user->id]);
    }

    public function stop(): void
    {
        settings()->save('stock', [...(array) settings('stock', []), 'yearly_count' => false]);
    }

    /**
     * @return array{counted: int, total: int}
     */
    public function progress(): array
    {
        $levels = StockLevel::query()->whereHas('item', fn ($query) => $query->where('is_active', true));

        return ['counted' => (clone $levels)->whereNotNull('counted_at')->count(), 'total' => $levels->count()];
    }
}
