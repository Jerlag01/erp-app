<?php

namespace App\Filament\Resources\EnersysDossiers\Pages;

use App\Filament\Resources\EnersysDossiers\EnersysDossierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEnersysDossiers extends ListRecords
{
    protected static string $resource = EnersysDossierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
