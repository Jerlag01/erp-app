<?php

namespace App\Filament\Resources\ItCategories\Pages;

use App\Filament\Resources\ItCategories\ItCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageItCategories extends ManageRecords
{
    protected static string $resource = ItCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
