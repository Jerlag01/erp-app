<?php

namespace App\Filament\Resources\ItMaintenances\Pages;

use App\Filament\Resources\ItMaintenances\ItMaintenanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageItMaintenances extends ManageRecords
{
    protected static string $resource = ItMaintenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => auth()->id()]),
        ];
    }
}
