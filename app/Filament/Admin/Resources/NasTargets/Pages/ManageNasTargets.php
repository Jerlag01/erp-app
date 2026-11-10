<?php

namespace App\Filament\Admin\Resources\NasTargets\Pages;

use App\Filament\Admin\Resources\NasTargets\NasTargetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageNasTargets extends ManageRecords
{
    protected static string $resource = NasTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
