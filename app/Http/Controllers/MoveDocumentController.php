<?php

namespace App\Http\Controllers;

use App\Actions\Batteries\BatteryDocument;
use App\Models\MoveDossier;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A document of a battery move (605) as PDF, or all of them in a ZIP file (see BatteryDocumentController).
 */
class MoveDocumentController extends Controller
{
    public function __invoke(Request $request, MoveDossier $dossier, string $document, BatteryDocument $documents): Response
    {
        return app(BatteryDocumentController::class)->respond($request, $dossier, $document, $documents);
    }
}
