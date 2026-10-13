<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Certificate PDFs (also of certificates employees submitted) and medical check-up files from the
 * employee register, for administrators only.
 * A medical check-up holds several files; $file is the position in its list.
 */
class EmployeeDocumentController
{
    public function __invoke(Employee $employee, string $kind, int $id, int $file = 0): StreamedResponse
    {
        abort_unless(Gate::allows('view', $employee), 403);

        if ($kind === 'certificate' || $kind === 'request') {
            $record = $kind === 'certificate' ? $employee->certificates()->find($id) : $employee->certificateRequests()->find($id);
            $path = $record?->document;
            $name = $record?->document_name;
        } else {
            $record = $employee->medicalChecks()->find($id);
            $path = $record?->documents[$file] ?? null;
            $name = $path ? $record->documentName($path) : null;
        }

        $disk = Storage::disk(Employee::DISK);
        abort_if(blank($path) || ! $disk->exists($path), 404);

        $mime = $disk->mimeType($path) ?: 'application/octet-stream';

        return $disk->response($path, $name ?: basename($path), [
            'Content-Type' => in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true) ? $mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:",
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }
}
