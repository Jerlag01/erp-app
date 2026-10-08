<?php

namespace App\Filament\Resources\WorkSites\Pages;

use App\Filament\Resources\WorkSites\Actions\ChangeBuildingTypeAction;
use App\Filament\Resources\WorkSites\WorkSiteResource;
use App\Models\WorkSite;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkSite extends ViewRecord
{
    protected static string $resource = WorkSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ChangeBuildingTypeAction::make()
                ->after(fn (WorkSite $record) => $record->refresh()),
            EditAction::make(),
        ];
    }
}
