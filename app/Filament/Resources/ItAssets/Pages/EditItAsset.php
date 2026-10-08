<?php

namespace App\Filament\Resources\ItAssets\Pages;

use App\Filament\Resources\ItAssets\ItAssetResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItAsset extends EditRecord
{
    protected static string $resource = ItAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
