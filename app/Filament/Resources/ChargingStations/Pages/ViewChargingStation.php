<?php

namespace App\Filament\Resources\ChargingStations\Pages;

use App\Filament\Resources\ChargingStations\ChargingStationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewChargingStation extends ViewRecord
{
    protected static string $resource = ChargingStationResource::class;

    public function getTitle(): string
    {
        return trim(($this->getRecord()->workSite?->cow_code ?? '').' · '.($this->getRecord()->ace_number ?? __('erp.charging.station')), ' ·');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
