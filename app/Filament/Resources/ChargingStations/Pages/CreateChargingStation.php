<?php

namespace App\Filament\Resources\ChargingStations\Pages;

use App\Filament\Resources\ChargingStations\ChargingStationResource;
use App\Models\Project;
use Filament\Resources\Pages\CreateRecord;

class CreateChargingStation extends CreateRecord
{
    protected static string $resource = ChargingStationResource::class;

    /**
     * Opened from a 608 project (?project=): that project and its COW site, or its third-party client
     * and installation address, are filled in.
     */
    protected function afterFill(): void
    {
        $project = Project::query()->find(request()->integer('project'));

        if ($project && array_key_exists($project->id, ChargingStationResource::projectOptions())) {
            $this->data['project_id'] = $project->id;
            $this->data['location_type'] = $project->location_type->value;
            $this->data['work_site_id'] = $project->work_site_id;

            if ($project->isThirdParty()) {
                foreach (['client_name', 'client_company', 'client_email', 'client_phone', 'street', 'house_number', 'addition', 'postal_code', 'city', 'country_id'] as $field) {
                    $this->data[$field] = $project->{$field};
                }
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return ChargingStationResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
