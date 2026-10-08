<?php

namespace App\Actions;

use App\Models\StockItem;
use App\Services\Distributors\DistributorException;

/**
 * Asks the item's distributor for the current market price (and an image when the item has none).
 */
class RefreshStockItemFromDistributor
{
    public function __construct(protected ImportImageFromUrl $images) {}

    /**
     * @throws DistributorException
     */
    public function handle(StockItem $item): void
    {
        $client = $item->distributor?->client();

        if ($client === null) {
            throw new DistributorException(__('erp.stock.provider.none'));
        }

        if (blank($item->distributor_reference) && blank($item->manufacturer_reference)) {
            throw new DistributorException(__('erp.stock.provider.no_reference'));
        }

        $info = $client->lookup($item);

        if ($info === null) {
            throw new DistributorException(__('erp.stock.provider.not_found', ['name' => $item->distributor->name]));
        }

        if ($info->price !== null) {
            $item->recordPrice($info->price, (string) $item->distributor->price_provider);
        }

        if (blank($item->image) && filled($info->imageUrl)) {
            $item->update(['image' => $this->images->handle($info->imageUrl, 'stock-items')]);
        }
    }
}
