<?php

namespace App\Filament\Resources\WasteProcessors\Pages;

use App\Filament\Resources\WasteProcessors\WasteProcessorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWasteProcessors extends ManageRecords
{
    protected static string $resource = WasteProcessorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
