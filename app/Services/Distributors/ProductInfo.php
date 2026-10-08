<?php

namespace App\Services\Distributors;

/**
 * What a distributor's catalogue returns for one article.
 */
final readonly class ProductInfo
{
    public function __construct(
        public ?float $price = null,
        public ?string $imageUrl = null,
        public ?string $name = null,
        public ?string $description = null,
    ) {}
}
