<?php

namespace App\Actions\Interventions;

use App\Models\InterventionReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The intervention report as PDF (A4 landscape) for approval and invoicing. A copy in the platform's
 * language is stored when the intervention is closed and again when it is reviewed (for the project
 * archive); downloading renders it in the reader's language.
 */
class InterventionPdf
{
    public function render(InterventionReport $report, ?string $locale = null): string
    {
        $report->loadMissing(['project.category', 'project.workSite', 'project.interventionSubcategory', 'project.poNumbers', 'materials.unit', 'positions', 'closer', 'coWorkers', 'reviewer', 'workSite']);
        $previous = app()->getLocale();

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        try {
            return Pdf::loadView('interventions.report-pdf', ['report' => $report, 'project' => $report->project])->setPaper('a4', 'landscape')->output();
        } finally {
            app()->setLocale($previous);
        }
    }

    public function store(InterventionReport $report): string
    {
        $path = 'interventions/'.$report->project_id.'/'.self::filename($report);
        $previous = $report->pdf_path;

        Storage::disk(InterventionReport::DISK)->put($path, $this->render($report, config('app.locale')));
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
