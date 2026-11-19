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
 * Sends a final invoice or credit note: by e-mail (template + PDF + UBL) to the client's communication
 * point the invoice is addressed to, else to all its points that receive invoices (else the accounting
 * address), and over Peppol when the client is reachable and an access point is set.
 */
class SendInvoice
{
    public function __construct(protected InvoicePdf $pdf, protected PeppolGateway $peppol) {}

    /**
     * @param  string|list<string>|null  $email  one or more addresses; none = the invoice's recipients
     * @return array{email: ?string, peppol: ?string}
     */
    public function handle(Invoice $invoice, string|array|null $email = null, bool $viaPeppol = true): array
    {
        if ($invoice->isDraft()) {
            throw new InvalidArgumentException('Finalise the invoice before sending it.');
        }

        $invoice->loadMissing('client.contacts', 'contact');
        $emails = self::addresses($email) ?: $invoice->recipients();
        $email = $emails !== [] ? implode(', ', $emails) : null;
        $pdf = $this->pdf->render($invoice);
        $peppolStatus = null;

        if ($viaPeppol && settings()->get('finance.peppol_enabled') && $invoice->client->peppolParticipant()) {
            $peppolStatus = $this->peppol->send($invoice, $pdf)->status;
        }

        if ($email !== null) {
            Mail::to($emails)->send(new SystemMail(
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
            'sent_to' => mb_substr((string) ($email ?? $invoice->client->peppolParticipant()), 0, 1000),
        ])->saveQuietly();

        return ['email' => $email, 'peppol' => $peppolStatus];
    }

    /**
     * "a@x.be, b@y.be" or a list: the valid, distinct addresses.
     *
     * @param  string|list<string>|null  $email
     * @return list<string>
     */
    public static function addresses(string|array|null $email): array
    {
        $list = is_array($email) ? $email : preg_split('/[,;\s]+/', (string) $email);

        return array_values(array_unique(array_filter(array_map(fn (mixed $address): string => strtolower(trim((string) $address)), (array) $list), fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false)));
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
