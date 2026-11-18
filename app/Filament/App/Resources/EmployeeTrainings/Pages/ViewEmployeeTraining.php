<?php

namespace App\Filament\App\Resources\EmployeeTrainings\Pages;

use App\Filament\Admin\Pages\TrainingMatrix;
use App\Filament\App\Resources\EmployeeTrainings\EmployeeTrainingResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeTraining extends ViewRecord
{
    protected static string $resource = EmployeeTrainingResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->fullName();
    }

    /**
     * Back to the overview rather than the plain list.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [TrainingMatrix::getUrl() => __('erp.training_matrix.title'), __('erp.resources.employee.singular')];
    }
}
