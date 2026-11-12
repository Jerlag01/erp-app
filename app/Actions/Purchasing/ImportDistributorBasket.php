<?php

namespace App\Actions\Purchasing;

use App\Models\Distributor;
use App\Models\DistributorOrder;
use App\Services\Distributors\DistributorBasketSource;
use App\Services\Distributors\DistributorClients;
use App\Services\Distributors\DistributorException;
use Illuminate\Support\Facades\DB;

/**
 * Imports the webshop basket of a distributor (Rexel NetStore, Cebeo e-shop) as an open order in
 * Purchasing > Orders, with the items, references, net prices and expected delivery. Our order
 * reference is chosen (or found in the reference) when importing; the delivery is filled in later from
 * the Peppol invoice or the Cebeo delivery note (RecordDistributorDeliveries).
 */
class ImportDistributorBasket
{
    public function __construct(protected DistributorClients $clients, protected SyncDistributorOrders $sync) {}

    public function source(Distributor $distributor): ?DistributorBasketSource
    {
        $client = $this->clients->for($distributor);

        return $client instanceof DistributorBasketSource && $client->isConfigured() ? $client : null;
    }

    /**
     * @param  array{order_reference_id?: ?int, customer_reference?: ?string, external_number?: ?string, notes?: ?string}  $details
     * @return DistributorOrder|null null when the basket is empty
     *
     * @throws DistributorException
     */
    public function handle(Distributor $distributor, array $details = [], ?int $userId = null): ?DistributorOrder
    {
        $source = $this->source($distributor) ?? throw new DistributorException(__('erp.distributor_orders.no_basket', ['name' => $distributor->name]));
        $basket = $source->basket();

        if ($basket === null) {
            return null;
        }

        // Cebeo's open order already has its order number: kept under it, so it is followed up every hour.
        if ($basket->number !== '') {
            $order = $this->sync->store($distributor, $basket, DistributorOrder::SOURCE_BASKET);
            $order->update(array_filter([
                'external_number' => filled($details['external_number'] ?? null) ? trim($details['external_number']) : null,
                'customer_reference' => filled($details['customer_reference'] ?? null) ? trim($details['customer_reference']) : null,
                'order_reference_id' => $details['order_reference_id'] ?? null,
                'notes' => filled($details['notes'] ?? null) ? trim($details['notes']) : null,
                'created_by' => $order->created_by ?? $userId,
            ], fn ($value): bool => $value !== null));

            return $order;
        }

        return DB::transaction(function () use ($distributor, $basket, $details, $userId): DistributorOrder {
            $order = DistributorOrder::query()->create([
                'distributor_id' => $distributor->id,
                'external_number' => filled($details['external_number'] ?? null) ? trim($details['external_number']) : null,
                'customer_reference' => filled($details['customer_reference'] ?? null) ? trim($details['customer_reference']) : $basket->customerReference,
                'order_reference_id' => $details['order_reference_id'] ?? null,
                'ordered_at' => $basket->orderedAt ?? today(),
                'status' => DistributorOrder::OPEN,
                'expected_delivery' => collect($basket->lines)->max(fn ($line) => $line->expectedDelivery),
                'total' => $basket->total,
                'source' => DistributorOrder::SOURCE_BASKET,
                'notes' => filled($details['notes'] ?? null) ? trim($details['notes']) : null,
                'created_by' => $userId,
            ]);

            foreach (array_values($basket->lines) as $index => $line) {
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
                ]);
            }

            return $order;
        });
    }
}
