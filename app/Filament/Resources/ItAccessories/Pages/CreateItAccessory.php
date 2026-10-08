<?php

namespace App\Filament\Resources\ItAccessories\Pages;

use App\Filament\Resources\ItAccessories\ItAccessoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateItAccessory extends CreateRecord
{
    protected static string $resource = ItAccessoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'kind' => ItAccessoryResource::kind()];
    }
}
