<?php

namespace App\Filament\Resources\OfferCategories\Pages;

use App\Filament\Resources\OfferCategories\OfferCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOfferCategories extends ManageRecords
{
    protected static string $resource = OfferCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
