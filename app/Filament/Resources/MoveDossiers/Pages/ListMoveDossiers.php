<?php

namespace App\Filament\Resources\MoveDossiers\Pages;

use App\Filament\Resources\MoveDossiers\MoveDossierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMoveDossiers extends ListRecords
{
    protected static string $resource = MoveDossierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
