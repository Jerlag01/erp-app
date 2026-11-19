<?php

namespace App\Services\Banking;

use App\Models\IntegrationState;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ponto Connect (Isabel / Ibanity): one connection to the company's accounts at ING, Belfius, KBC and
 * the other Belgian banks, for reading balances and movements only (scope "ai", no payments).
 *
 * The banks' own PSD2 APIs are only open to licensed payment institutions (TPP with eIDAS
 * certificates); Ponto holds that licence. Needs a Ponto Connect application (client id and secret)
 * and its client certificate for mutual TLS, set in Settings > Finance. Ponto refreshes the
 * accounts itself about four times a day.
 */
class PontoClient
{
    public const STATE_KEY = 'ponto';

    protected const API = 'https://api.ibanity.com/ponto-connect';

    public static function isConfigured(): bool
    {
        return filled(settings()->get('finance.ponto_client_id'))
            && filled(settings()->get('finance.ponto_client_secret'))
            && filled(settings()->get('finance.ponto_certificate'))
            && filled(settings()->get('finance.ponto_private_key'));
    }

    public static function isConnected(): bool
    {
        return self::isConfigured() && filled(IntegrationState::read(self::STATE_KEY)['refresh_token'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function state(): array
    {
        return (array) IntegrationState::read(self::STATE_KEY, []);
    }

    public static function redirectUri(): string
    {
        return route('banking.ponto.callback');
    }

    /**
     * The Ponto page where an administrator chooses the accounts to share (PKCE).
     */
    public function authorizationUrl(string $state, string $verifier): string
    {
        $base = settings()->get('finance.ponto_sandbox') ? 'https://sandbox-authorization.myponto.com/oauth2/auth' : 'https://authorization.myponto.com/oauth2/auth';

        return $base.'?'.http_build_query([
            'client_id' => settings()->get('finance.ponto_client_id'),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => 'ai offline_access',
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchangeCode(string $code, string $verifier, ?int $userId = null): void
    {
        $this->storeTokens($this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => self::redirectUri(),
            'code_verifier' => $verifier,
        ]), ['connected_at' => now()->toIso8601String(), 'connected_by' => $userId]);
    }

    public function disconnect(): void
    {
        $refresh = self::state()['refresh_token'] ?? null;

        if ($refresh) {
            try {
                $this->http(false)->asForm()->withBasicAuth(...$this->credentials())->post(self::API.'/oauth2/revoke', ['token' => $refresh]);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        IntegrationState::forget(self::STATE_KEY);
    }

    /**
     * The shared accounts.
     *
     * @return list<array{id: string, iban: string, name: ?string, currency: string, balance: ?float, holder: ?string}>
     */
    public function accounts(): array
    {
        $accounts = [];

        foreach ($this->pages(self::API.'/accounts?page[limit]=100') as $item) {
            $attributes = $item['attributes'] ?? [];
            $accounts[] = [
                'id' => (string) $item['id'],
                'iban' => (string) ($attributes['reference'] ?? ''),
                'name' => $attributes['description'] ?? $attributes['product'] ?? null,
                'currency' => (string) ($attributes['currency'] ?? 'EUR'),
                'balance' => isset($attributes['currentBalance']) ? (float) $attributes['currentBalance'] : null,
                'holder' => $attributes['holderName'] ?? null,
            ];
        }

        return $accounts;
    }

    /**
     * The movements of an account, newest first, until the given date.
     *
     * @return list<array{external_id: string, booking_date: string, value_date: ?string, amount: float, currency: string, counterparty_name: ?string, counterparty_iban: ?string, remittance: ?string, structured_reference: ?string}>
     */
    public function transactions(string $accountId, string $since): array
    {
        $transactions = [];

        foreach ($this->pages(self::API.'/accounts/'.rawurlencode($accountId).'/transactions?page[limit]=100', $since) as $item) {
            $attributes = $item['attributes'] ?? [];
            $remittance = $attributes['remittanceInformation'] ?? null;
            $structured = ($attributes['remittanceInformationType'] ?? null) === 'structured' ? preg_replace('/\D/', '', (string) $remittance) : null;

            $transactions[] = [
                'external_id' => 'ponto:'.$item['id'],
                'booking_date' => substr((string) ($attributes['executionDate'] ?? $attributes['valueDate'] ?? now()->toDateString()), 0, 10),
                'value_date' => isset($attributes['valueDate']) ? substr((string) $attributes['valueDate'], 0, 10) : null,
                'amount' => round((float) ($attributes['amount'] ?? 0), 2),
                'currency' => (string) ($attributes['currency'] ?? 'EUR'),
                'counterparty_name' => $attributes['counterpartName'] ?? null,
                'counterparty_iban' => $attributes['counterpartReference'] ?? null,
                'remittance' => $remittance ?: ($attributes['description'] ?? null),
                'structured_reference' => $structured && strlen($structured) === 12 ? $structured : null,
            ];
        }

        return $transactions;
    }

    /**
     * Walks the JSON:API pages; stops at items older than $since (lists are newest first).
     *
     * @return iterable<array<string, mixed>>
     */
    protected function pages(string $url, ?string $since = null): iterable
    {
        $guard = 0;

        while ($url && $guard++ < 200) {
            $response = $this->http()->withToken($this->accessToken())->acceptJson()->get($url)->throw();

            foreach ((array) $response->json('data') as $item) {
                $date = substr((string) ($item['attributes']['executionDate'] ?? $item['attributes']['valueDate'] ?? ''), 0, 10);

                if ($since !== null && $date !== '' && $date < $since) {
                    return;
                }

                yield $item;
            }

            $url = $response->json('links.next');
        }
    }

    protected function accessToken(): string
    {
        $state = self::state();

        if (filled($state['access_token'] ?? null) && now()->timestamp < (int) ($state['expires_at'] ?? 0) - 60) {
            return (string) $state['access_token'];
        }

        if (blank($state['refresh_token'] ?? null)) {
            throw new RuntimeException('Ponto is not connected.');
        }

        // Every refresh gives a new refresh token; the old one stops working.
        $tokens = $this->tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $state['refresh_token']]);
        $this->storeTokens($tokens);

        return (string) $tokens['access_token'];
    }

    /**
     * @param  array<string, string>  $form
     * @return array<string, mixed>
     */
    protected function tokenRequest(array $form): array
    {
        $response = $this->http()->asForm()->acceptJson()->withBasicAuth(...$this->credentials())->post(self::API.'/oauth2/token', $form);

        if ($response->failed() || blank($response->json('access_token'))) {
            throw new RuntimeException('Ponto token request failed: '.Str::limit($response->body(), 300));
        }

        return (array) $response->json();
    }

    /**
     * @param  array<string, mixed>  $tokens
     * @param  array<string, mixed>  $extra
     */
    protected function storeTokens(array $tokens, array $extra = []): void
    {
        IntegrationState::write(self::STATE_KEY, [
            ...self::state(),
            ...$extra,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? self::state()['refresh_token'] ?? null,
            'expires_at' => now()->timestamp + (int) ($tokens['expires_in'] ?? 1800),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function credentials(): array
    {
        return [(string) settings()->get('finance.ponto_client_id'), Crypt::decryptString((string) settings()->get('finance.ponto_client_secret'))];
    }

    /**
     * Requests with the Ponto client certificate (mutual TLS).
     */
    protected function http(bool $throwWithoutCertificate = true): PendingRequest
    {
        $certificate = settings()->get('finance.ponto_certificate');
        $key = settings()->get('finance.ponto_private_key');

        if ($throwWithoutCertificate && (blank($certificate) || blank($key))) {
            throw new RuntimeException('The Ponto certificate is missing (Settings > Finance).');
        }

        $options = [];

        if (filled($certificate) && filled($key)) {
            $passphrase = filled(settings()->get('finance.ponto_key_passphrase')) ? Crypt::decryptString((string) settings()->get('finance.ponto_key_passphrase')) : null;
            $options = [
                'cert' => Storage::disk('local')->path($certificate),
                'ssl_key' => $passphrase ? [Storage::disk('local')->path($key), $passphrase] : Storage::disk('local')->path($key),
            ];
        }

        return Http::timeout(30)->withOptions($options);
    }
}
