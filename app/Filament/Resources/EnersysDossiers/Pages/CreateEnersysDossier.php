<?php

namespace App\Filament\Resources\EnersysDossiers\Pages;

use App\Filament\Resources\EnersysDossiers\EnersysDossierResource;
use App\Filament\Resources\EnersysDossiers\Schemas\EnersysDossierForm;
use App\Models\MoveDossier;
use App\Models\Project;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateEnersysDossier extends CreateRecord
{
    protected static string $resource = EnersysDossierResource::class;

    /**
     * Defaults from Settings > Batteries, and from the project (?project=) when opened from it.
     */
    protected function afterFill(): void
    {
        $settings = settings()->get('batteries');
        $project = Project::query()->find(request()->integer('project'));

        $this->data = [
            ...$this->data,
            'brand' => $settings['enersys_brand'] ?? 'Enersys',
            'supplier_location' => $settings['supplier_location'] ?? null,
            'waste_type' => ((array) ($settings['waste_types'] ?? []))[1] ?? ((array) ($settings['waste_types'] ?? []))[0] ?? null,
            'project_id' => $project && array_key_exists($project->id, EnersysDossierForm::projectOptions()) ? $project->id : ($this->data['project_id'] ?? null),
            'po_number' => $project?->poNumbers()->value('number'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! array_key_exists((int) ($data['project_id'] ?? 0), EnersysDossierForm::projectOptions())) {
            throw ValidationException::withMessages(['data.project_id' => __('erp.batteries.errors.project')]);
        }

        // A 605 project is either an Enersys replacement or a move.
        if (MoveDossier::query()->where('project_id', (int) $data['project_id'])->exists()) {
            throw ValidationException::withMessages(['data.project_id' => __('erp.moves.errors.has_dossier')]);
        }

        unset($data['strings']);

        return [...$data, 'created_by' => auth()->id()];
    }

    protected function getRedirectUrl(): string
    {
        return EnersysDossierResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
