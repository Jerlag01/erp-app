<?php

namespace App\Filament\Admin\Resources\BackupTargets\Pages;

use App\Filament\Admin\Resources\BackupTargets\BackupTargetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBackupTargets extends ListRecords
{
    protected static string $resource = BackupTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('backups.new'))];
    }
}
