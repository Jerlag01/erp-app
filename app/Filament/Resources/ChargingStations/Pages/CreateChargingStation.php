<?php

namespace App\Filament\Resources\ChargingStations\Pages;

use App\Filament\Resources\ChargingStations\ChargingStationResource;
use App\Models\Project;
use Filament\Resources\Pages\CreateRecord;

class CreateChargingStation extends CreateRecord
{
    protected static string $resource = ChargingStationResource::class;

    /**
     * Opened from a 608 project (?project=): that project and its COW site are filled in.
     */
    protected function afterFill(): void
    {
        $project = Project::query()->find(request()->integer('project'));

        if ($project && array_key_exists($project->id, ChargingStationResource::projectOptions())) {
            $this->data['project_id'] = $project->id;
            $this->data['work_site_id'] = $project->work_site_id;
        }
    }

    protected function getRedirectUrl(): string
    {
        return ChargingStationResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
