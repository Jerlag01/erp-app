<?php

namespace App\Filament\Resources\ChargingStations\Pages;

use App\Filament\Resources\ChargingStations\ChargingStationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditChargingStation extends EditRecord
{
    protected static string $resource = ChargingStationResource::class;

    /**
     * The secrets are hidden attributes: only load them for users who may see them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (ChargingStationResource::canSeeSecrets()) {
            $data['password'] = $this->getRecord()->password;
            $data['recovery_code'] = $this->getRecord()->recovery_code;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return ChargingStationResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
