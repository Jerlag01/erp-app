<?php

namespace App\Filament\Resources\ItComponents\Pages;

use App\Filament\Resources\ItComponents\ItComponentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItComponents extends ListRecords
{
    protected static string $resource = ItComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
