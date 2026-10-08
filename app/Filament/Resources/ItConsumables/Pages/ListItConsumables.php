<?php

namespace App\Filament\Resources\ItConsumables\Pages;

use App\Filament\Resources\ItConsumables\ItConsumableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItConsumables extends ListRecords
{
    protected static string $resource = ItConsumableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
