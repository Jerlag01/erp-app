<?php

namespace App\Http\Controllers;

use App\Enums\ProjectFileSection;
use App\Models\Absence;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The certificate of an absence, for the employee it belongs to and administrators. PDFs and
 * pictures a browser can show open inline; other picture types (e.g. HEIC) are downloaded.
 */
class AbsenceDocumentController
{
    public function __invoke(Absence $absence): StreamedResponse
    {
        abort_unless(Gate::allows('view', $absence), 403);
        abort_if(! $absence->hasDocument() || ! Storage::disk(Absence::DISK)->exists($absence->document), 404);

        $mime = Storage::disk(Absence::DISK)->mimeType($absence->document) ?: 'application/octet-stream';
        $inline = $mime === 'application/pdf' || ProjectFileSection::isPreviewableImage($mime);

        return Storage::disk(Absence::DISK)->response($absence->document, $absence->document_name ?: basename($absence->document), [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:",
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }
}
