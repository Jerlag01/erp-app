<?php

namespace App\Actions\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Starts a credit note for a final invoice: client, project, subject, references and every line
 * are copied. They stay locked unless someone with "credit_notes.override" switches changes on.
 */
class CreateCreditNote
{
    public function handle(Invoice $invoice, User $user): Invoice
    {
        if ($invoice->isCreditNote() || $invoice->isDraft()) {
            throw new InvalidArgumentException('Only final invoices can be credited.');
        }

        return DB::transaction(function () use ($invoice, $user): Invoice {
            $creditNote = Invoice::create([
                'type' => InvoiceType::CreditNote,
                'status' => InvoiceStatus::Draft,
                'client_id' => $invoice->client_id,
                'project_id' => $invoice->project_id,
                'credited_invoice_id' => $invoice->id,
                'issue_date' => today(),
                'subject' => $invoice->subject,
                'client_reference' => $invoice->client_reference,
                'notes' => $invoice->notes,
                'created_by' => $user->id,
            ]);

            foreach ($invoice->lines as $line) {
                $creditNote->lines()->create($line->only(['description', 'quantity', 'unit', 'unit_price', 'vat_rate', 'is_standard', 'sort_order']));
            }

            $creditNote->recalculate();

            return $creditNote;
        });
    }
}
