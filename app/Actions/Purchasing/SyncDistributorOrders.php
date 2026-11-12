<?php

namespace App\Actions\Purchasing;

use App\Models\Distributor;
use App\Models\DistributorOrder;
use App\Services\Distributors\DistributorClients;
use App\Services\Distributors\DistributorException;
use App\Services\Distributors\OrderInfo;
use App\Services\Distributors\OrderLineInfo;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the orders of a distributor up to date through its API: a connection that lists orders gives the
 * new and changed ones; a connection that only looks orders up by number (Cebeo) refreshes every order
 * that is not delivered or cancelled yet (ordered quantities, delivered quantities, status, expected
 * delivery). Each order is linked to our own order reference when that reference is in it
 * (DistributorOrder::linkReference). Runs every hour (distributors:sync-orders).
 */
class SyncDistributorOrders
{
    /** Orders older than this are no longer looked up. */
    public const REFRESH_MONTHS = 12;

    public function __construct(protected DistributorClients $clients) {}

    /**
     * Whether this distributor's connection gives orders (a list, or one by number).
     */
    public function supports(Distributor $distributor): bool
    {
        return (bool) $this->clients->ordersFor($distributor)?->supportsOrders()
            || $this->clients->lookupFor($distributor) !== null;
    }

    /**
     * @return int how many orders were added or updated
     *
     * @throws DistributorException
     */
    public function handle(Distributor $distributor): int
    {
        $source = $this->clients->ordersFor($distributor);
        $lookup = $this->clients->lookupFor($distributor);

        if (($source === null || ! $source->supportsOrders()) && $lookup === null) {
            throw new DistributorException(__('erp.distributor_orders.no_api', ['name' => $distributor->name]));
        }

        $started = now();
        $count = 0;

        if ($source !== null && $source->supportsOrders()) {
            foreach ($source->orders($distributor->orders_synced_at) as $info) {
                $this->store($distributor, $info);
                $count++;
            }
        }

        if ($lookup !== null) {
            $open = DistributorOrder::query()
                ->where('distributor_id', $distributor->id)
                ->whereNotNull('external_number')
                ->whereNotIn('status', [DistributorOrder::DELIVERED, DistributorOrder::CANCELLED])
                ->where('created_at', '>=', now()->subMonths(self::REFRESH_MONTHS))
                ->pluck('external_number');

            foreach ($open as $number) {
                if ($this->fetch($distributor, (string) $number) !== null) {
                    $count++;
                }
            }
        }

        $distributor->forceFill(['orders_synced_at' => $started])->saveQuietly();

        return $count;
    }

    /**
     * Fetches one order by the distributor's number and stores it; null when the distributor does not
     * know it or cannot look orders up.
     *
     * @throws DistributorException
     */
    public function fetch(Distributor $distributor, string $number, string $source = DistributorOrder::SOURCE_API): ?DistributorOrder
    {
        $info = $this->clients->lookupFor($distributor)?->order($number);

        return $info === null ? null : $this->store($distributor, $info, $source);
    }

    /**
     * Adds or updates the order as the distributor has it now. A known order keeps its source, its link to
     * our order reference and the invoices and delivery notes recorded on it.
     */
    public function store(Distributor $distributor, OrderInfo $info, string $source = DistributorOrder::SOURCE_API): DistributorOrder
    {
        return DB::transaction(function () use ($distributor, $info, $source): DistributorOrder {
            $order = DistributorOrder::query()->firstOrNew(['distributor_id' => $distributor->id, 'external_number' => $info->number]);
            $order->fill([
                'customer_reference' => $info->customerReference ?? $order->customer_reference,
                'ordered_at' => $info->orderedAt ?? $order->ordered_at,
                'status' => in_array($info->status, DistributorOrder::STATUSES, true) ? $info->status : DistributorOrder::OPEN,
                'expected_delivery' => $info->expectedDelivery,
                'delivered_at' => $info->deliveredAt,
                'total' => $info->total,
                'source' => $order->exists ? $order->source : $source,
                'raw' => [...(array) ($order->raw ?? []), ...$info->raw],
                'synced_at' => now(),
            ])->save();

            // The lines as the distributor has them now.
            $order->lines()->delete();

            foreach (array_values($info->lines) as $index => $line) {
                /** @var OrderLineInfo $line */
                $order->lines()->create([
                    'line_number' => $index + 1,
                    'distributor_reference' => $line->distributorReference,
                    'manufacturer_reference' => $line->manufacturerReference,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'quantity_delivered' => $line->quantityDelivered,
                    'unit' => $line->unit,
                    'net_price' => $line->netPrice,
                    'expected_delivery' => $line->expectedDelivery,
                    'delivered_at' => $line->deliveredAt,
                ]);
            }

            return $order;
        });
    }
}
