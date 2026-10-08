<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Certificate and medical PDFs from the employee register, for administrators only.
 */
class EmployeeDocumentController
{
    public function __invoke(Employee $employee, string $kind, int $id): StreamedResponse
    {
        abort_unless(Gate::allows('view', $employee), 403);

        $record = $kind === 'certificate'
            ? $employee->certificates()->find($id)
            : $employee->medicalChecks()->find($id);

        abort_if($record === null || blank($record->document) || ! Storage::disk(Employee::DISK)->exists($record->document), 404);

        return Storage::disk(Employee::DISK)->response($record->document, $record->document_name ?: basename($record->document), [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:",
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }
}
