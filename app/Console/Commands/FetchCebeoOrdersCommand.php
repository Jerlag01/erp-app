<?php

namespace App\Console\Commands;

use App\Actions\Purchasing\RecordDistributorDeliveries;
use App\Actions\Purchasing\SyncDistributorOrders;
use App\Models\Distributor;
use App\Models\DistributorOrder;
use App\Models\Expense;
use App\Services\Distributors\CebeoClient;
use App\Services\Distributors\DistributorClients;
use App\Services\Peppol\UblReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Fetches the Cebeo orders of one month into Purchasing > Orders.
 *
 * Cebeo cannot list or search orders: an order can only be asked for by Cebeo's order number, and those
 * numbers are shared by all Cebeo customers (thousands a day), so trying every number is out of the
 * question. The order numbers are therefore collected from what we have: the orders already in the ERP,
 * and Cebeo's Peppol invoices (their order numbers, and the delivery notes on them, which name the
 * orders). Invoices up to two months after the month are read, since an order is often invoiced later.
 * Each order is then fetched with a pause between the calls (--sleep), at most --max calls per run, and
 * kept when it was ordered in that month. Orders that are not fully delivered are then followed up every
 * hour (distributors:sync-orders).
 */
#[Signature('distributors:cebeo-orders
    {month : the month (1-12)}
    {year : the year, e.g. 2026}
    {--distributor= : the id of the Cebeo distributor (the first one with a Cebeo connection by default)}
    {--numbers= : extra Cebeo order numbers, separated by commas}
    {--sleep=1000 : milliseconds to wait between two calls to Cebeo}
    {--max=300 : at most this many calls to Cebeo in this run}
    {--dry-run : only show which order numbers and delivery notes were found, without asking Cebeo}')]
#[Description('Fetch the Cebeo orders of a month (gently, one order at a time)')]
class FetchCebeoOrdersCommand extends Command
{
    protected int $calls = 0;

    public function handle(DistributorClients $clients, SyncDistributorOrders $sync, RecordDistributorDeliveries $deliveries, UblReader $reader): int
    {
        $this->calls = 0;
        $month = (int) $this->argument('month');
        $year = (int) $this->argument('year');

        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            $this->components->error('Give a month (1-12) and a year, e.g. distributors:cebeo-orders 10 2026.');

            return self::INVALID;
        }

        $distributor = $this->distributor();
        $client = $distributor ? $clients->lookupFor($distributor) : null;

        if (! $client instanceof CebeoClient) {
            $this->components->error('No Cebeo distributor with a working connection (customer number, user and password).');

            return self::FAILURE;
        }

        $start = Carbon::create($year, $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        [$numbers, $deliveryNotes] = $this->collect($distributor, $deliveries, $reader, $start, $end);

        $this->components->info(sprintf('%s %d: %d order number(s) and %d delivery note(s) to look at.', $start->translatedFormat('F'), $year, count($numbers), count($deliveryNotes)));

        if ($this->option('dry-run')) {
            $this->line('Orders: '.(implode(', ', $numbers) ?: '—'));
            $this->line('Delivery notes: '.(implode(', ', $deliveryNotes) ?: '—'));

            return self::SUCCESS;
        }

        // The delivery notes name the orders on them.
        foreach ($deliveryNotes as $note) {
            if (! $this->mayCall()) {
                break;
            }

            try {
                foreach ($client->dispatchAdvice($note) as $info) {
                    if ($info->number !== '' && ! in_array($info->number, $numbers, true)) {
                        $numbers[] = $info->number;
                    }
                }
            } catch (Throwable $exception) {
                $this->components->warn("Delivery note {$note}: {$exception->getMessage()}");
            }
        }

        $stored = $skipped = $unknown = 0;

        foreach ($numbers as $number) {
            if (! $this->mayCall()) {
                $this->components->warn('Stopped after '.$this->calls.' calls (--max). Run it again to continue: orders fetched already are skipped when delivered.');
                break;
            }

            try {
                $info = $client->order($number);
            } catch (Throwable $exception) {
                $this->components->warn("Order {$number}: {$exception->getMessage()}");

                continue;
            }

            if ($info === null) {
                $unknown++;

                continue;
            }

            // Only the orders placed in that month.
            if ($info->orderedAt !== null && ! $info->orderedAt->between($start, $end)) {
                $skipped++;

                continue;
            }

            $sync->store($distributor, $info);
            $stored++;
        }

        $this->components->info("{$stored} order(s) stored, {$skipped} from another month, {$unknown} unknown at Cebeo ({$this->calls} call(s)).");

        return self::SUCCESS;
    }

