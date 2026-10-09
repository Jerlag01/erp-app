<?php

namespace App\Filament\Admin\Resources\InterventionReports\Pages;

use App\Filament\Admin\Resources\InterventionReports\InterventionReportResource;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewInterventionReport extends ViewRecord
{
    protected static string $resource = InterventionReportResource::class;

    public function getTitle(): string
    {
        $project = $this->getRecord()->project;

        return trim(($project->work_order_number ? 'WO '.$project->work_order_number.' · ' : '').$project->reference);
    }

    protected function getHeaderActions(): array
    {
        return [
            InterventionReportResource::approveAction()->after(fn () => $this->refreshFormData(['status', 'po_number', 'reviewed_at', 'review_note'])),
            InterventionReportResource::rejectAction()->after(fn () => $this->refreshFormData(['status', 'reviewed_at', 'review_note'])),
            InterventionReportResource::pdfAction(),
            Action::make('project')
                ->label(__('erp.interventions.open'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => ProjectResource::getUrl('view', ['record' => $this->getRecord()->project], panel: 'admin')),
        ];
    }
}
