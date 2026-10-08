<?php

namespace App\Filament\Resources\WorkSites\Pages;

use App\Filament\Resources\WorkSites\WorkSiteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkSite extends EditRecord
{
    protected static string $resource = WorkSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
