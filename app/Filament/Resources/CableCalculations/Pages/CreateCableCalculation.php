<?php

namespace App\Filament\Resources\CableCalculations\Pages;

use App\Filament\Resources\CableCalculations\CableCalculationResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateCableCalculation extends CreateRecord
{
    protected static string $resource = CableCalculationResource::class;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'created_by' => auth()->id()];
    }

    protected function getRedirectUrl(): string
    {
        return CableCalculationResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
