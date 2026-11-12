<?php

namespace App\Jobs;

use App\Actions\RefreshStockItemFromDistributor;
use App\Models\StockItem;
use App\Services\Distributors\DistributorException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches the price of a new stock item from its manufacturer's distributors. Dispatched after the
 * response (StockItem::booted), so saving the item does not wait for the distributor and the suppliers
 * entered in the form are saved first.
 */
class FetchStockItemPrice
{
    use Dispatchable;

    public function __construct(public int $stockItemId) {}

    public function handle(RefreshStockItemFromDistributor $refresh): void
    {
        $item = StockItem::query()->find($this->stockItemId);

        if ($item === null || ! $refresh->canFetch($item)) {
            return;
        }

        try {
            $refresh->handle($item);
        } catch (DistributorException $exception) {
            Log::info('The price of a new stock item could not be fetched', ['stock_item' => $item->id, 'error' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            Log::warning('The price of a new stock item could not be fetched', ['stock_item' => $item->id, 'error' => $exception->getMessage()]);
        }
    }
}
