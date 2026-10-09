<?php

namespace App\Actions\Interventions;

use App\Enums\InterventionReportStatus;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\InterventionReport;
use App\Models\ProjectPoNumber;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An administrator approves a closed intervention, entering its PO number (added to the project's
 * PO numbers for invoicing; project status "invoicing to do"), or rejects it with a reason so the
 * employee corrects and closes it again (project status back to "in progress").
 */
class ReviewIntervention
{
    public function __construct(protected InterventionPdf $pdf) {}

    public function approve(InterventionReport $report, User $admin, string $poNumber, ?string $note = null): void
    {
        $poNumber = trim($poNumber);

        if (! preg_match(ProjectPoNumber::PATTERN, $poNumber)) {
            throw ValidationException::withMessages(['po_number' => __('erp.projects.po_number_invalid')]);
        }

        DB::transaction(function () use ($report, $admin, $poNumber, $note): void {
            $report->update([
                'status' => InterventionReportStatus::Approved,
                'po_number' => $poNumber,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $report->project->poNumbers()->firstOrCreate(['number' => $poNumber]);
            $report->project->update(['status' => ProjectStatus::InvoicingToDo]);
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
