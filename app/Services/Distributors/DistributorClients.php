<?php

namespace App\Services\Distributors;

use App\Models\Distributor;

/**
 * Picks the catalogue connection that belongs to a distributor ("price provider").
 */
class DistributorClients
{
    /**
     * @var array<string, class-string<PendingApiClient>>
     */
    public const PROVIDERS = [
        'cebeo' => CebeoClient::class,
        'rexel' => RexelClient::class,
    ];

    public function for(Distributor $distributor): ?DistributorClient
    {
        $class = self::PROVIDERS[$distributor->price_provider] ?? null;

        return $class ? new $class($distributor) : null;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return ['cebeo' => 'Cebeo', 'rexel' => 'Rexel'];
    }
}
