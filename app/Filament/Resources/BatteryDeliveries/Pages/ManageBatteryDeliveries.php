<?php

namespace App\Filament\Resources\BatteryDeliveries\Pages;

use App\Filament\Resources\BatteryDeliveries\BatteryDeliveryResource;
use App\Filament\Resources\BatteryDossiers\Schemas\BatteryDossierForm;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ManageBatteryDeliveries extends ManageRecords
{
    protected static string $resource = BatteryDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label(__('erp.batteries.print_overview'))
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => route('batteries.deliveries-pdf', ['tab' => $this->activeTab]), shouldOpenInNewTab: true),
            CreateAction::make()
                ->mutateDataUsing(function (array $data): array {
                    if (! array_key_exists((int) ($data['project_id'] ?? 0), BatteryDossierForm::projectOptions())) {
                        throw ValidationException::withMessages(['mountedActions.0.data.project_id' => __('erp.batteries.errors.project')]);
                    }

                    return $data;
                }),
        ];
    }

    /**
     * Like the Excel, finished orders are out of sight by default.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $count = fn (callable $scope): ?int => $scope(BatteryDeliveryResource::getEloquentQuery())->count() ?: null;
        $toDeliver = fn (Builder $query) => $query->where('delivered', false)->where('executed', false);
        $delivered = fn (Builder $query) => $query->where('delivered', true)->where('executed', false);

        return [
            'to_deliver' => Tab::make(__('erp.batteries.delivery_tabs.to_deliver'))->badge($count($toDeliver))->modifyQueryUsing($toDeliver),
            'delivered' => Tab::make(__('erp.batteries.delivery_tabs.delivered'))->badge($count($delivered))->badgeColor('warning')->modifyQueryUsing($delivered),
            'executed' => Tab::make(__('erp.batteries.delivery_tabs.executed'))->modifyQueryUsing(fn (Builder $query) => $query->where('executed', true)),
            'all' => Tab::make(__('erp.projects.all')),
        ];
    }
}
