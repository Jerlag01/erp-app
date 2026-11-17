<?php

namespace App\Filament\Admin\Resources\BackupTargets\Pages;

use App\Filament\Admin\Resources\BackupTargets\BackupTargetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBackupTarget extends EditRecord
{
    protected static string $resource = BackupTargetResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [...$data, 'host' => $data['host'] ?? 'localhost'];
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->modalDescription(__('backups.delete_help'))];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
