<?php

namespace App\Filament\Admin\Resources\CustomAbsenceTypes\Pages;

use App\Filament\Admin\Resources\CustomAbsenceTypes\CustomAbsenceTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomAbsenceTypes extends ManageRecords
{
    protected static string $resource = CustomAbsenceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
