<?php

namespace App\Services\Distributors;

use Carbon\CarbonInterface;

/**
 * One line of an order from a distributor's API.
 */
final readonly class OrderLineInfo
{
    public function __construct(
        public ?string $distributorReference = null,
        public ?string $manufacturerReference = null,
        public ?string $description = null,
        public float $quantity = 0,
        public float $quantityDelivered = 0,
        public ?string $unit = null,
        public ?float $netPrice = null,
        public ?CarbonInterface $expectedDelivery = null,
        public ?CarbonInterface $deliveredAt = null,
    ) {}
}
