<?php

namespace App\Filament\Resources\Routers\Pages;

use App\Filament\Resources\Concerns\ExportsChargingList;
use App\Filament\Resources\Routers\RouterResource;
use App\Support\ChargingExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRouters extends ListRecords
{
    use ExportsChargingList;

    protected static string $resource = RouterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->exportAction(),
            CreateAction::make(),
        ];
    }

    protected function exportColumns(bool $withSecrets): array
    {
        return ChargingExport::routerColumns($withSecrets);
    }

    protected function exportTitle(): string
    {
        return __('erp.charging.routers_export_title');
    }

    protected function canExportSecrets(): bool
    {
        return RouterResource::canSeeSecrets();
    }
}
