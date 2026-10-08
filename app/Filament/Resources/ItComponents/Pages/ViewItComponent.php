<?php

namespace App\Filament\Resources\ItComponents\Pages;

use App\Filament\Resources\ItComponents\ItComponentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItComponent extends ViewRecord
{
    protected static string $resource = ItComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ItComponentResource::checkoutAction(),
            EditAction::make(),
        ];
    }
}
