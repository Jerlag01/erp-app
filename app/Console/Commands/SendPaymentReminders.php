<?php

namespace App\Console\Commands;

use App\Actions\Finance\SendPaymentReminder;
use App\Models\Invoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('finance:send-reminders')]
#[Description('Send the automatic payment reminders for overdue invoices (when switched on in Settings > Finance)')]
class SendPaymentReminders extends Command
{
    public function handle(SendPaymentReminder $reminder): int
    {
        if (! modules()->finance() || ! settings()->get('finance.reminders_enabled')) {
            $this->components->info('Payment reminders are switched off.');

            return self::SUCCESS;
        }

        $sent = 0;

        Invoice::query()->outstanding()->whereDate('due_date', '<', today())->with('client')->each(function (Invoice $invoice) use ($reminder, &$sent): void {
            if (SendPaymentReminder::isDue($invoice)) {
                try {
                    $level = $reminder->handle($invoice);
                    $sent++;
                    $this->line("Reminder {$level} sent for {$invoice->number}.");
                } catch (Throwable $exception) {
                    report($exception);
                    $this->components->error("{$invoice->number}: {$exception->getMessage()}");
                }
            }
        });

        $this->components->info("{$sent} reminder(s) sent.");

        return self::SUCCESS;
    }
}
