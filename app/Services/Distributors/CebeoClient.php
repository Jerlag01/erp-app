<?php

namespace App\Services\Distributors;

/**
 * Cebeo (cebeo.be). Prices are customer-specific; access goes through Cebeo's customer API.
 */
class CebeoClient extends PendingApiClient
{
    protected function name(): string
    {
        return 'Cebeo';
    }

    protected function baseUrl(): ?string
    {
        return config('services.cebeo.url');
    }
}
