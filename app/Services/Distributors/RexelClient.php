<?php

namespace App\Services\Distributors;

/**
 * Rexel (rexel.be). Prices are customer-specific; access goes through Rexel's customer API.
 */
class RexelClient extends PendingApiClient
{
    protected function name(): string
    {
        return 'Rexel';
    }

    protected function baseUrl(): ?string
    {
        return config('services.rexel.url');
    }
}
