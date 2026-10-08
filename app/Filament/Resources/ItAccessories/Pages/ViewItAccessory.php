<?php

namespace App\Filament\Resources\ItAccessories\Pages;

use App\Filament\Resources\ItAccessories\ItAccessoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItAccessory extends ViewRecord
{
    protected static string $resource = ItAccessoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ItAccessoryResource::checkoutAction(),
            EditAction::make(),
        ];
    }
}
