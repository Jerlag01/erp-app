<?php

namespace App\Filament\Resources\DistributorOrders\Pages;

use App\Actions\Purchasing\ImportDistributorBasket;
use App\Actions\Purchasing\RecordDistributorDeliveries;
use App\Actions\Purchasing\SyncDistributorOrders;
use App\Filament\Resources\DistributorOrders\DistributorOrderResource;
use App\Models\Distributor;
use App\Models\DistributorOrder;
use App\Models\OrderReference;
use App\Services\Distributors\CebeoClient;
use App\Services\Distributors\DistributorClients;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * A tab per distributor with an API (and one with every order). Rexel and Cebeo cannot list placed
 * orders, so on their tab the webshop basket is imported as an order and (Cebeo) a delivery note is
 * fetched by its number; deliveries also come in from their Peppol invoices. "Fetch orders" only shows
 * for a distributor whose API lists orders.
 */
class ListDistributorOrders extends ListRecords
{
    protected static string $resource = DistributorOrderResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('erp.distributor_orders.all')),
            ...DistributorOrderResource::apiDistributors()->mapWithKeys(fn (Distributor $distributor): array => ['distributor-'.$distributor->id => Tab::make($distributor->name)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('distributor_id', $distributor->id))
                ->badge(fn (): ?int => ($count = $distributor->orders()->whereNotIn('status', ['delivered', 'cancelled'])->count()) > 0 ? $count : null)])->all(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->fetchOrderAction(),
            $this->importBasketAction(),
            $this->deliveryNoteAction(),
            // For a distributor whose API lists orders or looks them up by number (Cebeo): the open orders again.
            Action::make('sync')
                ->label(__('erp.distributor_orders.sync'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (SyncDistributorOrders $sync): bool => ($distributor = $this->tabDistributor()) !== null && $sync->supports($distributor))
                ->action(function (SyncDistributorOrders $sync): void {
                    $distributor = $this->tabDistributor();

                    try {
                        $count = $sync->handle($distributor);
                        Notification::make()->title(__('erp.distributor_orders.synced_count', ['count' => $count, 'name' => $distributor->name]))->success()->send();
                    } catch (Throwable $exception) {
                        Notification::make()->title(__('erp.distributor_orders.sync_failed', ['name' => $distributor->name]))->body($exception->getMessage())->warning()->send();
                    }
                }),
            CreateAction::make(),
        ];
    }

    /**
     * One order by the distributor's order number (Cebeo); afterwards followed up every hour.
     */
    protected function fetchOrderAction(): Action
    {
        return Action::make('fetchOrder')
            ->label(__('erp.distributor_orders.fetch_order'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn (DistributorClients $clients): bool => ($distributor = $this->tabDistributor()) !== null
                && $clients->lookupFor($distributor) !== null
                && auth()->user()?->can('create', DistributorOrder::class))
            ->schema([
                TextInput::make('number')
                    ->label(__('erp.distributor_orders.external_number'))
                    ->helperText(__('erp.distributor_orders.fetch_order_help'))
                    ->required()
                    ->maxLength(30)
                    ->regex('/^\s*\d+\s*$/'),
            ])
            ->action(function (array $data, SyncDistributorOrders $sync): void {
                $distributor = $this->tabDistributor();
                $number = ltrim(trim((string) $data['number']), '0') ?: '0';

                try {
                    $order = $sync->fetch($distributor, $number);
                } catch (Throwable $exception) {
                    Notification::make()->title(__('erp.distributor_orders.import_failed', ['name' => $distributor->name]))->body($exception->getMessage())->warning()->send();

                    return;
                }

                if ($order === null) {
                    Notification::make()->title(__('erp.distributor_orders.order_unknown', ['number' => $number, 'name' => $distributor->name]))->warning()->send();

                    return;
                }

                Notification::make()->title(__('erp.distributor_orders.order_fetched', ['number' => $number]))->success()->send();
                $this->redirect(DistributorOrderResource::getUrl('view', ['record' => $order]));
            });
    }

    /**
     * The webshop basket of the open tab's distributor becomes an open order (ImportDistributorBasket).
     */
    protected function importBasketAction(): Action
    {
        return Action::make('importBasket')
            ->label(__('erp.distributor_orders.import_basket'))
            ->icon(Heroicon::OutlinedShoppingCart)
            ->visible(fn (ImportDistributorBasket $import): bool => ($distributor = $this->tabDistributor()) !== null
                && $import->source($distributor) !== null
                && auth()->user()?->can('create', DistributorOrder::class))
            ->modalDescription(fn (ImportDistributorBasket $import): string => $import->source($this->tabDistributor())?->basketIsEmptied()
                ? __('erp.distributor_orders.import_basket_emptied', ['name' => $this->tabDistributor()->name])
                : __('erp.distributor_orders.import_basket_help', ['name' => $this->tabDistributor()->name]))
            ->schema([
                Select::make('order_reference_id')
                    ->label(__('erp.distributor_orders.order_reference'))
                    ->helperText(__('erp.distributor_orders.order_reference_help'))
                    ->options(fn (): array => OrderReference::query()->latest('id')->limit(200)->pluck('reference', 'id')->all())
                    ->searchable(),
                TextInput::make('customer_reference')->label(__('erp.distributor_orders.customer_reference'))->maxLength(255),
                TextInput::make('external_number')->label(__('erp.distributor_orders.external_number'))->maxLength(100),
                Textarea::make('notes')->label(__('erp.fields.notes'))->rows(2),
            ])
            ->action(function (array $data, ImportDistributorBasket $import): void {
                $distributor = $this->tabDistributor();

                try {
                    $order = $import->handle($distributor, $data, auth()->id());
                } catch (Throwable $exception) {
                    Notification::make()->title(__('erp.distributor_orders.import_failed', ['name' => $distributor->name]))->body($exception->getMessage())->warning()->send();

                    return;
                }

                if ($order === null) {
                    Notification::make()->title(__('erp.distributor_orders.basket_empty', ['name' => $distributor->name]))->warning()->send();

                    return;
                }

                Notification::make()->title(__('erp.distributor_orders.basket_imported', ['count' => $order->lines()->count()]))->success()->send();
                $this->redirect(DistributorOrderResource::getUrl('edit', ['record' => $order]));
            });
    }

    /**
     * A Cebeo delivery note fetched by its number: delivered quantities per Cebeo order.
     */
    protected function deliveryNoteAction(): Action
    {
        return Action::make('deliveryNote')
            ->label(__('erp.distributor_orders.delivery_note'))
            ->icon(Heroicon::OutlinedTruck)
            ->color('gray')
            ->visible(fn (DistributorClients $clients): bool => ($distributor = $this->tabDistributor()) !== null
                && ($client = $clients->for($distributor)) instanceof CebeoClient
                && $client->isConfigured()
                && auth()->user()?->can('create', DistributorOrder::class))
            ->schema([
                TextInput::make('number')
                    ->label(__('erp.distributor_orders.delivery_note_number'))
                    ->helperText(__('erp.distributor_orders.delivery_note_help'))
                    ->required()
                    ->maxLength(30)
                    ->regex('/^\s*\d+\s*$/'),
            ])
            ->action(function (array $data, DistributorClients $clients, RecordDistributorDeliveries $deliveries): void {
                $distributor = $this->tabDistributor();
                $number = ltrim(trim((string) $data['number']), '0') ?: '0';

                try {
                    /** @var CebeoClient $client */
                    $client = $clients->for($distributor);
                    $orders = $client->dispatchAdvice($number);
                } catch (Throwable $exception) {
                    Notification::make()->title(__('erp.distributor_orders.import_failed', ['name' => $distributor->name]))->body($exception->getMessage())->warning()->send();

                    return;
                }

                if ($orders === []) {
                    Notification::make()->title(__('erp.distributor_orders.delivery_note_unknown', ['number' => $number]))->warning()->send();

                    return;
                }

                $recorded = $deliveries->fromDeliveryNote($distributor, $number, $orders);

                $recorded === []
                    ? Notification::make()->title(__('erp.distributor_orders.delivery_note_done', ['number' => $number]))->info()->send()
                    : Notification::make()->title(__('erp.distributor_orders.delivery_note_recorded', ['count' => count($recorded)]))->success()->send();
            });
    }

    protected function tabDistributor(): ?Distributor
    {
        return str_starts_with((string) $this->activeTab, 'distributor-')
            ? Distributor::query()->find((int) substr((string) $this->activeTab, strlen('distributor-')))
            : null;
    }
}
