<?php

namespace App\Filament\Resources\StandardOfferLines\Pages;

use App\Filament\Resources\StandardOfferLines\StandardOfferLineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStandardOfferLines extends ManageRecords
{
    protected static string $resource = StandardOfferLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
