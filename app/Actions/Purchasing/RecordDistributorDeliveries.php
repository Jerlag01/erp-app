<?php

namespace App\Actions\Purchasing;

use App\Models\Client;
use App\Models\Distributor;
use App\Models\DistributorOrder;
use App\Models\DistributorOrderLine;
use App\Models\Expense;
use App\Services\Distributors\DistributorClients;
use App\Services\Distributors\DistributorException;
use App\Services\Distributors\OrderInfo;
use App\Services\Distributors\OrderLineInfo;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Rexel and Cebeo have no API that lists the orders placed with them. What they do send is an invoice
 * through Peppol (and Cebeo a delivery note that can be fetched by its number). Those tell which items
 * were delivered, with the distributor's item numbers and the reference we gave when ordering. This
 * records them in Purchasing > Orders: the order they belong to (the distributor's order number, else
 * the same customer reference) gets the delivered quantities, otherwise a delivered order is added. The
 * order is linked to our order reference when it is in the reference (DistributorOrder::linkReference).
 * Each invoice and delivery note counts only once. When the distributor looks orders up by number
 * (Cebeo) and the document names the order, the whole order is fetched instead.
 */
class RecordDistributorDeliveries
{
    public function __construct(protected SyncDistributorOrders $sync) {}

    /**
     * The distributor that sent a Peppol invoice: by VAT number, else (for a distributor without a VAT
     * number) by the connection's or the distributor's name in the supplier name.
     */
    public function distributorFor(?string $vat, ?string $supplierName): ?Distributor
    {
        // "BE 0123.456.789", "BE0123456789" and "0123456789" are the same number.
        $digits = fn (?string $value): string => preg_replace('/\D/', '', (string) Client::normaliseVat($value));
        $vat = $digits($vat);
        $distributors = Distributor::query()->get();

        if ($vat !== '' && ($match = $distributors->first(fn (Distributor $distributor): bool => $digits($distributor->vat_number) === $vat))) {
            return $match;
        }

        $name = Str::lower(Str::ascii((string) $supplierName));

        if ($name === '') {
            return null;
        }

        // By name only for a distributor without a VAT number (one with a number is recognised by it).
        $distributors = $distributors->filter(fn (Distributor $distributor): bool => blank($distributor->vat_number));

        return $distributors->first(fn (Distributor $distributor): bool => filled($distributor->price_provider) && str_contains($name, Str::lower(Str::ascii(DistributorClients::options()[$distributor->price_provider] ?? (string) $distributor->price_provider))))
            ?? $distributors->first(fn (Distributor $distributor): bool => mb_strlen((string) $distributor->name) >= 4 && str_contains($name, Str::lower(Str::ascii((string) $distributor->name))));
    }

    /**
     * Records a received Peppol invoice (see UblReader::read for $data) when it comes from a distributor.
     */
    public function fromInvoice(Expense $expense, array $data): ?DistributorOrder
    {
        if (($data['is_credit_note'] ?? false) || ($data['lines'] ?? []) === []) {
            return null;
        }

        $distributor = $this->distributorFor($data['supplier_vat'] ?? null, $data['supplier_name'] ?? null);

        if ($distributor === null || $this->alreadyRecorded($distributor, 'invoices', $expense->id)) {
            return null;
        }

        $date = filled($data['invoice_date'] ?? null) ? Carbon::parse($data['invoice_date']) : today();
        $reference = $data['order_reference'] ?? null;
        $info = new OrderInfo(
            number: filled($data['sales_order_id'] ?? null) ? (string) $data['sales_order_id'] : '',
            customerReference: filled($reference) ? Str::limit((string) $reference, 250, '') : null,
            lines: array_map(fn (array $line): OrderLineInfo => new OrderLineInfo(
                distributorReference: $line['seller_id'],
                manufacturerReference: $line['manufacturer_id'],
                description: $line['name'] ? Str::limit($line['name'], 250, '') : null,
                quantity: $line['quantity'],
                quantityDelivered: $line['quantity'],
                unit: $line['unit'],
                netPrice: $line['price'],
                deliveredAt: $date,
            ), $data['lines']),
        );

        $note = trim(__('erp.distributor_orders.from_invoice', ['number' => $data['supplier_invoice_number'] ?? '?'])
            .(filled($data['buyer_reference'] ?? null) && ($data['buyer_reference'] ?? null) !== $reference ? ' · '.$data['buyer_reference'] : ''));

        return $this->record($distributor, $info, DistributorOrder::SOURCE_INVOICE, 'invoices', $expense->id, $date, $note, $expense->id);
    }

    /**
     * Records a Cebeo delivery note (CebeoClient::dispatchAdvice): one entry per order on it.
     *
     * @param  list<OrderInfo>  $orders
     * @return list<DistributorOrder>
     */
    public function fromDeliveryNote(Distributor $distributor, string $number, array $orders): array
    {
        if ($this->alreadyRecorded($distributor, 'delivery_notes', $number)) {
            return [];
        }

        return array_map(fn (OrderInfo $info): DistributorOrder => $this->record(
            $distributor,
            $info,
            DistributorOrder::SOURCE_DELIVERY_NOTE,
            'delivery_notes',
            $number,
            $info->deliveredAt ?? today(),
            __('erp.distributor_orders.from_delivery_note', ['number' => $number]),
        ), $orders);
    }

