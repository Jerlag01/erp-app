<?php

namespace App\Support;

use App\Filament\Resources\StockItems\Tables\StockItemsTable;
use App\Models\Distributor;
use App\Models\Location;
use App\Models\StockItem;
use App\Models\StockItemSupplier;
use Illuminate\Support\Collection;

/**
 * The stock list as Excel, for those with "stock_prices.export": per item its details, the quantity at
 * each location the user sees and in total, the market price, and per supplier the article number, the
 * price and where it came from.
 */
class StockExport
{
    public function __construct(protected ChargingExport $writer) {}

    /**
     * Writes the file and returns its temporary path.
     *
     * @param  Collection<int, StockItem>  $items
     */
    public function xlsx(Collection $items): string
    {
        $items->loadMissing(['unit', 'category.parent', 'manufacturer', 'suppliers.distributor', 'levels']);
        $locations = StockItemsTable::visibleLocations();
        $distributors = Distributor::query()
            ->whereKey($items->flatMap(fn (StockItem $item) => $item->suppliers->pluck('distributor_id'))->unique()->all())
            ->orderBy('name')
            ->get();
        $number = fn (mixed $value): ?float => $value === null ? null : (float) $value;
        $supplier = fn (StockItem $item, Distributor $distributor): ?StockItemSupplier => $item->suppliers->firstWhere('distributor_id', $distributor->id);

        $columns = [
            __('erp.stock.names.nl') => fn (StockItem $item): ?string => $item->name_nl,
            __('erp.stock.names.fr') => fn (StockItem $item): ?string => $item->name_fr,
            __('erp.stock.names.en') => fn (StockItem $item): ?string => $item->name_en,
            __('erp.fields.category') => fn (StockItem $item): ?string => $item->category?->fullName(),
            __('erp.resources.manufacturer.singular') => fn (StockItem $item): ?string => $item->manufacturer?->name,
            __('erp.fields.manufacturer_reference') => fn (StockItem $item): ?string => $item->manufacturer_reference,
            __('erp.fields.ean') => fn (StockItem $item): ?string => $item->ean,
            __('erp.fields.unit') => fn (StockItem $item): ?string => $item->unit?->abbreviation ?: $item->unit?->name,
        ];

        foreach ($locations as $location) {
            $columns[$location->code ?: $location->name] = fn (StockItem $item): float => (float) $item->levels->firstWhere('location_id', $location->id)?->quantity;
        }

        $columns[__('erp.stock.export.total')] = fn (StockItem $item): float => (float) $item->levels->whereIn('location_id', $locations->modelKeys())->sum('quantity');
        $columns[__('erp.stock.export.preferred')] = fn (StockItem $item): ?string => $item->suppliers->firstWhere('is_preferred', true)?->distributor?->name;
        $columns[__('erp.fields.market_price')] = fn (StockItem $item): ?float => $number($item->market_price);

        foreach ($distributors as $distributor) {
            $columns[__('erp.stock.export.reference', ['name' => $distributor->name])] = fn (StockItem $item): ?string => $supplier($item, $distributor)?->reference;
            $columns[__('erp.stock.export.price', ['name' => $distributor->name])] = fn (StockItem $item): ?float => $number($supplier($item, $distributor)?->price);
            $columns[__('erp.stock.export.price_date', ['name' => $distributor->name])] = fn (StockItem $item): ?string => ($found = $supplier($item, $distributor))?->price_updated_at
                ? $found->price_updated_at->format('d/m/Y').' ('.($found->price_source === StockItemSupplier::MANUAL ? __('erp.stock.suppliers.manual') : $found->price_source).')'
                : null;
        }

        return $this->writer->xlsx(__('erp.stock.export.title'), $columns, $items);
    }

    public static function filename(): string
    {
        return ChargingExport::filename(__('erp.stock.export.title'), 'xlsx');
    }
}
