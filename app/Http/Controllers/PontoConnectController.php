<?php

namespace App\Http\Controllers;

use App\Actions\Banking\ImportBankTransactions;
use App\Filament\Resources\BankAccounts\BankAccountResource;
use App\Models\BankAccount;
use App\Services\Banking\PontoClient;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Connect Ponto": whoever manages the bank accounts logs in at Ponto and chooses which accounts
 * (ING, Belfius, KBC…) the ERP may read. Ponto sends the user back to one fixed address, which has to
 * be the redirect URI of the Ponto Connect application.
 */
class PontoConnectController extends Controller
{
    public function connect(Request $request, PontoClient $ponto): RedirectResponse
    {
        abort_unless(Gate::allows('create', BankAccount::class), 403);
        abort_unless(PontoClient::isConfigured(), 404);

        $state = Str::random(40);
        $verifier = Str::random(64);
        $request->session()->put('ponto_oauth', ['state' => $state, 'verifier' => $verifier]);

        return redirect()->away($ponto->authorizationUrl($state, $verifier));
    }

    public function callback(Request $request, PontoClient $ponto, ImportBankTransactions $import): RedirectResponse
    {
        abort_unless(Gate::allows('create', BankAccount::class), 403);
        $pending = $request->session()->pull('ponto_oauth');
        abort_unless(is_array($pending) && hash_equals((string) $pending['state'], (string) $request->query('state')), 403);

        try {
            if (filled($request->query('error'))) {
                throw new \RuntimeException((string) ($request->query('error_description') ?: $request->query('error')));
            }

            $ponto->exchangeCode((string) $request->query('code'), (string) $pending['verifier'], $request->user()?->id);
            $result = $import->fromPonto();

            Notification::make()
                ->title(__('erp.banking.ponto.connected'))
                ->body(__('erp.banking.synced', ['accounts' => $result['accounts'], 'added' => $result['added'], 'matched' => $result['matched']]))
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title(__('erp.banking.ponto.connect_failed'))->body(Str::limit($exception->getMessage(), 300))->danger()->persistent()->send();
        }

        return redirect(BankAccountResource::getUrl(panel: 'app'));
    }
}
