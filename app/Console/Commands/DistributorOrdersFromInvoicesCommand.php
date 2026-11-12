<?php

namespace App\Console\Commands;

use App\Actions\Purchasing\RecordDistributorDeliveries;
use App\Models\Expense;
use App\Services\Peppol\UblReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Reads the Peppol invoices that were already received into Purchasing > Orders (new ones are recorded
 * when they arrive). Invoices that were recorded before are skipped, so it can run again safely.
 */
#[Signature('distributors:orders-from-invoices {--since= : only invoices dated from this day (Y-m-d)}')]
#[Description('Build the distributor orders from the Peppol invoices already received')]
class DistributorOrdersFromInvoicesCommand extends Command
{
    public function handle(RecordDistributorDeliveries $deliveries, UblReader $reader): int
    {
        $count = 0;

        Expense::query()
            ->where('source', 'peppol')
            ->whereNotNull('ubl_path')
            ->when($this->option('since'), fn ($query, string $since) => $query->whereDate('invoice_date', '>=', $since))
            ->orderBy('invoice_date')
            ->each(function (Expense $expense) use ($deliveries, $reader, &$count): void {
                try {
                    $xml = Storage::disk(Expense::DISK)->get($expense->ubl_path);

                    if ($xml !== null && $deliveries->fromInvoice($expense, $reader->read($xml)) !== null) {
                        $count++;
                    }
                } catch (Throwable $exception) {
                    $this->components->warn("{$expense->supplier_invoice_number}: {$exception->getMessage()}");
                }
            });

        $this->components->info("{$count} invoice(s) recorded in the distributor orders.");

        return self::SUCCESS;
    }
}
