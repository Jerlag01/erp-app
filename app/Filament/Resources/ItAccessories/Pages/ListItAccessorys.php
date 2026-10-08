<?php

namespace App\Filament\Resources\ItAccessories\Pages;

use App\Filament\Resources\ItAccessories\ItAccessoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItAccessorys extends ListRecords
{
    protected static string $resource = ItAccessoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
