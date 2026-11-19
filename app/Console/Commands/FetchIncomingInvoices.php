<?php

namespace App\Console\Commands;

use App\Services\Doccle\DoccleClient;
use App\Services\Inbox\FetchInvoiceMailbox;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('finance:fetch-incoming')]
#[Description('Fetch incoming invoices from Doccle and from the invoice mailbox folder (when set in Settings > Finance)')]
class FetchIncomingInvoices extends Command
{
    public function handle(DoccleClient $doccle, FetchInvoiceMailbox $mailbox): int
    {
        if (! modules()->finance()) {
            $this->components->info('The finance module is off.');

            return self::SUCCESS;
        }

        $failed = false;

        if (DoccleClient::isConfigured()) {
            try {
                $result = $doccle->fetch();
                $this->components->info("Doccle: {$result['documents']} document(s), {$result['created']} new, {$result['duplicates']} already known.");
            } catch (Throwable $exception) {
                report($exception);
                $this->components->error('Doccle: '.$exception->getMessage());
                $failed = true;
            }
        }

        if (FetchInvoiceMailbox::isConfigured()) {
            try {
                $result = $mailbox->run();
                $this->components->info("Mailbox: {$result['messages']} message(s), {$result['created']} new, {$result['duplicates']} already known.");
            } catch (Throwable $exception) {
                report($exception);
                $this->components->error('Mailbox: '.$exception->getMessage());
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
