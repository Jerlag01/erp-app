<?php

namespace App\Filament\Resources\StockItems\Pages;

use App\Filament\Resources\StockItems\StockItemResource;
use App\Support\StockExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListStockItems extends ListRecords
{
    protected static string $resource = StockItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The items shown (search and filters) with the stock per location and the prices per supplier.
            Action::make('export')
                ->label(__('erp.stock.export.action'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->hasPermission('stock_prices.export'))
                ->action(function (): BinaryFileResponse {
                    abort_unless((bool) auth()->user()?->hasPermission('stock_prices.export'), 403);

                    return response()->download(app(StockExport::class)->xlsx($this->getFilteredSortedTableQuery()->get()), StockExport::filename())->deleteFileAfterSend();
                }),
            CreateAction::make(),
        ];
    }
}
