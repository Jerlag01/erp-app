<?php

namespace App\Filament\Client\Resources\BatteryReports\Pages;

use App\Filament\Client\Resources\BatteryReports\ClientBatteryReportResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * One finished battery replacement in the client portal: its photos and documents.
 */
class ViewClientBatteryReport extends ViewRecord
{
    protected static string $resource = ClientBatteryReportResource::class;

    public function getTitle(): string
    {
        return __('erp.client_portal.battery_reports.singular').' '.$this->getRecord()->reference;
    }
}
