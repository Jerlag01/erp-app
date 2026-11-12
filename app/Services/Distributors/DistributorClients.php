<?php

namespace App\Services\Distributors;

use App\Models\Distributor;

/**
 * Picks the catalogue connection that belongs to a distributor ("price provider").
 */
class DistributorClients
{
    /**
     * @var array<string, class-string<DistributorClient>>
     */
    public const PROVIDERS = [
        'cebeo' => CebeoClient::class,
        'rexel' => RexelClient::class,
        'wurth' => WurthClient::class,
    ];

    public function for(Distributor $distributor): ?DistributorClient
    {
        $class = self::PROVIDERS[$distributor->price_provider] ?? null;

        return $class ? new $class($distributor) : null;
    }

    /**
     * The connection's order source, when it has one (Purchasing > Orders).
     */
    public function ordersFor(Distributor $distributor): ?DistributorOrderSource
    {
        $client = $this->for($distributor);

        return $client instanceof DistributorOrderSource ? $client : null;
    }

    /**
     * The connection's lookup of one order by its number, when it has one and is set up (Cebeo).
     */
    public function lookupFor(Distributor $distributor): ?DistributorOrderLookup
    {
        $client = $this->for($distributor);

        return $client instanceof DistributorOrderLookup && $client->isConfigured() ? $client : null;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return ['cebeo' => 'Cebeo', 'rexel' => 'Rexel', 'wurth' => 'Würth'];
    }
}
