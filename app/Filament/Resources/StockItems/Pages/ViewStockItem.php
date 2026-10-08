<?php

namespace App\Filament\Resources\StockItems\Pages;

use App\Actions\ImportImageFromUrl;
use App\Actions\RefreshStockItemFromDistributor;
use App\Filament\Resources\StockItems\StockItemResource;
use App\Models\StockItem;
use App\Services\Distributors\DistributorException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * The page a QR code opens: details, stock per location with + and −, and the history.
 */
class ViewStockItem extends ViewRecord
{
    protected static string $resource = StockItemResource::class;

    protected function getHeaderActions(): array
    {
        $canUpdate = fn (): bool => (bool) auth()->user()?->can('update', $this->getRecord());

        return [
            EditAction::make(),
            ActionGroup::make([
                Action::make('refreshFromDistributor')
                    ->label(__('erp.stock.refresh_price'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (): bool => $canUpdate() && (bool) auth()->user()?->canSeePrices())
                    ->disabled(fn (StockItem $record): bool => $record->distributor?->client() === null)
                    ->tooltip(fn (StockItem $record): ?string => $record->distributor?->client() === null ? __('erp.stock.provider.none') : null)
                    ->action(function (StockItem $record): void {
                        try {
                            app(RefreshStockItemFromDistributor::class)->handle($record);
                            Notification::make()->title(__('erp.stock.price_refreshed'))->success()->send();
                        } catch (DistributorException $exception) {
                            Notification::make()->title(__('erp.stock.provider.failed'))->body($exception->getMessage())->warning()->persistent()->send();
                        }

                        $this->refreshFormData(['market_price', 'image']);
                    }),
                Action::make('imageFromUrl')
                    ->label(__('erp.stock.image.from_url'))
                    ->icon(Heroicon::OutlinedPhoto)
                    ->visible($canUpdate)
                    ->modalDescription(__('erp.stock.image.from_url_help'))
                    ->schema([
                        TextInput::make('image_url')
                            ->label(__('erp.stock.image.url'))
                            ->url()
                            ->required(),
                    ])
                    ->action(function (StockItem $record, array $data): void {
                        $record->update(['image' => app(ImportImageFromUrl::class)->handle($data['image_url'], 'stock-items')]);
                        $this->refreshFormData(['image']);
                    })
                    ->successNotificationTitle(__('erp.stock.image.saved')),
                Action::make('printLabel')
                    ->label(__('erp.stock.print_label'))
                    ->icon(Heroicon::OutlinedQrCode)
                    ->url(fn (StockItem $record): string => route('stock.labels', ['items' => $record->id]), shouldOpenInNewTab: true),
            ])->button()->label(__('erp.actions.more'))->color('gray'),
        ];
    }
}
