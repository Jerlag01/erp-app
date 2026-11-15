<?php

namespace App\Http\Controllers;

use App\Models\CableCalculation;
use App\Support\CableCalculationDocuments;
use App\Support\PdfAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * A cable calculation as PDF (an overview of its circuits, then each circuit), on the letterhead of the battery documents (the 604 delivery note).
 */
class CableCalculationPdfController
{
    public function __invoke(Request $request, CableCalculation $calculation): Response
    {
        abort_unless(Gate::allows('view', $calculation) && PdfAccess::canGenerate($request->user(), 'cable_calculations'), 403);

        $documents = app(CableCalculationDocuments::class);
        $pdf = $documents->render($calculation);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$documents->fileName($calculation).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
