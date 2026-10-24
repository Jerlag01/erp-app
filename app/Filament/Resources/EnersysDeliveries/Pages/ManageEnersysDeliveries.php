<?php

namespace App\Filament\Resources\EnersysDeliveries\Pages;

use App\Filament\Resources\EnersysDeliveries\EnersysDeliveryResource;
use App\Models\DeliveryFollowUp;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageEnersysDeliveries extends ManageRecords
{
    protected static string $resource = EnersysDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data): array => [...$data, 'kind' => DeliveryFollowUp::ENERSYS, 'created_by' => auth()->id()]),
        ];
    }

    public function getTabs(): array
    {
        return [
            'to_deliver' => Tab::make(__('erp.follow_ups.tabs.to_deliver'))->modifyQueryUsing(fn (Builder $query) => $query->where('delivered', false)),
            'delivered' => Tab::make(__('erp.follow_ups.tabs.delivered'))->modifyQueryUsing(fn (Builder $query) => $query->where('delivered', true)),
            'all' => Tab::make(__('erp.follow_ups.tabs.all')),
        ];
    }
}
