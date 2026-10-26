<?php

namespace App\Filament\Resources\CableCalculations\Pages;

use App\Filament\Resources\CableCalculations\CableCalculationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditCableCalculation extends EditRecord
{
    protected static string $resource = CableCalculationResource::class;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            CableCalculationResource::pdfAction(),
            DeleteAction::make(),
        ];
    }
}
