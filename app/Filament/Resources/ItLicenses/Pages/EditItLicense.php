<?php

namespace App\Filament\Resources\ItLicenses\Pages;

use App\Filament\Resources\ItLicenses\ItLicenseResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItLicense extends EditRecord
{
    protected static string $resource = ItLicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
