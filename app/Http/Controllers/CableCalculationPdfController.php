<?php

namespace App\Http\Controllers;

use App\Models\CableCalculation;
use App\Support\BatteryTemplates;
use App\Support\PdfAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A cable calculation as PDF, on the letterhead of the battery documents (the 604 delivery note).
 */
class CableCalculationPdfController
{
    public function __invoke(Request $request, CableCalculation $calculation): Response
    {
        abort_unless(Gate::allows('view', $calculation) && PdfAccess::canGenerate($request->user(), 'cable_calculations'), 403);

        $calculation->loadMissing(['project', 'creator']);

        $pdf = Pdf::loadView('cable-calculations.pdf', [
            'calculation' => $calculation,
            'result' => (array) $calculation->result,
            'background' => BatteryTemplates::backgroundDataUri(),
        ])->setPaper('a4', 'portrait')->setOption('isFontSubsettingEnabled', true)->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::slug(__('erp.cable_calculator.singular').' '.$calculation->name).'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