    protected function alreadyRecorded(Distributor $distributor, string $key, int|string $id): bool
    {
        return DistributorOrder::query()->where('distributor_id', $distributor->id)->whereJsonContains("raw->{$key}", $id)->exists();
    }

    protected function record(Distributor $distributor, OrderInfo $info, string $source, string $key, int|string $id, CarbonInterface $date, string $note, ?int $expenseId = null): DistributorOrder
    {
        // A distributor that looks orders up by number (Cebeo): the whole order as it has it now.
        if ($info->number !== '' && ($order = $this->fetched($distributor, $info->number, $source)) !== null) {
            $raw = (array) ($order->raw ?? []);
            $raw[$key] = [...($raw[$key] ?? []), $id];
            $order->update([
                'expense_id' => $expenseId ?? $order->expense_id,
                'notes' => trim(($order->notes ? $order->notes."\n" : '').$note),
                'raw' => $raw,
            ]);

            return $order;
        }

        return DB::transaction(function () use ($distributor, $info, $source, $key, $id, $date, $note, $expenseId): DistributorOrder {
            $order = $this->existingOrder($distributor, $info) ?? new DistributorOrder([
                'distributor_id' => $distributor->id,
                'external_number' => $info->number !== '' ? $info->number : null,
                'customer_reference' => $info->customerReference,
                'status' => DistributorOrder::DELIVERED,
                'source' => $source,
            ]);

            $raw = (array) ($order->raw ?? []);
            $raw[$key] = [...($raw[$key] ?? []), $id];

            $order->fill([
                'external_number' => $order->external_number ?: ($info->number !== '' ? $info->number : null),
                'customer_reference' => $order->customer_reference ?: $info->customerReference,
                'expense_id' => $expenseId ?? $order->expense_id,
                'notes' => trim(($order->notes ? $order->notes."\n" : '').$note),
                'raw' => $raw,
                'synced_at' => now(),
            ])->save();

            foreach ($info->lines as $line) {
                $this->deliver($order, $line, $date);
            }

            $lines = $order->lines()->get();
            $delivered = $lines->every(fn (DistributorOrderLine $line): bool => $line->isDelivered());
            $order->update([
                'status' => $delivered ? DistributorOrder::DELIVERED : DistributorOrder::PARTIAL,
                'delivered_at' => $delivered ? $date : null,
                'total' => $order->total ?? round($lines->sum(fn (DistributorOrderLine $line): float => (float) $line->quantity * (float) $line->net_price), 2),
            ]);

            return $order;
        });
    }

    protected function fetched(Distributor $distributor, string $number, string $source): ?DistributorOrder
    {
        try {
            return $this->sync->fetch($distributor, $number, $source);
        } catch (DistributorException $exception) {
            Log::warning('Distributor order could not be fetched', ['distributor' => $distributor->id, 'number' => $number, 'error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * The order this delivery belongs to: the distributor's order number, else an order that is not
     * fully delivered with the same customer reference containing our order number.
     */
    protected function existingOrder(Distributor $distributor, OrderInfo $info): ?DistributorOrder
    {
        $orders = DistributorOrder::query()->where('distributor_id', $distributor->id);

        if ($info->number !== '' && ($order = (clone $orders)->where('external_number', $info->number)->first())) {
            return $order;
        }

        // Only a reference with our order number is specific enough ("Webshop" or a site name is not).
        if (DistributorOrder::referencesIn($info->customerReference) === []) {
            return null;
        }

        return $orders->where('customer_reference', $info->customerReference)
            ->whereNotIn('status', [DistributorOrder::DELIVERED, DistributorOrder::CANCELLED])
            ->latest('id')
            ->first();
    }

    /**
     * Adds a delivered quantity to the matching order line that still waits for it, else adds the line.
     */
    protected function deliver(DistributorOrder $order, OrderLineInfo $line, CarbonInterface $date): void
    {
        $match = $order->lines()->get()->first(function (DistributorOrderLine $existing) use ($line): bool {
            $same = (filled($line->distributorReference) && $existing->distributor_reference === $line->distributorReference)
                || (filled($line->manufacturerReference) && $existing->manufacturer_reference === $line->manufacturerReference);

            return $same && ! $existing->isDelivered();
        });

        if ($match === null && $order->lines()->get()->contains(fn (DistributorOrderLine $existing): bool => (filled($line->distributorReference) && $existing->distributor_reference === $line->distributorReference)
            || (filled($line->manufacturerReference) && $existing->manufacturer_reference === $line->manufacturerReference))) {
            // Already fully delivered (e.g. as the distributor reported it): not counted twice.
            return;
        }

        if ($match !== null) {
            $quantity = (float) $match->quantity_delivered + $line->quantityDelivered;
            $match->update([
                'quantity_delivered' => $quantity,
                'delivered_at' => $quantity >= (float) $match->quantity ? $date : $match->delivered_at,
                'net_price' => $match->net_price ?? $line->netPrice,
            ]);

            return;
        }

        $order->lines()->create([
            'line_number' => ((int) $order->lines()->max('line_number')) + 1,
            'distributor_reference' => $line->distributorReference,
            'manufacturer_reference' => $line->manufacturerReference,
            'description' => $line->description,
            'quantity' => max($line->quantity, $line->quantityDelivered),
            'quantity_delivered' => $line->quantityDelivered,
            'unit' => $line->unit,
            'net_price' => $line->netPrice,
            'delivered_at' => $date,
        ]);
    }
}
