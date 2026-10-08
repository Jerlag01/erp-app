<?php

namespace App\Filament\Resources\ItConsumables\Pages;

use App\Filament\Resources\ItConsumables\ItConsumableResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItConsumable extends EditRecord
{
    protected static string $resource = ItConsumableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
