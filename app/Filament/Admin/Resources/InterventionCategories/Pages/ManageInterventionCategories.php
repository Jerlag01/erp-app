<?php

namespace App\Filament\Admin\Resources\InterventionCategories\Pages;

use App\Filament\Admin\Resources\InterventionCategories\InterventionCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInterventionCategories extends ManageRecords
{
    protected static string $resource = InterventionCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data): array => InterventionCategoryResource::clean($data)),
        ];
    }
}
