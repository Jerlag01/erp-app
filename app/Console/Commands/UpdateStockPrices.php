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
use Illuminate\Support\Sleep;

/**
 * Fetches the current price of every active item from the distributors of its manufacturer (else its
 * own suppliers) that have a working connection; runs every night (routes/console.php). A distributor of
 * the manufacturer that is not a supplier of the item yet is added, and its article number is filled in
 * by the first answer. A short pause between the items keeps the load on the distributors low.
 */
#[Signature('stock:update-prices
    {--sleep=250 : milliseconds to wait between two items}')]
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
            ->where(fn (Builder $query) => $query
                ->whereHas('suppliers', fn (Builder $query) => $query->whereIn('distributor_id', $distributorIds))
                ->orWhereHas('manufacturer.distributors', fn (Builder $query) => $query->whereIn('distributors.id', $distributorIds)))
            ->with('manufacturer')
            ->chunkById(100, function ($items) use ($refresh, &$updated, &$failed): void {
                foreach ($items as $item) {
                    if (! $refresh->canFetch($item)) {
                        continue;
                    }

                    if ($updated + $failed > 0) {
                        Sleep::usleep(max(0, (int) $this->option('sleep')) * 1000);
                    }

                    try {
                        foreach ($refresh->handle($item) as $name => $message) {
                            $this->components->warn("{$item->name} ({$name}): {$message}");
                        }
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
