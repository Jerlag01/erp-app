<?php

namespace App\Http\Controllers;

use App\Actions\Interventions\InterventionPdf;
use App\Models\InterventionReport;
use App\Support\Interventions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PDF of a closed intervention, for administrators (approval) and those who see the
 * project's finance (invoicing).
 */
class InterventionPdfController extends Controller
{
    public function __invoke(Request $request, InterventionReport $report, Interventions $interventions, InterventionPdf $pdf): Response
    {
        abort_unless($interventions->canDownloadPdf($request->user(), $report->project), 403);

        $disk = Storage::disk(InterventionReport::DISK);

        if (blank($report->pdf_path) || ! $disk->exists($report->pdf_path)) {
            $pdf->store($report);
        }

        return $disk->response($report->fresh()->pdf_path, InterventionPdf::filename($report), [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
