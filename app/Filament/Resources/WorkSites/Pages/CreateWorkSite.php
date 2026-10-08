<?php

namespace App\Filament\Resources\WorkSites\Pages;

use App\Filament\Resources\WorkSites\WorkSiteResource;
use App\Models\WorkSite;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkSite extends CreateRecord
{
    protected static string $resource = WorkSiteResource::class;

    /**
     * The first building type starts the site's building type log.
     */
    protected function afterCreate(): void
    {
        /** @var WorkSite $site */
        $site = $this->getRecord();

        $site->typeChanges()->create([
            'from_type' => null,
            'to_type' => $site->building_type->value,
            'changed_on' => today(),
            'reason' => __('erp.work_sites.created'),
            'user_id' => auth()->id(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return WorkSiteResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
