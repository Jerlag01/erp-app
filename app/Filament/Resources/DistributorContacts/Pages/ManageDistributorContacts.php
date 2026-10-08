<?php

namespace App\Filament\Resources\DistributorContacts\Pages;

use App\Filament\Resources\DistributorContacts\DistributorContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDistributorContacts extends ManageRecords
{
    protected static string $resource = DistributorContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
