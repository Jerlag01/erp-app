<?php

namespace App\Filament\Resources\Offers\Pages;

use App\Filament\Resources\Offers\OfferResource;
use App\Models\Offer;
use App\Models\Project;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

/**
 * A new offer starts with the settings (hourly rate, remarks, validity) and, coming from a project
 * ("?project=…"), with its COW code, address and description.
 */
class CreateOffer extends CreateRecord
{
    protected static string $resource = OfferResource::class;

    #[Url]
    public ?string $project = null;

    protected function fillForm(): void
    {
        $project = filled($this->project) ? Project::query()->with('workSite')->find($this->project) : null;
        $project = $project && Gate::allows('view', $project) ? $project : null;

        $this->form->fill(Offer::defaults($project));
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'created_by' => auth()->id()];
    }

    protected function afterCreate(): void
    {
        $this->getRecord()->recalculate();
    }

    protected function getRedirectUrl(): string
    {
        return OfferResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
