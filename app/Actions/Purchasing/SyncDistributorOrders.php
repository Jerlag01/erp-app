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
 * Fetches the orders of a distributor through its API (when it offers them) and stores them with their
 * lines: new orders are added, known ones updated (status, delivery). Each order is linked to our own
 * order reference when that reference is in it (DistributorOrder::linkReference).
 */
class SyncDistributorOrders
{
    public function __construct(protected DistributorClients $clients) {}

    /**
     * Whether this distributor's connection gives orders.
     */
    public function supports(Distributor $distributor): bool
    {
        return (bool) $this->clients->ordersFor($distributor)?->supportsOrders();
    }

    /**
     * @return int how many orders were added or updated
     *
     * @throws DistributorException
     */
    public function handle(Distributor $distributor): int
    {
        $source = $this->clients->ordersFor($distributor);

        if ($source === null || ! $source->supportsOrders()) {
            throw new DistributorException(__('erp.distributor_orders.no_api', ['name' => $distributor->name]));
        }

        $started = now();
        $count = 0;

        foreach ($source->orders($distributor->orders_synced_at) as $info) {
            $this->store($distributor, $info);
            $count++;
        }

        $distributor->forceFill(['orders_synced_at' => $started])->saveQuietly();

        return $count;
    }

    public function store(Distributor $distributor, OrderInfo $info): DistributorOrder
    {
        return DB::transaction(function () use ($distributor, $info): DistributorOrder {
            $order = DistributorOrder::query()->firstOrNew(['distributor_id' => $distributor->id, 'external_number' => $info->number]);
            $order->fill([
                'customer_reference' => $info->customerReference,
                'ordered_at' => $info->orderedAt,
                'status' => in_array($info->status, DistributorOrder::STATUSES, true) ? $info->status : DistributorOrder::OPEN,
                'expected_delivery' => $info->expectedDelivery,
                'delivered_at' => $info->deliveredAt,
                'total' => $info->total,
                'source' => DistributorOrder::SOURCE_API,
                'raw' => $info->raw,
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
