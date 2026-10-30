<?php

namespace App\Filament\Resources\StockItems\Pages;

use App\Filament\Resources\StockItems\StockItemResource;
use App\Support\StockCount;
use App\Support\StockExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListStockItems extends ListRecords
{
    protected static string $resource = StockItemResource::class;

    public function getSubheading(): ?string
    {
        $count = app(StockCount::class);

        return $count->active()
            ? __('erp.stock.yearly_count.running', ['date' => $count->startedAt()?->format('d/m/Y H:i') ?? '—', ...$count->progress()])
            : null;
    }

    protected function getHeaderActions(): array
    {
        $count = app(StockCount::class);

        return [
            // The yearly count: while it runs, stock can only be counted (platform and app).
            Action::make('startYearlyCount')
                ->label(__('erp.stock.yearly_count.start'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('warning')
                ->visible(fn (): bool => (bool) auth()->user()?->isAdmin() && ! $count->active())
                ->requiresConfirmation()
                ->modalDescription(__('erp.stock.yearly_count.start_help'))
                ->action(function () use ($count): void {
                    abort_unless((bool) auth()->user()?->isAdmin(), 403);
                    $count->start(auth()->user());
                    Notification::make()->title(__('erp.stock.yearly_count.started'))->success()->send();
                }),
            Action::make('stopYearlyCount')
                ->label(__('erp.stock.yearly_count.stop'))
                ->icon(Heroicon::OutlinedStopCircle)
                ->color('danger')
                ->visible(fn (): bool => (bool) auth()->user()?->isAdmin() && $count->active())
                ->requiresConfirmation()
                ->modalDescription(fn (): string => __('erp.stock.yearly_count.stop_help', $count->progress()))
                ->action(function () use ($count): void {
                    abort_unless((bool) auth()->user()?->isAdmin(), 403);
                    $count->stop();
                    Notification::make()->title(__('erp.stock.yearly_count.stopped'))->success()->send();
                }),
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
