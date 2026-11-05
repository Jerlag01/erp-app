<?php

namespace App\Http\Controllers;

use App\Actions\It\ItDeliveryReports;
use App\Models\ItDeliveryReport;
use App\Models\SignedDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The delivery report of an IT asset as PDF (as it is now, in the employee's language) and the signed
 * documents of an employee: for the employee, administrators and, for reports, those who hand out IT assets.
 * The mobile app opens them through short-lived signed links (the phone's browser has no session).
 */
class ItDeliveryReportController
{
    public function show(Request $request, ItDeliveryReport $report): Response
    {
        abort_unless($report->mayBeViewedBy($request->user()), 403);

        return $this->pdf($report);
    }

    public function signedShow(ItDeliveryReport $report): Response
    {
        return $this->pdf($report);
    }

    protected function pdf(ItDeliveryReport $report): Response
    {
        return response(app(ItDeliveryReports::class)->render($report), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.ItDeliveryReports::filename($report).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function signedDocument(Request $request, SignedDocument $document): StreamedResponse
    {
        abort_unless($document->mayBeOpenedBy($request->user()), 403);

        return $this->download($document);
    }

    public function signedDocumentLink(SignedDocument $document): StreamedResponse
    {
        return $this->download($document);
    }

    protected function download(SignedDocument $document): StreamedResponse
    {
        abort_unless(Storage::disk(SignedDocument::DISK)->exists($document->path), 404);

        return Storage::disk(SignedDocument::DISK)->response($document->path, $document->fileName(), ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }
}
