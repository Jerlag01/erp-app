<?php

namespace App\Filament\Client\Pages;

use App\Enums\ProjectStatus;
use App\Filament\Client\Resources\Asbuilts\ClientAsbuiltResource;
use App\Filament\Client\Resources\BatteryDeliveries\ClientBatteryDeliveryResource;
use App\Filament\Client\Resources\ChargingStations\ClientChargingStationResource;
use App\Filament\Client\Resources\GpwRequests\ClientGpwRequestResource;
use App\Filament\Client\Resources\Projects\ClientProjectResource;
use App\Filament\Client\Resources\Routers\ClientRouterResource;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The start page of the client portal: a count per overview the user may open, and the projects per
 * status.
 */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.client.dashboard';

    public function getTitle(): string
    {
        return __('erp.client_portal.brand');
    }

    /**
     * @return list<array{label: string, count: int, url: string}>
     */
    public function tiles(): array
    {
        $tiles = [];

        foreach ([ClientProjectResource::class, ClientGpwRequestResource::class, ClientAsbuiltResource::class, ClientBatteryDeliveryResource::class, ClientChargingStationResource::class, ClientRouterResource::class] as $resource) {
            if ($resource::canViewAny()) {
                $tiles[] = ['label' => $resource::getPluralModelLabel(), 'count' => $resource::getEloquentQuery()->count(), 'url' => $resource::getUrl()];
            }
        }

        return $tiles;
    }

    /**
     * @return list<array{label: string, color: string, count: int, url: string}>
     */
    public function statuses(): array
    {
        if (! ClientProjectResource::canViewAny()) {
            return [];
        }

        $counts = ClientProjectResource::getEloquentQuery()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(ProjectStatus::cases())
            ->filter(fn (ProjectStatus $status): bool => ($counts[$status->value] ?? 0) > 0)
            ->map(fn (ProjectStatus $status): array => [
                'label' => $status->getLabel(),
                'color' => $status->getColor(),
                'count' => (int) $counts[$status->value],
                'url' => ClientProjectResource::getUrl('index', ['filters' => ['status' => ['values' => [$status->value]]]]),
            ])
            ->values()
            ->all();
    }
}
