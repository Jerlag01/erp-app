<?php

namespace App\Http\Controllers;

use App\Services\Peppol\PeppolGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Webhook for the Peppol access point: it POSTs each received UBL document here (raw XML body).
 * The URL holds a long random secret from Settings > Finance. Received invoices become expenses
 * that still need validation.
 */
class PeppolInboundController
{
    public static function url(): ?string
    {
        $secret = settings()->get('finance.peppol_inbound_secret');

        return filled($secret) ? route('peppol.inbound', ['secret' => $secret]) : null;
    }

    public function __invoke(Request $request, PeppolGateway $gateway, string $secret): JsonResponse
    {
        $expected = (string) settings()->get('finance.peppol_inbound_secret');

        abort_unless(modules()->finance() && settings()->get('finance.peppol_enabled') && $expected !== '' && hash_equals($expected, $secret), 404);

        $xml = (string) $request->getContent();
        abort_if($xml === '' || strlen($xml) > 30 * 1024 * 1024, 422, 'Empty or too large document.');

        try {
            $result = $gateway->receive($xml);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        return response()->json(['expense' => $result['expense']->id, 'duplicate' => $result['duplicate']], $result['duplicate'] ? 200 : 201);
    }
}
