<?php

namespace App\Filament\Resources\ChargingStations\Pages;

use App\Filament\Resources\ChargingStations\ChargingStationResource;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

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
            $this->data = [...$this->data, ...ChargingStationResource::projectDefaults($project)];
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createMultiple')
                ->label(__('erp.charging.create_multiple'))
                ->icon(Heroicon::OutlinedSquare2Stack)
                ->color('gray')
                ->url(fn (): string => ChargingStationResource::getUrl('create-multiple', array_filter(['project' => request()->integer('project') ?: null]))),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return ChargingStationResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
