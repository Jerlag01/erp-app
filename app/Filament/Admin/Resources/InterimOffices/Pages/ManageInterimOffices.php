<?php

namespace App\Filament\Admin\Resources\InterimOffices\Pages;

use App\Filament\Admin\Resources\InterimOffices\InterimOfficeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInterimOffices extends ManageRecords
{
    protected static string $resource = InterimOfficeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
