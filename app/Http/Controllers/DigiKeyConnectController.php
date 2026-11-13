<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Distributors\DistributorResource;
use App\Models\Distributor;
use App\Services\Distributors\DigiKeyClient;
use App\Services\Distributors\DistributorException;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * "Connect with DigiKey": the administrator logs in at DigiKey and allows the ERP (3-legged OAuth), so
 * the barcode and account APIs can be used. DigiKey sends the user back to one fixed address, which
 * has to be the OAuth callback URL of the app in DigiKey's developer portal.
 */
class DigiKeyConnectController extends Controller
{
    public static function callbackUrl(): string
    {
        return route('distributors.digikey.callback');
    }

    public function connect(Request $request, Distributor $distributor): RedirectResponse
    {
        abort_unless((bool) $request->user()?->isAdmin(), 403);
        $client = $distributor->client();
        abort_unless($client instanceof DigiKeyClient && $client->isConfigured(), 404);

        $state = Str::random(40);
        $request->session()->put('digikey_oauth', ['state' => $state, 'distributor' => $distributor->id]);

        return redirect()->away($client->authorizeUrl(self::callbackUrl(), $state));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->isAdmin(), 403);
        $pending = $request->session()->pull('digikey_oauth');
        abort_unless(is_array($pending) && hash_equals((string) $pending['state'], (string) $request->query('state')), 403);

        $distributor = Distributor::query()->findOrFail($pending['distributor']);
        $client = $distributor->client();
        abort_unless($client instanceof DigiKeyClient, 404);

        try {
            if (filled($request->query('error'))) {
                throw new DistributorException((string) ($request->query('error_description') ?: $request->query('error')));
            }

            $client->connect((string) $request->query('code'), self::callbackUrl());

            // The Account ID for the orders, when it was not filled in yet and the user has one account.
            if (blank($distributor->api_customer_number) && count($accounts = $client->accounts()) === 1) {
                $distributor->forceFill(['api_customer_number' => $accounts[0]['id']])->save();
            }

            Notification::make()->title(__('erp.distributors.digikey.connected'))->success()->send();
        } catch (DistributorException $exception) {
            Notification::make()->title(__('erp.distributors.digikey.connect_failed'))->body($exception->getMessage())->danger()->persistent()->send();
        }

        return redirect(DistributorResource::getUrl('edit', ['record' => $distributor]));
    }
}
