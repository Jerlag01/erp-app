<?php

namespace App\Http\Controllers;

use App\Actions\Interventions\InterventionPdf;
use App\Models\InterventionReport;
use App\Support\Interventions;
use App\Support\PdfAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PDF of a closed intervention, for administrators (approval) and those who see the
 * project's finance (invoicing), in the reader's language.
 */
class InterventionPdfController extends Controller
{
    public function __invoke(Request $request, InterventionReport $report, Interventions $interventions, InterventionPdf $pdf): Response
    {
        abort_unless($interventions->canDownloadPdf($request->user(), $report->project) && PdfAccess::canGenerate($request->user(), 'projects'), 403);

        // Rendered for the reader, in their language; the stored copy stays for the project archive.
        return response($pdf->render($report), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.InterventionPdf::filename($report).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
