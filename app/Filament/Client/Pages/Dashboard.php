<?php

namespace App\Filament\Client\Pages;

use App\Enums\ProjectStatus;
use App\Filament\Client\Resources\Asbuilts\ClientAsbuiltResource;
use App\Filament\Client\Resources\BatteryDeliveries\ClientBatteryDeliveryResource;
use App\Filament\Client\Resources\BatteryReports\ClientBatteryReportResource;
use App\Filament\Client\Resources\ChargingStations\ClientChargingStationResource;
use App\Filament\Client\Resources\GpwRequests\ClientGpwRequestResource;
use App\Filament\Client\Resources\Projects\ClientProjectResource;
use App\Filament\Client\Resources\Routers\ClientRouterResource;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The start page of the client portal: a count per overview the user may open, the share of open and
 * closed projects per area code, and the projects per status.
 */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.client.dashboard';

    public function getTitle(): string
    {
        return __('erp.client_portal.brand');
    }

    /**
     * One tile per overview the user may open. Battery deliveries count what still has to be delivered
     * (delivered ones leave the client's list), and stand out while there are any.
     *
     * @return list<array{label: string, count: int, url: string, highlight: bool}>
     */
    public function tiles(): array
    {
        $tiles = [];

        foreach ([ClientProjectResource::class, ClientGpwRequestResource::class, ClientAsbuiltResource::class, ClientBatteryReportResource::class, ClientBatteryDeliveryResource::class, ClientChargingStationResource::class, ClientRouterResource::class] as $resource) {
            if ($resource::canViewAny()) {
                $count = $resource::getEloquentQuery()->count();
                $toDeliver = $resource === ClientBatteryDeliveryResource::class;

                $tiles[] = [
                    'label' => $toDeliver ? __('erp.client_portal.deliveries_to_come') : $resource::getPluralModelLabel(),
                    'count' => $count,
                    'url' => $resource::getUrl(),
                    'highlight' => $toDeliver && $count > 0,
                ];
            }
        }

        return $tiles;
    }

    /**
     * Per area code (of the COW site) of the client's projects: how many there are, and which share of
     * them is open and which is closed ("Closed"; every other status counts as open).
     *
     * @return list<array{area: string, total: int, open: int, closed: int, open_percent: float, closed_percent: float}>
     */
    public function areas(): array
    {
        if (! ClientProjectResource::canViewAny()) {
            return [];
        }

        $rows = ClientProjectResource::visibleProjects()
            ->toBase()
            ->leftJoin('work_sites as area_sites', 'area_sites.id', '=', 'projects.work_site_id')
            ->leftJoin('work_site_areas as site_areas', 'site_areas.id', '=', 'area_sites.work_site_area_id')
            ->selectRaw('site_areas.name as area, count(*) as total, sum(case when projects.status = ? then 1 else 0 end) as closed', [ProjectStatus::Closed->value])
            ->groupBy('site_areas.name')
            ->orderBy('site_areas.name')
            ->get();

        return $rows->map(function (object $row): array {
            $total = (int) $row->total;
            $closed = (int) $row->closed;

            return [
                'area' => filled($row->area) ? (string) $row->area : __('erp.client_portal.areas.none'),
                'total' => $total,
                'open' => $total - $closed,
                'closed' => $closed,
                'open_percent' => $total > 0 ? round(($total - $closed) / $total * 100, 1) : 0.0,
                'closed_percent' => $total > 0 ? round($closed / $total * 100, 1) : 0.0,
            ];
        })->sortBy(fn (array $row): string => $row['area'] === __('erp.client_portal.areas.none') ? "\u{10FFFF}" : $row['area'])->values()->all();
    }

    /**
     * @return list<array{label: string, color: string, count: int, url: string}>
     */
    public function statuses(): array
    {
        if (! ClientProjectResource::canViewAny()) {
            return [];
        }

        $counts = ClientProjectResource::visibleProjects()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

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
