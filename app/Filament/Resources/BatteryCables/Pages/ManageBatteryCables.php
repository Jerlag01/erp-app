<?php

namespace App\Filament\Resources\BatteryCables\Pages;

use App\Filament\Resources\BatteryCables\BatteryCableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBatteryCables extends ManageRecords
{
    protected static string $resource = BatteryCableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
