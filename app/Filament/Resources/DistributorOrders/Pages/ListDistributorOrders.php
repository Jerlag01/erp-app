<?php

namespace App\Filament\Resources\DistributorOrders\Pages;

use App\Actions\Purchasing\SyncDistributorOrders;
use App\Filament\Resources\DistributorOrders\DistributorOrderResource;
use App\Models\Distributor;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * A tab per distributor with an API (and one with every order); "Fetch orders" asks the API of the
 * distributor of the open tab.
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
            Action::make('sync')
                ->label(__('erp.distributor_orders.sync'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => $this->tabDistributor() !== null)
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

    protected function tabDistributor(): ?Distributor
    {
        return str_starts_with((string) $this->activeTab, 'distributor-')
            ? Distributor::query()->find((int) substr((string) $this->activeTab, strlen('distributor-')))
            : null;
    }
}
