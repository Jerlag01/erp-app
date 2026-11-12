<?php

namespace App\Http\Controllers;

use App\Actions\Purchasing\RecordDistributorDeliveries;
use App\Actions\Purchasing\SyncDistributorOrders;
use App\Models\Distributor;
use App\Services\Distributors\CebeoClient;
use App\Services\Distributors\DistributorException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook for documents a distributor sends us by HTTPS POST (raw XML body): Cebeo's order
 * confirmations (for every order, also those placed in the e-shop or by Cebeo staff) and delivery notes
 * in cebeoXML. They land in Purchasing > Orders. The URL (shown on the distributor) holds a secret
 * derived from the app key, so it cannot be guessed.
 */
class DistributorInboundController
{
    public static function secret(Distributor $distributor): string
    {
        return substr(hash_hmac('sha256', 'distributor-inbound:'.$distributor->id, (string) config('app.key')), 0, 40);
    }

    public static function url(Distributor $distributor): ?string
    {
        return $distributor->exists && $distributor->price_provider === 'cebeo'
            ? route('distributors.inbound', ['distributor' => $distributor, 'secret' => self::secret($distributor)])
            : null;
    }

    public function __invoke(Request $request, Distributor $distributor, string $secret, SyncDistributorOrders $sync, RecordDistributorDeliveries $deliveries): JsonResponse
    {
        abort_unless($distributor->price_provider === 'cebeo' && hash_equals(self::secret($distributor), $secret), 404);

        $xml = (string) $request->getContent();
        abort_if($xml === '' || strlen($xml) > 10 * 1024 * 1024, 422, 'Empty or too large document.');

        try {
            $document = CebeoClient::parseDocument($xml);
        } catch (DistributorException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        $orders = $document['delivery_note'] !== null
            ? $deliveries->fromDeliveryNote($distributor, $document['delivery_note'], $document['orders'])
            : array_map(fn ($info) => $sync->store($distributor, $info), array_filter($document['orders'], fn ($info): bool => $info->number !== ''));

        return response()->json(['orders' => array_map(fn ($order): int => $order->id, $orders)]);
    }
}
