<?php

namespace App\Http\Controllers;

use App\Actions\Finance\InvoicePdf;
use App\Models\Expense;
use App\Models\Invoice;
use App\Policies\InvoicePolicy;
use App\Services\Peppol\PeppolGateway;
use App\Support\PdfAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invoice PDFs and Peppol UBL files, and the documents of incoming invoices (PDF, UBL and the
 * validation signature), for users with the matching finance rights.
 */
class FinanceDocumentController
{
    public function invoice(Invoice $invoice, string $format, InvoicePdf $pdf, PeppolGateway $peppol): Response
    {
        abort_unless(Gate::allows('view', $invoice), 403);

        if ($format === 'ubl') {
            abort_if($invoice->isDraft(), 404);

            return response($peppol->ublFor($invoice, $pdf->render($invoice)), 200, [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="'.str_replace('.pdf', '.xml', InvoicePdf::filename($invoice)).'"',
            ]);
        }

        abort_unless(PdfAccess::canGenerate(auth()->user(), InvoicePolicy::area($invoice->type)), 403);

        return response($pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.InvoicePdf::filename($invoice).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function expense(Expense $expense, string $kind): Response
    {
        abort_unless(Gate::allows('view', $expense), 403);

        $path = match ($kind) {
            'document' => $expense->document,
            'ubl' => $expense->ubl_path,
            'signature' => $expense->signature_path,
        };

        abort_if(blank($path) || ! Storage::disk(Expense::DISK)->exists($path), 404);
        abort_if($kind === 'document' && PdfAccess::isPdf($expense->document_name ?: $path) && ! PdfAccess::canDownload(auth()->user(), 'expenses'), 403);

        $mime = Storage::disk(Expense::DISK)->mimeType($path) ?: 'application/octet-stream';
        $inline = in_array($mime, ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);

        return Storage::disk(Expense::DISK)->response($path, $kind === 'document' ? ($expense->document_name ?: basename($path)) : basename($path), [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:",
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }
}
