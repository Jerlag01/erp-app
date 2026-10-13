<?php

namespace App\Http\Controllers;

use App\Actions\Batteries\BatteryDocument;
use App\Models\BatteryDossier;
use App\Support\PdfAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A document of a battery dossier as PDF, or all of them in a ZIP file. The invoice request needs
 * the price rights.
 */
class BatteryDocumentController extends Controller
{
    public function __invoke(Request $request, BatteryDossier $dossier, string $document, BatteryDocument $documents): Response
    {
        abort_unless(Gate::allows('view', $dossier) && PdfAccess::canGenerate($request->user(), 'batteries'), 403);

        if ($document === 'all') {
            $list = array_values(array_filter(array_keys(BatteryDocument::DOCUMENTS), fn (string $name): bool => $name !== 'invoice_request' || Gate::allows('viewPrices', $dossier)));

            return response()->download($documents->zip($dossier, $list), Str::slug('batterijdossier '.$dossier->reference()).'.zip')->deleteFileAfterSend();
        }

        abort_unless(array_key_exists($document, BatteryDocument::DOCUMENTS), 404);
        abort_if($document === 'invoice_request' && ! Gate::allows('viewPrices', $dossier), 403);

        return response($documents->render($dossier, $document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.BatteryDocument::filename($dossier, $document).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
