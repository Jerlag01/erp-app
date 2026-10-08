<?php

namespace App\Filament\Resources\WorkSiteAreas\Pages;

use App\Filament\Resources\WorkSiteAreas\WorkSiteAreaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkSiteAreas extends ManageRecords
{
    protected static string $resource = WorkSiteAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
