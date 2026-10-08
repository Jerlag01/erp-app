<?php

namespace App\Actions\Finance;

use App\Enums\MailTemplateKey;
use App\Mail\SystemMail;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Sends the next payment reminder (1st, 2nd, then the 3rd and last) for an overdue invoice,
 * with the PDF attached.
 */
class SendPaymentReminder
{
    public const MAX_LEVEL = 3;

    public function __construct(protected InvoicePdf $pdf) {}

    public function handle(Invoice $invoice, ?User $user = null): int
    {
        $email = $invoice->client->invoiceEmail();

        if (! $invoice->isOverdue() || $email === null) {
            throw new InvalidArgumentException('Only overdue invoices of clients with an e-mail address get reminders.');
        }

        $level = min($invoice->reminder_level + 1, self::MAX_LEVEL);

        Mail::to($email)->send(new SystemMail(
            MailTemplateKey::reminder($level),
            $invoice->client->locale ?: config('app.locale'),
            SendInvoice::placeholders($invoice),
            [['data' => $this->pdf->render($invoice), 'name' => InvoicePdf::filename($invoice), 'mime' => 'application/pdf']],
        ));

        $invoice->forceFill(['reminder_level' => $level, 'last_reminder_at' => now()])->saveQuietly();
        $invoice->reminders()->create(['level' => $level, 'sent_to' => $email, 'sent_by' => $user?->id, 'sent_at' => now()]);

        return $level;
    }

    /**
     * Whether the automatic run should send the next reminder today: the invoice is the set
     * number of days past due for the next level, and that level was not sent yet.
     */
    public static function isDue(Invoice $invoice): bool
    {
        if (! $invoice->isOverdue() || ! $invoice->client->send_reminders || $invoice->reminder_level >= self::MAX_LEVEL) {
            return false;
        }

        $days = (int) settings()->get('finance.reminder_'.($invoice->reminder_level + 1).'_days', 7 * ($invoice->reminder_level + 1));

        return $invoice->daysOverdue() >= $days;
    }
}
