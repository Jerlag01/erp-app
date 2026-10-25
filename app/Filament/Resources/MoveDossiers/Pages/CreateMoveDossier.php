<?php

namespace App\Filament\Resources\MoveDossiers\Pages;

use App\Filament\Resources\MoveDossiers\MoveDossierResource;
use App\Filament\Resources\MoveDossiers\Schemas\MoveDossierForm;
use App\Models\EnersysDossier;
use App\Models\MoveDossier;
use App\Models\Project;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

/**
 * A 605 project is either an Enersys replacement or a move: a project with an Enersys dossier gets no move.
 */
class CreateMoveDossier extends CreateRecord
{
    protected static string $resource = MoveDossierResource::class;

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
            'waste_type' => ((array) ($settings['waste_types'] ?? []))[0] ?? null,
            'project_id' => $project && array_key_exists($project->id, MoveDossierForm::projectOptions()) ? $project->id : ($this->data['project_id'] ?? null),
            'move_to_work_site_id' => $project?->work_site_id,
            'po_number' => $project?->poNumbers()->value('number'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $projectId = (int) ($data['project_id'] ?? 0);

        if (! array_key_exists($projectId, MoveDossierForm::projectOptions())) {
            throw ValidationException::withMessages(['data.project_id' => __('erp.batteries.errors.project')]);
        }

        if (EnersysDossier::query()->where('project_id', $projectId)->exists() || MoveDossier::query()->where('project_id', $projectId)->exists()) {
            throw ValidationException::withMessages(['data.project_id' => __('erp.moves.errors.has_dossier')]);
        }

        unset($data['strings']);

        return [...$data, 'created_by' => auth()->id(), 'hourly_rate' => (float) settings()->get('batteries.hourly_rate', 0)];
    }

    protected function getRedirectUrl(): string
    {
        return MoveDossierResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
