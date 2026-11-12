<?php

namespace App\Actions;

use App\Models\Distributor;
use App\Models\StockItem;
use App\Models\StockItemSupplier;
use App\Services\Distributors\DistributorException;
use Illuminate\Support\Collection;

/**
 * Fetches the item's current price from the distributors of its manufacturer that have a working
 * connection (Rexel, Cebeo). Such a distributor is added to the item's suppliers when it is not there
 * yet. The price is asked by the manufacturer reference, else by the distributor's own article number;
 * that number is not required: the first answer fills it in. An image is taken when the item has none.
 * Without a manufacturer (or one without distributors) the item's own suppliers with a connection are
 * asked. Suppliers without a connection keep the price entered by hand.
 */
class RefreshStockItemFromDistributor
{
    public function __construct(protected ImportImageFromUrl $images) {}

    /**
     * Whether there is a distributor with a working connection to ask for this item's price.
     */
    public function canFetch(StockItem $item): bool
    {
        return $this->distributors($item)->isNotEmpty();
    }

    /**
     * @return array<string, string> the suppliers that failed, with why
     *
     * @throws DistributorException when no supplier could be asked, or none answered
     */
    public function handle(StockItem $item): array
    {
        $distributors = $this->distributors($item);

        if ($distributors->isEmpty()) {
            throw new DistributorException(__('erp.stock.provider.none'));
        }

        $suppliers = $item->suppliers()->with('distributor')->get();

        foreach ($distributors as $distributor) {
            if (! $suppliers->contains('distributor_id', $distributor->id)) {
                $supplier = $item->suppliers()->create(['distributor_id' => $distributor->id]);
                $suppliers->push($supplier->setRelation('distributor', $distributor));
            }
        }

        $suppliers = $suppliers->filter(fn (StockItemSupplier $supplier): bool => $distributors->contains('id', $supplier->distributor_id));
        $failed = [];
        $answered = 0;

        foreach ($suppliers as $supplier) {
            $name = $supplier->distributor->name;

            try {
                if (blank($supplier->reference) && blank($item->manufacturer_reference)) {
                    throw new DistributorException(__('erp.stock.provider.no_reference'));
                }

                // Only this supplier's own number: the item's one may be another distributor's.
                $info = $supplier->distributor->client()->lookup($item, (string) $supplier->reference);

                if ($info === null) {
                    throw new DistributorException(__('erp.stock.provider.not_found', ['name' => $name]));
                }

                $answered++;

                $supplier->update(array_filter([
                    // The distributor's article number the first time it is known.
                    'reference' => blank($supplier->reference) && filled($info->reference) ? mb_substr($info->reference, 0, 100) : null,
                    ...($info->price !== null ? ['price' => $info->price, 'price_source' => (string) $supplier->distributor->price_provider, 'price_updated_at' => now()] : []),
                ], fn ($value): bool => $value !== null));

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

    /**
     * The distributors to ask: always those of the manufacturer when it has any, else the item's own
     * suppliers; only the ones with a connection (one that is not set up yet says what is missing).
     *
     * @return Collection<int, Distributor>
     */
    public function distributors(StockItem $item): Collection
    {
        $manufacturer = $item->manufacturer;
        $distributors = $manufacturer !== null && $manufacturer->distributors()->exists()
            ? $manufacturer->distributors()->get()
            : Distributor::query()->whereIn('id', $item->suppliers()->select('distributor_id'))->get();

        return $distributors->filter(fn (Distributor $distributor): bool => $distributor->client() !== null)->values();
    }
}
