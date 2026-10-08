<?php

namespace App\Filament\Resources\ItLicenses\Pages;

use App\Filament\Resources\ItLicenses\ItLicenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItLicenses extends ListRecords
{
    protected static string $resource = ItLicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
