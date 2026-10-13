<?php

namespace App\Filament\Resources\ChargingStations\Pages;

use App\Filament\Resources\ChargingStations\ChargingStationResource;
use App\Filament\Resources\Concerns\ExportsChargingList;
use App\Support\ChargingExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChargingStations extends ListRecords
{
    use ExportsChargingList;

    protected static string $resource = ChargingStationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->exportAction(),
            CreateAction::make(),
        ];
    }

    protected function exportColumns(bool $withSecrets): array
    {
        return ChargingExport::stationColumns($withSecrets);
    }

    protected function exportTitle(): string
    {
        return __('erp.charging.stations_export_title');
    }

    protected function permissionArea(): string
    {
        return 'charging';
    }

    protected function canExportSecrets(): bool
    {
        return ChargingStationResource::canSeeSecrets();
    }
}
