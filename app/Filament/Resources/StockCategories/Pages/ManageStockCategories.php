<?php

namespace App\Filament\Resources\StockCategories\Pages;

use App\Filament\Resources\StockCategories\StockCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStockCategories extends ManageRecords
{
    protected static string $resource = StockCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
