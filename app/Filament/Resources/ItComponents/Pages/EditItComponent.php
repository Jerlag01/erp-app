<?php

namespace App\Filament\Resources\ItComponents\Pages;

use App\Filament\Resources\ItComponents\ItComponentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItComponent extends EditRecord
{
    protected static string $resource = ItComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
