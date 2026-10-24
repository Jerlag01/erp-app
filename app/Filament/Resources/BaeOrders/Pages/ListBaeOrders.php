<?php

namespace App\Filament\Resources\BaeOrders\Pages;

use App\Filament\Resources\BaeOrders\BaeOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBaeOrders extends ListRecords
{
    protected static string $resource = BaeOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
