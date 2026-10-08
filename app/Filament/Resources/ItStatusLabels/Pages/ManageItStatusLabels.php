<?php

namespace App\Filament\Resources\ItStatusLabels\Pages;

use App\Filament\Resources\ItStatusLabels\ItStatusLabelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageItStatusLabels extends ManageRecords
{
    protected static string $resource = ItStatusLabelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
