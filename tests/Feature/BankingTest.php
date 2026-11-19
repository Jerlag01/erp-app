<?php

namespace Tests\Feature;

use App\Actions\Banking\ImportBankTransactions;
use App\Actions\Banking\MatchBankTransactions;
use App\Enums\Bank;
use App\Enums\BankTransactionStatus;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\BankAccounts\Pages\ManageBankAccounts;
use App\Filament\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\IntegrationState;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Banking\CodaParser;
use App\Services\Banking\PontoClient;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(Settings::class)->save('finance', [...Settings::defaults()['finance'], 'company_name' => 'Power Installation NV', 'vat_number' => 'BE0123456749']);
    }

    #[Test]
    public function a_coda_file_is_read_and_payments_with_the_structured_communication_mark_invoices_paid(): void
    {
        $invoice = Invoice::factory()->withLine(1000)->finalized()->create();
        $invoice->forceFill(['status' => InvoiceStatus::Sent])->save();
        $byNumber = Invoice::factory()->withLine(500)->finalized()->create();
        $expense = Expense::factory()->create(['subtotal' => 500, 'vat_total' => 105, 'payment_reference' => '+++090/9337/55493+++']);

        $digits = preg_replace('/\D/', '', $invoice->structured_reference);
        $coda = $this->coda('BE68539007547034', [
            ['amount' => 1210.00, 'structured' => $digits, 'name' => 'KLANT NV', 'iban' => 'BE71096123456769'],
            ['amount' => 605.00, 'free' => 'Betaling factuur '.$byNumber->number, 'name' => 'ANDERE KLANT'],
            ['amount' => -605.00, 'structured' => '090933755493', 'name' => 'CEBEO'],
            ['amount' => 99.99, 'free' => 'Iets anders'],
        ]);

        $result = app(ImportBankTransactions::class)->fromCoda($coda);

        $this->assertSame(['accounts' => 1, 'added' => 4, 'matched' => 3], $result);
        $account = BankAccount::query()->sole();
        $this->assertSame('BE68539007547034', $account->iban);
        $this->assertSame(Bank::Other, $account->bank);
        $this->assertEqualsWithDelta(5000.0, (float) $account->balance, 0.001);

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('2026-11-18', $invoice->fresh()->paid_on->toDateString());
        $this->assertSame(InvoiceStatus::Paid, $byNumber->fresh()->status);
        $this->assertSame('2026-11-18', $expense->fresh()->paid_on->toDateString());

        $first = BankTransaction::query()->where('amount', 1210)->sole();
        $this->assertSame($digits, $first->structured_reference);
        $this->assertSame('BE71096123456769', $first->counterparty_iban);
        $this->assertTrue($first->matchable->is($invoice));
        $this->assertSame(BankTransactionStatus::Unmatched, BankTransaction::query()->where('amount', 99.99)->sole()->status);

        // The same file again adds nothing.
        $this->assertSame(0, app(ImportBankTransactions::class)->fromCoda($coda)['added']);
    }

    #[Test]
    public function a_different_amount_is_not_matched_automatically_but_can_be_linked_and_unlinked_by_hand(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('app');

        $invoice = Invoice::factory()->withLine(1000)->finalized()->create();
        $invoice->forceFill(['status' => InvoiceStatus::Sent, 'sent_at' => now()])->save();
        $movement = BankTransaction::factory()->create(['amount' => 1200, 'remittance' => $invoice->structured_reference]);

        $this->assertSame(0, app(MatchBankTransactions::class)->run());

        Livewire::test(ListBankTransactions::class)
            ->assertCanSeeTableRecords([$movement])
            ->callTableAction('link', $movement, ['document' => 'invoice:'.$invoice->id])
            ->assertHasNoTableActionErrors();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame($admin->id, $movement->fresh()->matched_by);

        Livewire::test(ListBankTransactions::class)
            ->set('activeTab', 'matched')
            ->callTableAction('unlink', $movement);

        $this->assertSame(InvoiceStatus::Sent, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->paid_on);
        $this->assertSame(BankTransactionStatus::Unmatched, $movement->fresh()->status);
    }

    #[Test]
    public function only_who_may_match_links_movements_and_without_the_banking_right_the_page_is_closed(): void
    {
        $movement = BankTransaction::factory()->create();

        $viewer = User::factory()->withPermissions(['banking' => ['view']])->inTeam()->create();
        $this->actingAs($viewer);
        Filament::setCurrentPanel('app');

        Livewire::test(ListBankTransactions::class)
            ->assertCanSeeTableRecords([$movement])
            ->assertTableActionHidden('link', $movement)
            ->assertActionHidden('importCoda');

        $this->actingAs(User::factory()->withPermissions(['invoices' => ['view']])->inTeam()->create());
        $this->get(ListBankTransactions::getUrl(panel: 'app'))->assertForbidden();
    }

    #[Test]
    public function ponto_accounts_and_movements_are_read_with_a_refreshed_token(): void
    {
        app(Settings::class)->save('finance', [
            ...app(Settings::class)->get('finance'),
            'ponto_client_id' => 'client', 'ponto_client_secret' => Crypt::encryptString('secret'),
            'ponto_certificate' => 'banking/ponto/cert.pem', 'ponto_private_key' => 'banking/ponto/key.pem',
        ]);
        IntegrationState::write(PontoClient::STATE_KEY, ['refresh_token' => 'old-refresh', 'access_token' => 'expired', 'expires_at' => now()->subMinute()->timestamp]);

        $invoice = Invoice::factory()->withLine(100)->finalized()->create();

        Http::fake([
            'https://api.ibanity.com/ponto-connect/oauth2/token' => Http::response(['access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 1799]),
            'https://api.ibanity.com/ponto-connect/accounts/acc-1/transactions*' => Http::response(['data' => [
                ['id' => 'tx-1', 'attributes' => ['amount' => 121.0, 'currency' => 'EUR', 'counterpartName' => 'Klant', 'counterpartReference' => 'BE71096123456769', 'remittanceInformation' => preg_replace('/\D/', '', $invoice->structured_reference), 'remittanceInformationType' => 'structured', 'executionDate' => '2026-11-19T00:00:00.000Z', 'valueDate' => '2026-11-19T00:00:00.000Z']],
                ['id' => 'tx-2', 'attributes' => ['amount' => -50.0, 'currency' => 'EUR', 'counterpartName' => 'Shell', 'remittanceInformation' => 'Brandstof', 'remittanceInformationType' => 'unstructured', 'executionDate' => '2026-11-18T00:00:00.000Z']],
            ], 'links' => []]),
            'https://api.ibanity.com/ponto-connect/accounts*' => Http::response(['data' => [
                ['id' => 'acc-1', 'attributes' => ['reference' => 'BE68 3101 2345 6734', 'description' => 'Zichtrekening ING', 'currency' => 'EUR', 'currentBalance' => 15234.56]],
            ], 'links' => []]),
        ]);

        $result = app(ImportBankTransactions::class)->fromPonto();

        $this->assertSame(['accounts' => 1, 'added' => 2, 'matched' => 1, 'errors' => []], $result);
        $account = BankAccount::query()->sole();
        $this->assertSame('BE68310123456734', $account->iban);
        $this->assertSame(Bank::Ing, $account->bank);
        $this->assertSame(BankAccount::PONTO, $account->provider);
        $this->assertEqualsWithDelta(15234.56, (float) $account->balance, 0.001);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);

        // The refresh token rotates.
        $this->assertSame('new-refresh', PontoClient::state()['refresh_token']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/accounts') && $request->hasHeader('Authorization', 'Bearer new-access'));
    }

    #[Test]
    public function the_bank_pages_and_the_finance_settings_open_for_an_administrator(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('app');
        BankAccount::factory()->create(['name' => 'Zichtrekening KBC']);

        $this->get(ManageBankAccounts::getUrl(panel: 'app'))->assertOk()->assertSee('Zichtrekening KBC')->assertSee(__('erp.banking.ponto.not_configured'));
        $this->get(ListBankTransactions::getUrl(panel: 'app'))->assertOk();
        $this->get(route('filament.admin.pages.settings'))->assertOk()->assertSee('Ponto')->assertSee('Doccle');
    }

    #[Test]
    public function structured_communications_are_recognised_in_free_text_only_with_a_valid_check(): void
    {
        $this->assertSame('090933755493', BankTransaction::structuredReferenceFrom('Factuur +++090/9337/55493+++ dank'));
        $this->assertSame('090933755493', BankTransaction::structuredReferenceFrom('***090/9337/55493***'));
        $this->assertSame('090933755493', BankTransaction::structuredReferenceFrom('ref 090933755493'));
        $this->assertNull(BankTransaction::structuredReferenceFrom('+++090/9337/55494+++'));
        $this->assertSame('BE68539007547034', CodaParser::belgianIban('539007547034'));
    }

    /**
     * A CODA 2 file for one account with the given movements, booked on 18/11/2026.
     *
     * @param  list<array{amount: float, structured?: string, free?: string, name?: string, iban?: string}>  $movements
     */
    protected function coda(string $iban, array $movements): string
    {
        $line = function (array $fields): string {
            $line = str_repeat(' ', 128);

            foreach ($fields as $position => $value) {
                $line = substr_replace($line, $value, $position - 1, strlen($value));
            }

            return $line;
        };

        $lines = [
            $line([1 => '0', 2 => '0000', 6 => '181126', 128 => '2']),
            $line([1 => '1', 2 => '2', 3 => '001', 6 => $iban, 40 => 'EUR', 43 => '0', 44 => str_pad('4000000', 15, '0', STR_PAD_LEFT), 59 => '171126']),
        ];

        foreach ($movements as $index => $movement) {
            $sequence = str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $communication = isset($movement['structured']) ? '101'.$movement['structured'] : str_pad($movement['free'] ?? '', 53);

            $lines[] = $line([
                1 => '21', 3 => $sequence, 7 => '0000', 11 => 'REF'.$sequence, 32 => $movement['amount'] < 0 ? '1' : '0',
                33 => str_pad((string) (int) round(abs($movement['amount']) * 1000), 15, '0', STR_PAD_LEFT), 48 => '181126', 54 => '00150000',
                62 => isset($movement['structured']) ? '1' : '0', 63 => substr($communication, 0, 53), 116 => '181126', 122 => '001', 125 => '0', 126 => '1', 128 => '0',
            ]);
            $lines[] = $line([1 => '23', 3 => $sequence, 7 => '0000', 11 => str_pad($movement['iban'] ?? '', 37), 48 => str_pad($movement['name'] ?? '', 35), 126 => '0', 128 => '0']);
        }

        $lines[] = $line([1 => '8', 2 => '001', 5 => $iban, 42 => '0', 43 => str_pad('5000000', 15, '0', STR_PAD_LEFT), 58 => '181126']);
        $lines[] = $line([1 => '9']);

        return implode("\r\n", $lines)."\r\n";
    }
}
