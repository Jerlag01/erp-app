<?php

namespace App\Filament\Resources\WorkSites\Pages;

use App\Filament\Resources\WorkSites\WorkSiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkSites extends ListRecords
{
    protected static string $resource = WorkSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
