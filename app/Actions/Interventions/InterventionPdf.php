<?php

namespace App\Actions\Interventions;

use App\Models\InterventionReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The intervention report as PDF for approval and invoicing. It is stored when the intervention
 * is closed and again when it is reviewed, so it always shows the latest state.
 */
class InterventionPdf
{
    public function render(InterventionReport $report): string
    {
        $report->loadMissing(['project.category', 'project.workSite', 'project.interventionSubcategory', 'project.poNumbers', 'materials.unit', 'positions', 'closer', 'coWorkers', 'reviewer', 'workSite']);

        return Pdf::loadView('interventions.report-pdf', ['report' => $report, 'project' => $report->project])->setPaper('a4')->output();
    }

    public function store(InterventionReport $report): string
    {
        $path = 'interventions/'.$report->project_id.'/'.self::filename($report);
        $previous = $report->pdf_path;

        Storage::disk(InterventionReport::DISK)->put($path, $this->render($report));
        $report->forceFill(['pdf_path' => $path])->saveQuietly();

        if (filled($previous) && $previous !== $path) {
            Storage::disk(InterventionReport::DISK)->delete($previous);
        }

        return $path;
    }

    public static function filename(InterventionReport $report): string
    {
        return Str::slug(implode('-', array_filter([$report->project->reference, $report->project->work_order_number ? 'WO'.$report->project->work_order_number : null]))).'.pdf';
    }
}
