<?php

namespace App\Actions\Interventions;

use App\Enums\InterventionReportStatus;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\InterventionReport;
use App\Models\User;
use App\Support\Interventions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * An administrator approves a closed intervention (project status "invoicing to do"; no PO number is asked,
 * the report shows the project's PO number, entered on the project), or rejects it with a reason so the
 * employee corrects and closes it again (project status back to "in progress"). With several COW codes
 * (one report each) the project goes to "invoicing to do" once all of them are approved.
 */
class ReviewIntervention
{
    public function __construct(protected InterventionPdf $pdf, protected Interventions $interventions) {}

    public function approve(InterventionReport $report, User $admin, ?string $note = null): void
    {
        DB::transaction(function () use ($report, $admin, $note): void {
            $project = $report->project;

            $report->update([
                'status' => InterventionReportStatus::Approved,
                // For the PDF: the (first) PO number of the project, if any.
                'po_number' => $project->poNumbers()->oldest('id')->value('number'),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $project->unsetRelation('interventionReports');

            if ($this->interventions->allApproved($project)) {
                $project->update(['status' => ProjectStatus::InvoicingToDo]);
            }
        });

        $this->pdf->store($report->fresh());
        $this->notifyCloser($report, 'approved');
    }

    public function reject(InterventionReport $report, User $admin, string $note): void
    {
        DB::transaction(function () use ($report, $admin, $note): void {
            $report->update([
                'status' => InterventionReportStatus::Rejected,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $report->project->update(['status' => ProjectStatus::InProgress]);
        });

        $this->pdf->store($report->fresh());
        $this->notifyCloser($report, 'rejected');
    }

    protected function notifyCloser(InterventionReport $report, string $outcome): void
    {
        $closer = $report->closer;

        if (! $closer?->is_active) {
            return;
        }

        $locale = $closer->preferredLocale();
        $notification = Notification::make()
            ->title(__("erp.interventions.notifications.{$outcome}_title", ['reference' => $report->project->reference], $locale))
            ->body($report->review_note)
            ->icon($outcome === 'approved' ? 'heroicon-o-check-badge' : 'heroicon-o-arrow-uturn-left')
            ->actions([
                Action::make('open')
                    ->label(__('erp.interventions.open', locale: $locale))
                    ->url(ProjectResource::getUrl('view', ['record' => $report->project], panel: 'app')),
            ]);

        ($outcome === 'approved' ? $notification->success() : $notification->danger())->sendToDatabase($closer);
    }
}
