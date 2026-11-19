<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\VatCodes\Pages\ManageVatCodes;
use App\Mail\SystemMail;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\User;
use App\Models\VatCode;
use App\Services\Peppol\UblWriter;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoicingSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(Settings::class)->save('finance', [...Settings::defaults()['finance'], 'company_name' => 'Power Installation NV', 'vat_number' => 'BE0123456749', 'iban' => 'BE68539007547034']);
    }

    protected function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('app');

        return $admin;
    }

    #[Test]
    public function vat_codes_are_managed_and_a_reverse_charge_code_charges_no_vat_and_prints_its_mention(): void
    {
        $this->admin();

        Livewire::test(ManageVatCodes::class)
            ->callAction('create', [
                'code' => 'medec', 'name' => 'Medecontractant', 'name_fr' => 'Cocontractant', 'percentage' => 21, 'category' => 'S',
                'is_reverse_charge' => true, 'mention' => 'Btw verlegd – medecontractant.', 'mention_fr' => 'Autoliquidation – cocontractant.', 'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $code = VatCode::query()->where('code', 'medec')->sole();
        $this->assertSame(0.0, $code->percentage());
        $this->assertSame('AE', $code->ublCategory());

        $client = Client::factory()->create(['default_vat_rate' => 'medec', 'locale' => 'fr']);
        $invoice = Invoice::factory()->for($client)->withLine(1000, 'medec')->withLine(100, '21')->finalized()->create();

        $this->assertEqualsWithDelta(1100.0, (float) $invoice->subtotal, 0.001);
        $this->assertEqualsWithDelta(21.0, (float) $invoice->vat_total, 0.001);
        $this->assertTrue($invoice->hasReverseCharge());
        $this->assertSame(['Autoliquidation – cocontractant.'], $invoice->vatMentions('fr'));
        $this->assertSame(['Btw verlegd – medecontractant.'], $invoice->vatMentions('nl'));

        $xml = app(UblWriter::class)->write($invoice, '%PDF');
        $this->assertStringContainsString('<cbc:ID>AE</cbc:ID>', $xml);
        $this->assertStringContainsString('VATEX-EU-AE', $xml);

        // In use: switched off rather than removed.
        $this->assertTrue($code->isUsed());
        Livewire::test(ManageVatCodes::class)->assertTableActionHidden('delete', $code);
    }

    #[Test]
    public function the_old_codes_keep_working_on_existing_lines(): void
    {
        $invoice = Invoice::factory()->withLine(100, 'reverse_charge')->withLine(100, '6')->finalized()->create();

        $this->assertEqualsWithDelta(6.0, (float) $invoice->vat_total, 0.001);
        $this->assertSame(['Btw verlegd – art. 51 §2 W.BTW. De medecontractant is de btw verschuldigd.'], $invoice->vatMentions('nl'));
        $this->assertSame('Btw verlegd', $invoice->lines->first()->vat_rate->label('nl'));
    }

    #[Test]
    public function a_client_has_communication_points_that_receive_invoices_and_reminders(): void
    {
        Mail::fake();
        $this->admin();

        Livewire::test(ManageClients::class)
            ->callAction('create', [
                'short_name' => 'acme', 'company_name' => 'Acme NV', 'vat_number' => 'BE0123456749', 'default_vat_rate' => '21', 'payment_term_days' => 45, 'locale' => 'nl',
                'accounting_email' => 'info@acme.be',
                'contacts' => [
                    ['name' => 'Els Peeters', 'role' => 'Boekhouding', 'email' => 'Boekhouding@Acme.be', 'receives_invoices' => true, 'receives_reminders' => true],
                    ['name' => 'Tom Aerts', 'role' => 'Aankoop', 'email' => 'aankoop@acme.be', 'receives_invoices' => true, 'receives_reminders' => false],
                    ['name' => 'Facility', 'role' => 'Site Gent', 'email' => 'gent@acme.be', 'receives_invoices' => false, 'receives_reminders' => false],
                ],
            ])
            ->assertHasNoActionErrors();

        $client = Client::query()->where('short_name', 'ACME')->sole();
        $this->assertCount(3, $client->contacts);
        $this->assertSame(['boekhouding@acme.be', 'aankoop@acme.be'], $client->invoiceRecipients());
        $this->assertSame(['boekhouding@acme.be'], $client->reminderRecipients());

        // Due after the client's own payment term.
        $invoice = Invoice::factory()->for($client)->withLine(100)->create(['issue_date' => today()]);
        $invoice->finalize();
        $this->assertTrue($invoice->due_date->isSameDay(today()->addDays(45)));

        // Sent to all points that receive invoices.
        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->mountAction('send')
            ->assertSchemaStateSet(['email' => ['boekhouding@acme.be', 'aankoop@acme.be']], 'mountedActionSchema0')
            ->callMountedAction()
            ->assertHasNoActionErrors();
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('boekhouding@acme.be') && $mail->hasTo('aankoop@acme.be') && ! $mail->hasTo('gent@acme.be'));
        $this->assertSame('boekhouding@acme.be, aankoop@acme.be', $invoice->fresh()->sent_to);

        // An invoice addressed to one point goes (and its reminders go) only there.
        $site = $client->contacts->firstWhere('email', 'gent@acme.be');
        $addressed = Invoice::factory()->for($client)->withLine(100)->finalized()->create(['client_contact_id' => $site->id]);
        $this->assertSame(['gent@acme.be'], $addressed->recipients());
        $this->assertSame(['gent@acme.be'], $addressed->reminderRecipients());
        $this->assertStringContainsString('T.a.v. Facility – Site Gent', view('finance.invoice-pdf', ['invoice' => $addressed->load('contact', 'lines')])->render());
    }

    #[Test]
    public function a_new_invoice_can_be_addressed_to_a_communication_point_of_its_client(): void
    {
        $this->admin();
        $client = Client::factory()->create();
        $point = ClientContact::factory()->for($client)->create(['name' => 'Els', 'role' => 'Boekhouding']);
        ClientContact::factory()->create();

        Livewire::test(CreateInvoice::class)
            ->fillForm(['client_id' => $client->id])
            ->assertFormFieldVisible('client_contact_id')
            ->fillForm([
                'client_contact_id' => $point->id,
                'issue_date' => today()->toDateString(),
                'lines' => [['description' => 'Werken', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => '21']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Invoice::query()->latest('id')->first()->contact->is($point));
    }

    #[Test]
    public function the_client_page_lists_its_invoices_and_credit_notes_with_what_is_outstanding(): void
    {
        $this->admin();
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $open = Invoice::factory()->for($client)->withLine(1000)->finalized()->create();
        $open->forceFill(['status' => InvoiceStatus::Sent, 'due_date' => today()->subDays(5)])->save();
        $paid = Invoice::factory()->for($client)->withLine(200)->finalized()->create();
        $paid->forceFill(['status' => InvoiceStatus::Paid])->save();
        $credit = Invoice::factory()->for($client)->withLine(50)->finalized()->create(['type' => InvoiceType::CreditNote]);
        $foreign = Invoice::factory()->for($other)->withLine(10)->finalized()->create();

        $this->get('/clients/'.$client->getRouteKey())->assertOk()->assertSee('€ 1.210,00');

        Livewire::test(InvoicesRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewClient::class])
            ->assertCanSeeTableRecords([$open, $paid, $credit])
            ->assertCanNotSeeTableRecords([$foreign])
            ->filterTable('type', 'credit_note')
            ->assertCanSeeTableRecords([$credit])
            ->assertCanNotSeeTableRecords([$open, $paid]);

        // Who only sees invoices does not get the credit notes.
        $this->actingAs(User::factory()->withPermissions(['clients' => ['view'], 'invoices' => ['view']])->inTeam()->create());
        Livewire::test(InvoicesRelationManager::class, ['ownerRecord' => $client, 'pageClass' => ViewClient::class])
            ->assertCanSeeTableRecords([$open, $paid])
            ->assertCanNotSeeTableRecords([$credit]);
    }
}
