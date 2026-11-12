<?php

namespace App\Services\Distributors;

use Carbon\CarbonInterface;

/**
 * An order as a distributor's API gives it (see DistributorOrderSource).
 */
final readonly class OrderInfo
{
    /**
     * @param  list<OrderLineInfo>  $lines
     * @param  array<string, mixed>  $raw  the distributor's own data, kept for later
     */
    public function __construct(
        public string $number,
        public ?string $customerReference = null,
        public ?CarbonInterface $orderedAt = null,
        public string $status = 'open',
        public ?CarbonInterface $expectedDelivery = null,
        public ?CarbonInterface $deliveredAt = null,
        public ?float $total = null,
        public array $lines = [],
        public array $raw = [],
    ) {}
}
