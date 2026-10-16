<?php

namespace App\Http\Controllers;

use App\Actions\Batteries\BatteryDocument;
use App\Models\EnersysDossier;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A document of an Enersys dossier as PDF, or all of them in a ZIP file (see BatteryDocumentController).
 */
class EnersysDocumentController extends Controller
{
    public function __invoke(Request $request, EnersysDossier $dossier, string $document, BatteryDocument $documents): Response
    {
        return app(BatteryDocumentController::class)->respond($request, $dossier, $document, $documents);
    }
}
