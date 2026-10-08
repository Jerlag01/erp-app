<?php

namespace App\Filament\Resources\ItComponents\Pages;

use App\Filament\Resources\ItComponents\ItComponentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateItComponent extends CreateRecord
{
    protected static string $resource = ItComponentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'kind' => ItComponentResource::kind()];
    }
}
