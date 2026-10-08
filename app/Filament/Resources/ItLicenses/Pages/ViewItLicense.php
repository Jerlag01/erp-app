<?php

namespace App\Filament\Resources\ItLicenses\Pages;

use App\Filament\Resources\ItLicenses\ItLicenseResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItLicense extends ViewRecord
{
    protected static string $resource = ItLicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
