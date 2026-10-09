<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Models\ProjectCategory;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    /**
     * "New intervention" opens this page with ?category= preselected.
     */
    protected function afterFill(): void
    {
        $category = ProjectCategory::query()->where('is_active', true)->find(request()->integer('category'));

        if ($category) {
            $this->data['project_category_id'] = $category->id;
            $this->data['status'] = $category->numbering->isIntervention() ? ProjectStatus::ToBeExecuted->value : ($this->data['status'] ?? null);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...ProjectForm::clean($data), 'created_by' => auth()->id()];
    }

    protected function getRedirectUrl(): string
    {
        return ProjectResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
