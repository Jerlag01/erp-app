<?php

namespace App\Actions\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\MailTemplateKey;
use App\Mail\SystemMail;
use App\Models\Invoice;
use App\Services\Peppol\PeppolGateway;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Sends a final invoice or credit note: by e-mail (template + PDF + UBL) to the client's
 * accounting address, and over Peppol when the client is reachable and an access point is set.
 */
class SendInvoice
{
    public function __construct(protected InvoicePdf $pdf, protected PeppolGateway $peppol) {}

    /**
     * @return array{email: ?string, peppol: ?string}
     */
    public function handle(Invoice $invoice, ?string $email = null, bool $viaPeppol = true): array
    {
        if ($invoice->isDraft()) {
            throw new InvalidArgumentException('Finalise the invoice before sending it.');
        }

        $invoice->loadMissing('client');
        $email = $email ?: $invoice->client->invoiceEmail();
        $pdf = $this->pdf->render($invoice);
        $peppolStatus = null;

        if ($viaPeppol && settings()->get('finance.peppol_enabled') && $invoice->client->peppolParticipant()) {
            $peppolStatus = $this->peppol->send($invoice, $pdf)->status;
        }

        if ($email !== null) {
            Mail::to($email)->send(new SystemMail(
                $invoice->isCreditNote() ? MailTemplateKey::CreditNoteSent : MailTemplateKey::InvoiceSent,
                $invoice->client->locale ?: config('app.locale'),
                self::placeholders($invoice),
                [
                    ['data' => $pdf, 'name' => InvoicePdf::filename($invoice), 'mime' => 'application/pdf'],
                    ['data' => $this->peppol->ublFor($invoice), 'name' => str_replace('.pdf', '.xml', InvoicePdf::filename($invoice)), 'mime' => 'application/xml'],
                ],
            ));
        }

        if ($email === null && $peppolStatus !== PeppolGateway::SENT) {
            throw new InvalidArgumentException('The client has no e-mail address and could not be reached over Peppol.');
        }

        $invoice->forceFill([
            'status' => $invoice->isPaid() ? InvoiceStatus::Paid : InvoiceStatus::Sent,
            'sent_at' => now(),
            'sent_to' => $email ?? $invoice->client->peppolParticipant(),
        ])->saveQuietly();

        return ['email' => $email, 'peppol' => $peppolStatus];
    }

    /**
     * Values for the {placeholders} in invoice and reminder mails.
     *
     * @return array<string, string|int|null>
     */
    public static function placeholders(Invoice $invoice): array
    {
        return [
            'client_name' => $invoice->client->company_name,
            'number' => $invoice->number,
            'amount' => Invoice::money($invoice->total),
            'issue_date' => $invoice->issue_date->format('d/m/Y'),
            'due_date' => $invoice->due_date?->format('d/m/Y'),
            'structured_reference' => $invoice->structured_reference,
            'iban' => trim(chunk_split((string) settings()->get('finance.iban'), 4, ' ')),
            'subject' => $invoice->subject,
            'days_overdue' => $invoice->daysOverdue(),
        ];
    }
}
