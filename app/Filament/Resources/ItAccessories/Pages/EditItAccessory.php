<?php

namespace App\Filament\Resources\ItAccessories\Pages;

use App\Filament\Resources\ItAccessories\ItAccessoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItAccessory extends EditRecord
{
    protected static string $resource = ItAccessoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
