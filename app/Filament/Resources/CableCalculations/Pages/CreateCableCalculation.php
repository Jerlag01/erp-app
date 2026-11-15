<?php

namespace App\Filament\Resources\CableCalculations\Pages;

use App\Filament\Resources\CableCalculations\CableCalculationResource;
use App\Models\Project;
use App\Support\CableCalculationDocuments;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

class CreateCableCalculation extends CreateRecord
{
    protected static string $resource = CableCalculationResource::class;

    /** Coming from a project ("?project=…"): linked to it. */
    #[Url]
    public ?string $project = null;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'created_by' => auth()->id()];
    }

    protected function afterFill(): void
    {
        $project = filled($this->project) ? Project::query()->find($this->project) : null;

        if ($project !== null && Gate::allows('view', $project)) {
            $this->data['project_id'] = $project->id;
        }
    }

    /**
     * After the circuits are saved: the PDF in the project's extra files and as-built.
     */
    protected function afterCreate(): void
    {
        app(CableCalculationDocuments::class)->sync($this->getRecord(), auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return CableCalculationResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