    protected function distributor(): ?Distributor
    {
        $query = Distributor::query()->where('price_provider', 'cebeo');

        return $this->option('distributor') ? $query->find((int) $this->option('distributor')) : $query->orderBy('id')->first();
    }

    /**
     * The order numbers and delivery notes known for the month: the ERP's orders, the Cebeo invoices and
     * the numbers given on the command line. Delivered orders already in the ERP are not asked again.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function collect(Distributor $distributor, RecordDistributorDeliveries $deliveries, UblReader $reader, Carbon $start, Carbon $end): array
    {
        $numbers = DistributorOrder::query()
            ->where('distributor_id', $distributor->id)
            ->whereNotNull('external_number')
            ->whereBetween('ordered_at', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('status', [DistributorOrder::DELIVERED, DistributorOrder::CANCELLED])
            ->pluck('external_number')
            ->all();
        $done = DistributorOrder::query()
            ->where('distributor_id', $distributor->id)
            ->whereIn('status', [DistributorOrder::DELIVERED, DistributorOrder::CANCELLED])
            ->whereNotNull('synced_at')
            ->pluck('external_number')
            ->all();
        $notes = [];

        Expense::query()
            ->where('source', 'peppol')
            ->whereNotNull('ubl_path')
            ->whereBetween('invoice_date', [$start->toDateString(), $end->copy()->addMonthsNoOverflow(2)->endOfMonth()->toDateString()])
            ->each(function (Expense $expense) use ($distributor, $deliveries, $reader, &$numbers, &$notes): void {
                try {
                    if ($deliveries->distributorFor($expense->supplier_vat, $expense->supplier_name)?->is($distributor) !== true) {
                        return;
                    }

                    $data = $reader->read((string) Storage::disk(Expense::DISK)->get($expense->ubl_path));
                } catch (Throwable) {
                    return;
                }

                array_push($numbers, ...array_map(fn (string $number): string => ltrim($number, '0'), array_filter($data['sales_order_ids'], 'ctype_digit')));
                array_push($notes, ...array_map(fn (string $number): string => ltrim($number, '0'), array_filter($data['despatch_references'], 'ctype_digit')));
            });

        foreach (explode(',', (string) $this->option('numbers')) as $number) {
            if (ctype_digit($number = trim($number))) {
                $numbers[] = ltrim($number, '0');
            }
        }

        $numbers = array_values(array_diff(array_unique(array_filter($numbers)), $done));

        // A delivery note already recorded on an order does not need asking again.
        $recorded = DistributorOrder::query()->where('distributor_id', $distributor->id)->whereNotNull('raw->delivery_notes')->pluck('raw')
            ->flatMap(fn ($raw): array => (array) ($raw['delivery_notes'] ?? []))
            ->map(fn ($note): string => (string) $note)
            ->all();

        return [$numbers, array_values(array_diff(array_unique(array_filter($notes)), $recorded))];
    }

    /**
     * Whether another call to Cebeo is allowed; waits between two calls.
     */
    protected function mayCall(): bool
    {
        if ($this->calls >= max(1, (int) $this->option('max'))) {
            return false;
        }

        if ($this->calls > 0) {
            Sleep::usleep(max(0, (int) $this->option('sleep')) * 1000);
        }

        $this->calls++;

        return true;
    }
}
