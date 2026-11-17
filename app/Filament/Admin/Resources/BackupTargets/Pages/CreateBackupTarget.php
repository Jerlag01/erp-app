<?php

namespace App\Filament\Admin\Resources\BackupTargets\Pages;

use App\Filament\Admin\Resources\BackupTargets\BackupTargetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBackupTarget extends CreateRecord
{
    protected static string $resource = BackupTargetResource::class;

    /**
     * A folder on this server needs no host.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'host' => $data['host'] ?? 'localhost'];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
