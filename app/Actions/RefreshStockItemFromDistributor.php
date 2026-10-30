<?php

namespace App\Actions;

use App\Models\StockItem;
use App\Models\StockItemSupplier;
use App\Services\Distributors\DistributorException;

/**
 * Asks each supplier of the item that has a working connection (Rexel, Cebeo) for its current price,
 * with that supplier's article number, and takes an image when the item has none. Suppliers without a
 * connection keep the price entered by hand.
 */
class RefreshStockItemFromDistributor
{
    public function __construct(protected ImportImageFromUrl $images) {}

    /**
     * @return array<string, string> the suppliers that failed, with why
     *
     * @throws DistributorException when no supplier could be asked, or none answered
     */
    public function handle(StockItem $item): array
    {
        $suppliers = $item->suppliers()->with('distributor')->get()
            ->filter(fn (StockItemSupplier $supplier): bool => $supplier->distributor?->client() !== null);

        if ($suppliers->isEmpty()) {
            throw new DistributorException(__('erp.stock.provider.none'));
        }

        $failed = [];
        $answered = 0;

        foreach ($suppliers as $supplier) {
            $name = $supplier->distributor->name;

            try {
                if (blank($supplier->reference) && blank($item->manufacturer_reference)) {
                    throw new DistributorException(__('erp.stock.provider.no_reference'));
                }

                $info = $supplier->distributor->client()->lookup($item, $supplier->reference);

                if ($info === null) {
                    throw new DistributorException(__('erp.stock.provider.not_found', ['name' => $name]));
                }

                $answered++;

                if ($info->price !== null) {
                    $supplier->update(['price' => $info->price, 'price_source' => (string) $supplier->distributor->price_provider, 'price_updated_at' => now()]);
                }

                if (blank($item->image) && filled($info->imageUrl)) {
                    $item->update(['image' => $this->images->handle($info->imageUrl, 'stock-items')]);
                }
            } catch (DistributorException $exception) {
                $failed[$name] = $exception->getMessage();
            }
        }

        if ($answered === 0) {
            throw new DistributorException(implode(' ', array_map(fn (string $name, string $message): string => $suppliers->count() > 1 ? "{$name}: {$message}" : $message, array_keys($failed), $failed)));
        }

        return $failed;
    }
}
