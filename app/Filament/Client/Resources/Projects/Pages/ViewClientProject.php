<?php

namespace App\Filament\Client\Resources\Projects\Pages;

use App\Filament\Client\Resources\Projects\ClientProjectResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * One project in the client portal: its overview (COW code, address, status, GPW requests, project
 * leaders with their e-mail address), without files.
 */
class ViewClientProject extends ViewRecord
{
    protected static string $resource = ClientProjectResource::class;

    public function getTitle(): string
    {
        return __('erp.client_portal.project').' '.$this->getRecord()->reference;
    }
}
