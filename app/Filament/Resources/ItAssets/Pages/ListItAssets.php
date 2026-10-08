<?php

namespace App\Filament\Resources\ItAssets\Pages;

use App\Filament\Resources\ItAssets\ItAssetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItAssets extends ListRecords
{
    protected static string $resource = ItAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
