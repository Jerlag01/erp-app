<?php

namespace App\Filament\Resources\GpwRequests\Pages;

use App\Enums\GpwStatus;
use App\Filament\Resources\GpwRequests\GpwRequestResource;
use App\Models\GpwRequest;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListGpwRequests extends ListRecords
{
    protected static string $resource = GpwRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Asked through a personal mail: added by hand with its status, no mail goes out.
            CreateAction::make()
                ->label(__('erp.gpw.add_manual'))
                ->icon(Heroicon::OutlinedEnvelope)
                ->modalHeading(__('erp.gpw.add_manual'))
                ->modalDescription(__('erp.gpw.add_manual_help'))
                ->schema(GpwRequestResource::manualSchema())
                ->createAnother(false)
                ->using(fn (array $data): GpwRequest => GpwRequestResource::addManual($data))
                ->successNotificationTitle(__('erp.gpw.manual_added')),
        ];
    }

    public function getTabs(): array
    {
        $counts = GpwRequestResource::getEloquentQuery()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $tabs = ['all' => Tab::make(__('erp.gpw.all'))];

        foreach ([GpwStatus::Pending, GpwStatus::Approved, GpwStatus::Rejected, GpwStatus::Planned] as $status) {
            $tabs[$status->value] = Tab::make(__('erp.gpw.overview_status.'.$status->value))
                ->icon($status->getIcon())
                ->badge((int) ($counts[$status->value] ?? 0) ?: null)
                ->badgeColor($status->getColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        return $tabs;
    }
}
