<?php

namespace App\Filament\Resources\ItConsumables\Pages;

use App\Filament\Resources\ItConsumables\ItConsumableResource;
use Filament\Resources\Pages\CreateRecord;

class CreateItConsumable extends CreateRecord
{
    protected static string $resource = ItConsumableResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'kind' => ItConsumableResource::kind()];
    }
}
