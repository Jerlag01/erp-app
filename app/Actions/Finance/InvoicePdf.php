<?php

namespace App\Actions\Finance;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * The PDF of an invoice or credit note, in the client's language.
 */
class InvoicePdf
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['client.country', 'lines', 'poNumbers', 'project', 'creditedInvoice.poNumbers']);
        $previous = app()->getLocale();
        app()->setLocale($invoice->client->locale ?: config('app.locale'));

        try {
            return Pdf::loadView('finance.invoice-pdf', ['invoice' => $invoice])->setPaper('a4')->output();
        } finally {
            app()->setLocale($previous);
        }
    }

    public static function filename(Invoice $invoice): string
    {
        return Str::slug(($invoice->number ?: 'draft-'.$invoice->id)).'.pdf';
    }
}
