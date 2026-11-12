<?php

namespace App\Console\Commands;

use App\Actions\Purchasing\SyncDistributorOrders;
use App\Models\Distributor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Fetches the orders of every distributor whose API offers them (Purchasing > Orders).
 */
#[Signature('distributors:sync-orders')]
#[Description('Fetch the orders placed at the distributors with an API')]
class SyncDistributorOrdersCommand extends Command
{
    public function handle(SyncDistributorOrders $sync): int
    {
        $failed = false;

        foreach (Distributor::query()->whereNotNull('price_provider')->orderBy('name')->get() as $distributor) {
            if (! $sync->supports($distributor)) {
                continue;
            }

            try {
                $this->components->info($distributor->name.': '.$sync->handle($distributor).' order(s).');
            } catch (Throwable $exception) {
                $failed = true;
                $this->components->error($distributor->name.': '.$exception->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
