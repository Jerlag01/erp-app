<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\MailTemplateKey;
use App\Enums\VatRate;
use App\Filament\Admin\Resources\MailTemplates\Pages\ManageMailTemplates;
use App\Filament\Pages\FinanceStatistics;
use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Filament\Resources\CreditNotes\Pages\EditCreditNote;
use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Mail\SystemMail;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\MailTemplate;
use App\Models\Project;
use App\Models\User;
use App\Services\Peppol\UblReader;
use App\Services\Peppol\UblWriter;
use App\Support\FinanceFigures;
use App\Support\Settings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A tiny valid PNG, as the signature pad sends it.
     */
    protected const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        app(Settings::class)->save('finance', [...Settings::defaults()['finance'], 'company_name' => 'Power Installation NV', 'vat_number' => 'BE0123456749', 'iban' => 'BE68539007547034', 'bic' => 'GKCCBEBB']);
    }

    protected function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('app');

        return $admin;
    }

    #[Test]
    public function employees_do_not_see_finance_without_permissions(): void
    {
        $employee = User::factory()->withPermissions(['projects' => ['view']])->inTeam()->create();

        foreach (['/finance', '/finance-statistics', '/invoices', '/credit-notes', '/expenses', '/clients'] as $url) {
            $this->actingAs($employee)->get($url)->assertForbidden();
        }

        $bookkeeper = User::factory()->withPermissions(['finance' => ['view'], 'invoices' => ['view']])->inTeam()->create();
        $this->actingAs($bookkeeper)->get('/finance')->assertOk();
        $this->actingAs($bookkeeper)->get('/invoices')->assertOk();
        $this->actingAs($bookkeeper)->get('/expenses')->assertForbidden();
    }

    #[Test]
    public function clients_need_a_valid_vat_number_unless_private_and_the_memo_is_for_admins(): void
    {
        $this->admin();

        Livewire::test(ManageClients::class)
            ->callAction('create', ['short_name' => 'acme', 'company_name' => 'Acme NV', 'vat_number' => 'BE0123456789', 'default_vat_rate' => '21', 'payment_term_days' => 30, 'locale' => 'nl'])
            ->assertHasActionErrors(['vat_number']);

        Livewire::test(ManageClients::class)
            ->callAction('create', ['short_name' => 'acme', 'company_name' => 'Acme NV', 'vat_number' => 'BE 0123.456.749', 'emails' => ['info@acme.be', 'sales@acme.be'], 'accounting_email' => 'boekhouding@acme.be', 'default_vat_rate' => '21', 'payment_term_days' => 45, 'locale' => 'nl', 'internal_memo' => 'Betaalt traag'])
            ->assertHasNoActionErrors();

        Livewire::test(ManageClients::class)
            ->callAction('create', ['short_name' => 'peeters', 'company_name' => 'Jan Peeters', 'is_private' => true, 'default_vat_rate' => '6', 'payment_term_days' => 14, 'locale' => 'nl'])
            ->assertHasNoActionErrors();

        $acme = Client::where('short_name', 'ACME')->sole();
        $this->assertSame('BE0123456749', $acme->vat_number);
        $this->assertSame(['info@acme.be', 'sales@acme.be'], $acme->emails);
        $this->assertSame('boekhouding@acme.be', $acme->invoiceEmail());
        $this->assertSame('0208:0123456749', $acme->peppolParticipant());

        $this->actingAs(User::factory()->withPermissions(['clients' => ['view', 'update']])->inTeam()->create());
        Livewire::test(ManageClients::class)->mountTableAction('edit', $acme)->assertFormFieldDoesNotExist('internal_memo');
    }

    #[Test]
    public function a_new_invoice_starts_with_the_standard_lines_and_bills_each_po_number_once(): void
    {
        Repeater::fake();
        $this->admin();
        $client = Client::factory()->create(['default_vat_rate' => VatRate::Six]);
        $project = Project::factory()->create();
        $billed = $project->poNumbers()->create(['number' => '4500000001']);
        $open = $project->poNumbers()->create(['number' => '4500000002']);
        Invoice::factory()->create()->poNumbers()->attach($billed);

        $page = Livewire::test(CreateInvoice::class)
            ->assertFormSet(fn (array $state): bool => collect($state['lines'])->pluck('description')->all() === ['Dossierkosten', 'BTV-controle (keuring)'])
            ->fillForm(['client_id' => $client->id, 'project_id' => $project->id]);

        $options = $page->instance()->form->getComponent('poNumbers')->getOptions();
        $this->assertArrayHasKey($open->id, $options);
        $this->assertArrayNotHasKey($billed->id, $options);

        $page->fillForm([
            'poNumbers' => [$open->id],
            'lines' => [
                ['description' => 'Dossierkosten', 'quantity' => 1, 'unit_price' => 50, 'vat_rate' => '6'],
                ['description' => 'Bekabeling', 'quantity' => 10, 'unit' => 'm', 'unit_price' => 12.5, 'vat_rate' => '21'],
                ['description' => 'Onderaanneming', 'quantity' => 1, 'unit_price' => 200, 'vat_rate' => 'reverse_charge'],
            ],
        ])->call('create')->assertHasNoFormErrors();

        $invoice = Invoice::query()->latest('id')->first();
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame(['4500000002'], $invoice->poNumberList());
        $this->assertSame('375.00', $invoice->subtotal);
        // 6% of 50 + 21% of 125, reverse charge 0.
        $this->assertSame('29.25', $invoice->vat_total);
        $this->assertSame('404.25', $invoice->total);
        $this->assertTrue($invoice->hasReverseCharge());
    }

    #[Test]
    public function finalising_numbers_without_gaps_and_locks_the_invoice(): void
    {
        $admin = $this->admin();
        $first = Invoice::factory()->withLine(1000)->create();
        $second = Invoice::factory()->withLine(500)->create();

        Livewire::test(ViewInvoice::class, ['record' => $first->getRouteKey()])->callAction('finalize');
        $second->finalize();

        $first->refresh();
        $this->assertSame('2026-0001', $first->number);
        $this->assertSame('2026-0002', $second->fresh()->number);
        $this->assertSame(InvoiceStatus::Final, $first->status);
        $this->assertSame($first->issue_date->addDays(30)->toDateString(), $first->due_date->toDateString());
        $this->assertFalse($admin->can('update', $first));
        $this->get("/invoices/{$first->id}/edit")->assertForbidden();

        // Structured reference: 10 digits + mod 97 check.
        $digits = preg_replace('/\D/', '', $first->structured_reference);
        $this->assertSame((int) substr($digits, 0, 10) % 97 ?: 97, (int) substr($digits, 10, 2));
    }

    #[Test]
    public function a_credit_note_copies_the_invoice_and_only_changes_with_the_override_right(): void
    {
        $this->admin();
        $invoice = Invoice::factory()->withLine(800)->finalized()->create();

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])->callAction('creditNote');

        $credit = Invoice::query()->where('type', InvoiceType::CreditNote)->sole();
        $this->assertTrue($credit->creditedInvoice->is($invoice));
        $this->assertSame('968.00', $credit->total);
        $this->assertSame(['Werken'], $credit->lines->pluck('description')->all());

        // Without the override right the copied content cannot change.
        $clerk = User::factory()->withPermissions(['credit_notes' => ['view', 'update']])->inTeam()->create();
        $this->actingAs($clerk);
        Livewire::test(EditCreditNote::class, ['record' => $credit->getRouteKey()])
            ->assertFormFieldHidden('allow_changes')
            ->assertFormFieldDisabled('client_id')
            ->assertFormFieldDisabled('lines');

        $supervisor = User::factory()->withPermissions(['credit_notes' => ['view', 'update', 'override']])->inTeam()->create();
        $this->actingAs($supervisor);
        Livewire::test(EditCreditNote::class, ['record' => $credit->getRouteKey()])
            ->assertFormFieldDisabled('subject')
            ->fillForm(['allow_changes' => true])
            ->assertFormFieldEnabled('subject')
            ->fillForm(['subject' => 'Gedeeltelijke creditering'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Gedeeltelijke creditering', $credit->fresh()->subject);

        $credit->fresh()->finalize();
        $this->assertSame('CN2026-0001', $credit->fresh()->number);
    }

    #[Test]
    public function sending_mails_the_pdf_and_ubl_with_the_customised_template(): void
    {
        Mail::fake();
        $this->admin();
        $invoice = Invoice::factory()->withLine(100)->finalized()->create();
        MailTemplate::create(['key' => MailTemplateKey::InvoiceSent, 'locale' => 'nl', 'subject' => 'Uw factuur {number}', 'body' => 'Beste {client_name}, betaal **{amount}**.', 'is_active' => true]);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])->callAction('send', ['email' => 'boekhouding@klant.be']);

        Mail::assertSent(SystemMail::class, function (SystemMail $mail) use ($invoice): bool {
            return $mail->hasTo('boekhouding@klant.be')
                && $mail->rendered['subject'] === "Uw factuur {$invoice->number}"
                && str_contains($mail->rendered['html'], '<strong>€ 121,00</strong>')
                && count($mail->files) === 2
                && str_starts_with($mail->files[0]['data'], '%PDF');
        });

        $this->assertSame(InvoiceStatus::Sent, $invoice->fresh()->status);
    }

    #[Test]
    public function overdue_invoices_get_automatic_reminders_when_switched_on(): void
    {
        Mail::fake();
        $invoice = Invoice::factory()->withLine(100)->finalized()->create(['issue_date' => today()->subDays(40)]);
        $invoice->forceFill(['status' => InvoiceStatus::Sent, 'due_date' => today()->subDays(10)])->save();

        $this->artisan('finance:send-reminders')->assertSuccessful();
        Mail::assertNothingSent();

        app(Settings::class)->save('finance', [...app(Settings::class)->get('finance'), 'reminders_enabled' => true]);
        $this->artisan('finance:send-reminders')->assertSuccessful();
        $this->artisan('finance:send-reminders')->assertSuccessful();

        Mail::assertSent(SystemMail::class, 1);
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->key === MailTemplateKey::PaymentReminder1);
        $this->assertSame(1, $invoice->fresh()->reminder_level);
        $this->assertCount(1, $invoice->reminders);
    }

    #[Test]
    public function an_incoming_invoice_is_only_accepted_with_a_note_and_a_signature(): void
    {
        Storage::fake('local');
        $expense = Expense::factory()->create();
        $clerk = User::factory()->withPermissions(['expenses' => ['view', 'create']])->inTeam()->create();
        $validator = User::factory()->withPermissions(['expenses' => ['view', 'validate']])->inTeam()->create();
        Filament::setCurrentPanel('app');

        $this->actingAs($clerk);
        Livewire::test(ManageExpenses::class)->assertActionHidden(TestAction::make('accept')->table($expense));

        $this->actingAs($validator);
        Livewire::test(ManageExpenses::class)
            ->callAction(TestAction::make('accept')->table($expense), ['note' => '', 'signature' => null])
            ->assertHasFormErrors(['note' => 'required', 'signature' => 'required']);

        Livewire::test(ManageExpenses::class)
            ->callAction(TestAction::make('accept')->table($expense), ['note' => 'Nagekeken met leveringsbon', 'signature' => self::SIGNATURE])
            ->assertHasNoFormErrors();

        $expense->refresh();
        $this->assertSame(ExpenseStatus::Accepted, $expense->status);
        $this->assertTrue($expense->validator->is($validator));
        $this->assertNotNull($expense->validated_at);
        Storage::disk('local')->assertExists($expense->signature_path);
        $this->get(route('finance.expense-document', ['expense' => $expense, 'kind' => 'signature']))->assertOk();
    }

    #[Test]
    public function invoices_are_written_as_peppol_ubl_and_received_documents_become_expenses(): void
    {
        Storage::fake('local');
        $client = Client::factory()->create(['company_name' => 'Klant NV']);
        $invoice = Invoice::factory()->for($client)->withLine(1000, VatRate::TwentyOne)->finalized()->create();

        $xml = app(UblWriter::class)->write($invoice, '%PDF-1.4 test');
        $this->assertStringContainsString(UblWriter::CUSTOMIZATION, $xml);
        $this->assertStringContainsString('<cbc:EndpointID schemeID="0208">0123456749</cbc:EndpointID>', $xml);

        $read = app(UblReader::class)->read($xml);
        $this->assertSame('Power Installation NV', $read['supplier_name']);
        $this->assertSame((string) $invoice->number, $read['supplier_invoice_number']);
        $this->assertEqualsWithDelta(1210.0, $read['total'], 0.001);
        $this->assertEqualsWithDelta(210.0, $read['vat_total'], 0.001);
        $this->assertSame('%PDF-1.4 test', $read['pdf']['data']);

        // The access point delivers a received document to the webhook.
        app(Settings::class)->save('finance', [...app(Settings::class)->get('finance'), 'peppol_enabled' => true, 'peppol_inbound_secret' => str_repeat('s', 40)]);
        $this->call('POST', '/peppol/inbound/wrong-secret', [], [], [], ['CONTENT_TYPE' => 'application/xml'], $xml)->assertNotFound();
        $this->call('POST', '/peppol/inbound/'.str_repeat('s', 40), [], [], [], ['CONTENT_TYPE' => 'application/xml'], $xml)->assertCreated();
        $this->call('POST', '/peppol/inbound/'.str_repeat('s', 40), [], [], [], ['CONTENT_TYPE' => 'application/xml'], $xml)->assertOk()->assertJson(['duplicate' => true]);

        $expense = Expense::sole();
        $this->assertSame('peppol', $expense->source);
        $this->assertSame(ExpenseStatus::Pending, $expense->status);
        $this->assertSame('1210.00', $expense->total);
        Storage::disk('local')->assertExists($expense->document);

        $this->call('POST', '/peppol/inbound/'.str_repeat('s', 40), [], [], [], ['CONTENT_TYPE' => 'application/xml'], '<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><Invoice/>')->assertStatus(422);
    }

    #[Test]
    public function statistics_count_credit_notes_and_expenses_and_compare_with_last_year(): void
    {
        Invoice::factory()->withLine(1000)->finalized()->create(['issue_date' => '2026-03-10']);
        $credited = Invoice::factory()->withLine(200)->create(['issue_date' => '2026-03-15', 'type' => InvoiceType::CreditNote]);
        $credited->finalize();
        Invoice::factory()->withLine(9999)->create(['issue_date' => '2026-03-20']); // draft: does not count
        Invoice::factory()->withLine(400)->finalized()->create(['issue_date' => '2025-03-10']);
        Expense::factory()->create(['invoice_date' => '2026-03-05', 'subtotal' => 300, 'vat_total' => 63]);
        Expense::factory()->create(['invoice_date' => '2026-03-06', 'subtotal' => 50, 'vat_total' => 0, 'is_credit_note' => true]);
        $rejected = Expense::factory()->create(['invoice_date' => '2026-03-07', 'subtotal' => 999]);
        $rejected->reject(User::factory()->create(), 'Dubbel');

        $totals = FinanceFigures::totals(FinanceFigures::monthly('2026-01-01', '2026-12-31', false));
        $this->assertEqualsWithDelta(1000, $totals['invoices'], 0.01);
        $this->assertEqualsWithDelta(200, $totals['credit_notes'], 0.01);
        $this->assertEqualsWithDelta(800, $totals['income'], 0.01);
        $this->assertEqualsWithDelta(250, $totals['expenses'], 0.01);
        $this->assertEqualsWithDelta(550, $totals['result'], 0.01);
        $this->assertEqualsWithDelta(1210 - 242, FinanceFigures::totals(FinanceFigures::monthly('2026-01-01', '2026-12-31', true))['income'], 0.01);

        $this->admin();
        Livewire::test(FinanceStatistics::class)
            ->set('from', '2026-01-01')->set('until', '2026-12-31')
            ->assertSee('€ 800,00')
            ->assertSee('100,0%')
            ->assertSee('data-finance-chart="previous"', escape: false);

        $this->get('/finance?year=2026&month=2026-03')->assertOk()->assertSee('2026-0001');
        $this->get('/admin/finance-statistics')->assertOk();
    }

    #[Test]
    public function every_system_mail_can_be_edited_per_language(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        Livewire::test(ManageMailTemplates::class)->assertOk();
        $this->assertSame(count(MailTemplateKey::cases()) * count(config('app.locales')), MailTemplate::count());

        $this->get('/admin/mail-templates')->assertOk()->assertSee(MailTemplateKey::PaymentReminder3->getLabel());
        $this->actingAs(User::factory()->withPermissions(['invoices' => ['view']])->inTeam()->create())->get('/admin/mail-templates')->assertForbidden();
    }

    #[Test]
    public function the_finance_settings_tab_only_shows_while_the_module_is_on(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee(__('erp.settings.fields.company_vat'));

        $this->setModules(['finance' => false]);
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertDontSee(__('erp.settings.fields.company_vat'));
        $this->actingAs($admin)->get('/invoices')->assertForbidden();
    }
}
