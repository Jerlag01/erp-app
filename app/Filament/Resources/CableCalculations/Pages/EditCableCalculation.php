<?php

namespace App\Filament\Resources\CableCalculations\Pages;

use App\Filament\Resources\CableCalculations\CableCalculationResource;
use App\Support\CableCalculationDocuments;
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

    /**
     * After the circuits are saved: the PDF in the project's extra files and as-built.
     */
    protected function afterSave(): void
    {
        app(CableCalculationDocuments::class)->sync($this->getRecord(), auth()->user());
    }
}
