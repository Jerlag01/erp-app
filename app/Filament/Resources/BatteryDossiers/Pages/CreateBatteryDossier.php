<?php

namespace App\Filament\Resources\BatteryDossiers\Pages;

use App\Filament\Resources\BatteryDossiers\BatteryDossierResource;
use App\Filament\Resources\BatteryDossiers\Schemas\BatteryDossierForm;
use App\Models\Project;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateBatteryDossier extends CreateRecord
{
    protected static string $resource = BatteryDossierResource::class;

    /**
     * Defaults from Settings > Batteries, and from the project (?project=) when opened from it.
     */
    protected function afterFill(): void
    {
        $settings = settings()->get('batteries');
        $project = Project::query()->find(request()->integer('project'));

        $this->data = [
            ...$this->data,
            'brand' => $settings['brand'] ?? null,
            'supplier_location' => $settings['supplier_location'] ?? null,
            'waste_type' => ((array) ($settings['waste_types'] ?? []))[0] ?? null,
            'project_id' => $project && array_key_exists($project->id, BatteryDossierForm::projectOptions()) ? $project->id : ($this->data['project_id'] ?? null),
            'po_number' => $project?->poNumbers()->value('number'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! array_key_exists((int) ($data['project_id'] ?? 0), BatteryDossierForm::projectOptions())) {
            throw ValidationException::withMessages(['data.project_id' => __('erp.batteries.errors.project')]);
        }

        $settings = settings()->get('batteries');

        unset($data['strings'], $data['floor_plan_png']);

        return [
            ...$data,
            'created_by' => auth()->id(),
            'hourly_rate' => $data['hourly_rate'] ?? $settings['hourly_rate'] ?? 0,
            'lug_price' => $settings['lug_price'] ?? 0,
            'tray_price' => $settings['tray_price'] ?? 0,
            'doc_holder_price' => $settings['doc_holder_price'] ?? 0,
            'picto_price' => $settings['picto_price'] ?? 0,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return BatteryDossierResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
