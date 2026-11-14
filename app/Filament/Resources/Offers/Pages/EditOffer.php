<?php

namespace App\Filament\Resources\Offers\Pages;

use App\Filament\Resources\Offers\OfferActions;
use App\Filament\Resources\Offers\OfferResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOffer extends EditRecord
{
    protected static string $resource = OfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OfferActions::pdf(),
            OfferActions::saveToProject(),
            OfferActions::createInvoice(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->getRecord()->recalculate();
    }
}
