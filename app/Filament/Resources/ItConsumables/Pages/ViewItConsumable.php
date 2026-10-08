<?php

namespace App\Filament\Resources\ItConsumables\Pages;

use App\Filament\Resources\ItConsumables\ItConsumableResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItConsumable extends ViewRecord
{
    protected static string $resource = ItConsumableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ItConsumableResource::checkoutAction(),
            EditAction::make(),
        ];
    }
}
