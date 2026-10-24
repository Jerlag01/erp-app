<?php

namespace App\Http\Controllers;

use App\Actions\Batteries\BatteryRoomDocument;
use App\Models\BatteryRoomDossier;
use App\Support\PdfAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PDFs of a battery room ("battery_rooms.generate_pdf") and its uploads: photos, the ventilation
 * calculation and the BTV report (a PDF also needs "battery_rooms.download_pdf").
 */
class BatteryRoomController extends Controller
{
    public function document(Request $request, BatteryRoomDossier $dossier, string $document, BatteryRoomDocument $documents): Response
    {
        abort_unless(Gate::allows('view', $dossier) && PdfAccess::canGenerate($request->user(), 'battery_rooms'), 403);

        return response($documents->render($dossier, $document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.BatteryRoomDocument::filename($dossier, $document).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function file(Request $request, BatteryRoomDossier $dossier, string $kind, ?int $index = null): Response
    {
        abort_unless(Gate::allows('view', $dossier), 403);

        $path = $dossier->filePath($kind, $index);
        $disk = Storage::disk(BatteryRoomDossier::DISK);
        abort_unless(filled($path) && $disk->exists($path), 404);

        $name = (string) $dossier->fileName($kind, $index);
        abort_if(PdfAccess::isPdf($name) && ! PdfAccess::canDownload($request->user(), 'battery_rooms'), 403);

        return $disk->download($path, $name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
