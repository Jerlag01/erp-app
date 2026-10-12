<?php

namespace App\Filament\Resources\Routers\Pages;

use App\Filament\Resources\Routers\RouterResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRouter extends ViewRecord
{
    protected static string $resource = RouterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
