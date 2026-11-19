<?php

namespace App\Actions\Banking;

use App\Enums\Bank;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Services\Banking\CodaParser;
use App\Services\Banking\PontoClient;
use Illuminate\Support\Str;
use Throwable;

/**
 * Brings bank movements in, from Ponto or from CODA files, without doubles, and checks the new
 * ones against the open invoices right away.
 */
class ImportBankTransactions
{
    /** How far back the first Ponto read goes. */
    public const FIRST_SYNC_DAYS = 90;

    public function __construct(protected CodaParser $coda, protected PontoClient $ponto, protected MatchBankTransactions $matcher) {}

    /**
     * @return array{accounts: int, added: int, matched: int}
     */
    public function fromCoda(string $content): array
    {
        $added = [];
        $accounts = [];

        foreach ($this->coda->parse($content) as $statement) {
            $account = BankAccount::query()->firstOrCreate(
                ['iban' => BankAccount::normaliseIban($statement['iban'])],
                ['name' => __('erp.banking.account_default_name'), 'provider' => BankAccount::CODA, 'bank' => Bank::guess($statement['iban']), 'currency' => $statement['currency']],
            );
            $accounts[$account->id] = true;

            array_push($added, ...$this->store($account, $statement['transactions'], BankAccount::CODA));

            if ($statement['balance'] !== null && $statement['balance_date'] !== null && ($account->balance_at === null || $account->balance_at->toDateString() <= $statement['balance_date'])) {
                $account->forceFill(['balance' => $statement['balance'], 'balance_at' => $statement['balance_date']])->save();
            }
        }

        return ['accounts' => count($accounts), 'added' => count($added), 'matched' => $this->matcher->run($added)];
    }

    /**
     * Reads the accounts shared in Ponto and their new movements.
     *
     * @return array{accounts: int, added: int, matched: int, errors: list<string>}
     */
    public function fromPonto(): array
    {
        $added = [];
        $errors = [];
        $count = 0;

        foreach ($this->ponto->accounts() as $remote) {
            if (blank($remote['iban'])) {
                continue;
            }

            $account = BankAccount::query()->firstOrNew(['iban' => BankAccount::normaliseIban($remote['iban'])]);
            $account->fill([
                'name' => $account->name ?: ($remote['name'] ?: __('erp.banking.account_default_name')),
                'provider' => BankAccount::PONTO,
                'external_id' => $remote['id'],
                'currency' => $remote['currency'],
                'bank' => $account->exists && $account->bank !== Bank::Other ? $account->bank : Bank::guess($remote['iban']),
            ]);

            if ($remote['balance'] !== null) {
                $account->fill(['balance' => $remote['balance'], 'balance_at' => now()]);
            }

            $account->save();

            if (! ($account->is_active ?? true)) {
                continue;
            }

            $count++;

            try {
                $since = ($account->last_synced_at?->copy()->subDays(10) ?? now()->subDays(self::FIRST_SYNC_DAYS))->toDateString();
                array_push($added, ...$this->store($account, $this->ponto->transactions($remote['id'], $since), BankAccount::PONTO));
                $account->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();
            } catch (Throwable $exception) {
                report($exception);
                $account->forceFill(['last_error' => Str::limit($exception->getMessage(), 490)])->save();
                $errors[] = $account->formattedIban().': '.$exception->getMessage();
            }
        }

        return ['accounts' => $count, 'added' => count($added), 'matched' => $this->matcher->run($added), 'errors' => $errors];
    }

    /**
     * Adds the movements the account does not have yet.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<BankTransaction>
     */
    protected function store(BankAccount $account, array $rows, string $source): array
    {
        $known = $account->transactions()->whereIn('external_id', array_column($rows, 'external_id'))->pluck('external_id')->flip();
        $added = [];

        foreach ($rows as $row) {
            if (isset($known[$row['external_id']])) {
                continue;
            }

            $known[$row['external_id']] = true;
            $added[] = $account->transactions()->create([
                'external_id' => $row['external_id'],
                'source' => $source,
                'booking_date' => $row['booking_date'],
                'value_date' => $row['value_date'] ?? null,
                'amount' => $row['amount'],
                'currency' => $row['currency'] ?? $account->currency,
                'counterparty_name' => isset($row['counterparty_name']) ? Str::limit((string) $row['counterparty_name'], 250, '') : null,
                'counterparty_iban' => $row['counterparty_iban'] ?? null,
                'remittance' => $row['remittance'] ?? null,
                'structured_reference' => $row['structured_reference'] ?? null,
            ]);
        }

        return $added;
    }
}
