<?php

namespace App\Filament\Resources\WorkSiteContacts\Pages;

use App\Filament\Resources\WorkSiteContacts\WorkSiteContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkSiteContacts extends ManageRecords
{
    protected static string $resource = WorkSiteContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
