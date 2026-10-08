<?php

namespace App\Console\Commands;

use App\Actions\RefreshStockItemFromDistributor;
use App\Models\Distributor;
use App\Models\StockItem;
use App\Services\Distributors\DistributorException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('stock:update-prices')]
#[Description('Fetch current market prices from the distributors that have a working connection')]
class UpdateStockPrices extends Command
{
    public function handle(RefreshStockItemFromDistributor $refresh): int
    {
        if (! modules()->stock()) {
            $this->components->info('The stock module is switched off.');

            return self::SUCCESS;
        }

        $distributorIds = Distributor::query()->whereNotNull('price_provider')->get()
            ->filter(fn (Distributor $distributor): bool => (bool) $distributor->client()?->isConfigured())
            ->modelKeys();

        if ($distributorIds === []) {
            $this->components->warn('No distributor has a configured price connection yet.');

            return self::SUCCESS;
        }

        $updated = 0;
        $failed = 0;

        StockItem::query()
            ->where('is_active', true)
            ->whereIn('distributor_id', $distributorIds)
            ->where(fn (Builder $query) => $query->whereNotNull('distributor_reference')->orWhereNotNull('manufacturer_reference'))
            ->with('distributor')
            ->chunkById(100, function ($items) use ($refresh, &$updated, &$failed): void {
                foreach ($items as $item) {
                    try {
                        $refresh->handle($item);
                        $updated++;
                    } catch (DistributorException $exception) {
                        $failed++;
                        $this->components->warn("{$item->name}: {$exception->getMessage()}");
                    }
                }
            });

        $this->components->info("Prices updated: {$updated}, failed: {$failed}.");

        return self::SUCCESS;
    }
}
