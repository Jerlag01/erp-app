<?php

namespace App\Filament\Resources\StockItems\Pages;

use App\Filament\Resources\StockItems\StockItemResource;
use App\Models\StockItem;
use App\Support\StockCount;
use App\Support\StockExport;
use App\Support\Translation\Translator;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListStockItems extends ListRecords
{
    /** Items translated per click (MyMemory's free limit is per day). */
    public const TRANSLATE_BATCH = 50;

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
            // Items from before (or made while translation was off): a batch at a time, because of the daily limit.
            Action::make('translateMissing')
                ->label(__('erp.stock.category_names.translate_missing'))
                ->icon(Heroicon::OutlinedLanguage)
                ->color('gray')
                ->visible(fn (): bool => app(Translator::class)->enabled()
                    && auth()->user()?->can('update', StockItem::query()->missingTranslations()->first() ?? new StockItem)
                    && StockItem::query()->missingTranslations()->exists())
                ->requiresConfirmation()
                ->modalDescription(fn (): string => __('erp.stock.item_names.translate_help', ['count' => StockItem::query()->missingTranslations()->count(), 'batch' => self::TRANSLATE_BATCH]))
                ->action(function (): void {
                    $count = 0;
                    StockItem::query()->missingTranslations()->orderBy('id')->limit(self::TRANSLATE_BATCH)->get()->each(function (StockItem $item) use (&$count): void {
                        $item->translateNames();
                        $count += (int) $item->isDirty();
                        $item->save();
                    });
                    Notification::make()->title(__('erp.stock.item_names.translated', ['count' => $count, 'left' => StockItem::query()->missingTranslations()->count()]))->success()->send();
                }),
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
