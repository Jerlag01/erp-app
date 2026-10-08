<?php

namespace App\Filament\Resources\ItModels\Pages;

use App\Filament\Resources\ItModels\ItModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageItModels extends ManageRecords
{
    protected static string $resource = ItModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
