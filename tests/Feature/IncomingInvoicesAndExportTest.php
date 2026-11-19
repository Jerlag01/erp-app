<?php

namespace Tests\Feature;

use App\Actions\Finance\BuildAccountingExport;
use App\Actions\Finance\ImportInvoiceXml;
use App\Actions\Finance\InvoicePdf;
use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Filament\Resources\AccountingExports\Pages\ManageAccountingExports;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Mail\SystemMail;
use App\Models\AccountingExport;
use App\Models\Client;
use App\Models\Expense;
use App\Models\IntegrationState;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Doccle\DoccleClient;
use App\Services\Inbox\FetchInvoiceMailbox;
use App\Services\Inbox\ImapClient;
use App\Services\Inbox\IncomingInvoices;
use App\Services\Inbox\MimeMessage;
use App\Services\Peppol\UblWriter;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class IncomingInvoicesAndExportTest extends TestCase
{
    use RefreshDatabase;

    protected const OUR_VAT = 'BE0123456749';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        app(Settings::class)->save('finance', [...Settings::defaults()['finance'], 'company_name' => 'Power Installation NV', 'vat_number' => self::OUR_VAT, 'iban' => 'BE68539007547034', 'accountant_email' => 'boekhouder@example.com']);
    }

    /**
     * The UBL of one of our invoices, after which the invoice is removed (as if made elsewhere).
     *
     * @return array{xml: string, number: string, client: Client}
     */
    protected function earlierInvoiceXml(float $amount = 1000): array
    {
        $invoice = Invoice::factory()->withLine($amount)->finalized()->create();
        $xml = app(UblWriter::class)->write($invoice, '%PDF-1.4 original');
        $number = (string) $invoice->number;
        $client = $invoice->client;
        $invoice->delete();

        return ['xml' => $xml, 'number' => $number, 'client' => $client];
    }

    #[Test]
    public function earlier_sales_invoices_are_imported_from_xml_with_their_number_pdf_and_client(): void
    {
        ['xml' => $xml, 'number' => $number, 'client' => $client] = $this->earlierInvoiceXml();
        $newClientXml = str_replace($client->vat_number, 'BE0402326408', $xml);
        $newClientXml = str_replace($number, '2026-0050', $newClientXml);

        $result = app(ImportInvoiceXml::class)->import([
            ['name' => 'a.xml', 'data' => $xml],
            ['name' => 'b.xml', 'data' => $newClientXml],
            ['name' => 'a-again.xml', 'data' => $xml],
        ]);

        $this->assertSame(2, $result['sales']);
        $this->assertSame([$number], $result['duplicates']);

        $invoice = Invoice::query()->where('number', $number)->sole();
        $this->assertTrue($invoice->isImported());
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
        $this->assertTrue($invoice->client->is($client));
        $this->assertEqualsWithDelta(1210.0, (float) $invoice->total, 0.001);
        $this->assertCount(1, $invoice->lines);
        $this->assertSame('21', $invoice->lines->first()->vat_rate->code);
        $this->assertNotNull($invoice->structured_reference);
        $this->assertSame('%PDF-1.4 original', app(InvoicePdf::class)->render($invoice));

        // A new client is made from the buyer in the file, and our numbering continues after 2026-0050.
        $other = Invoice::query()->where('number', '2026-0050')->sole();
        $this->assertSame('BE0402326408', $other->client->vat_number);
        $this->assertSame(50, (int) DB::table('finance_counters')->where(['type' => InvoiceType::Invoice->value, 'year' => 2026])->value('last_number'));
    }

    #[Test]
    public function a_supplier_invoice_in_the_import_becomes_an_incoming_invoice_and_zip_files_are_opened(): void
    {
        ['xml' => $xml] = $this->earlierInvoiceXml(500);

        // Seen from another company, our invoice is a supplier's invoice.
        app(Settings::class)->save('finance', [...app(Settings::class)->get('finance'), 'vat_number' => 'BE0402326408']);
        $zip = $this->zip(['supplier.xml' => $xml, 'notes.txt' => 'x']);

        $result = app(ImportInvoiceXml::class)->import([['name' => 'batch.zip', 'data' => $zip]]);

        $this->assertSame(0, $result['sales']);
        $this->assertSame(1, $result['purchases']);
        $expense = Expense::query()->sole();
        $this->assertSame('import', $expense->source);
        $this->assertEqualsWithDelta(605.0, (float) $expense->total, 0.001);
        $this->assertNotNull($expense->payment_reference);
        $this->assertCount(1, $result['problems']);
    }

    #[Test]
    public function the_import_action_takes_several_files_at_once(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('app');
        ['xml' => $first] = $this->earlierInvoiceXml();
        ['xml' => $second] = $this->earlierInvoiceXml(200);

        Livewire::test(ListInvoices::class)
            ->callAction('importXml', [
                'files' => [UploadedFile::fake()->createWithContent('one.xml', $first), UploadedFile::fake()->createWithContent('two.xml', $second)],
                'direction' => ImportInvoiceXml::SALES,
                'mark_paid' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(2, Invoice::query()->where('source', 'import')->where('status', InvoiceStatus::Paid)->count());
    }

    #[Test]
    public function a_mail_with_an_e_invoice_and_its_pdf_becomes_one_incoming_invoice_and_a_lone_pdf_one_to_complete(): void
    {
        ['xml' => $xml] = $this->earlierInvoiceXml(300);
        app(Settings::class)->save('finance', [...app(Settings::class)->get('finance'), 'vat_number' => 'BE0402326408']);
        $xmlWithoutPdf = preg_replace('/<cac:AdditionalDocumentReference>.*?<\/cac:AdditionalDocumentReference>/s', '', $xml);

        $message = MimeMessage::parse($this->mail('Factuur', [
            ['name' => 'factuur.xml', 'mime' => 'application/xml', 'data' => $xmlWithoutPdf],
            ['name' => 'factuur.pdf', 'mime' => 'application/pdf', 'data' => '%PDF-1.4 the picture'],
        ]));

        $this->assertSame('Factuur', $message->subject());
        $this->assertSame(['name' => 'Leverancier BV', 'email' => 'facturen@leverancier.be'], $message->from());
        $this->assertCount(2, $message->attachments);

        $result = app(IncomingInvoices::class)->ingest($message->attachments, 'mail', 'mail:1');
        $expense = $result['created'][0];
        $this->assertSame('mail', $expense->source);
        $this->assertSame('%PDF-1.4 the picture', Storage::disk('local')->get($expense->document));

        $lonePdf = [['name' => 'scan.pdf', 'data' => '%PDF-1.4 a scan']];
        $first = app(IncomingInvoices::class)->ingest($lonePdf, 'mail', 'mail:2', ['sender_name' => 'Garage Peeters', 'date' => '2026-11-15']);
        $this->assertSame('Garage Peeters', $first['created'][0]->supplier_name);
        $this->assertSame(ExpenseStatus::Pending, $first['created'][0]->fresh()->status);
        $this->assertSame(1, app(IncomingInvoices::class)->ingest($lonePdf, 'doccle', 'doccle:9')['duplicates']);
    }

    #[Test]
    public function the_mailbox_folder_is_read_once_per_message(): void
    {
        app(Settings::class)->save('finance', [...app(Settings::class)->get('finance'), 'inbox_enabled' => true, 'inbox_host' => 'imap.example.com', 'inbox_username' => 'facturen', 'inbox_password' => Crypt::encryptString('pw'), 'inbox_folder' => 'Facturen']);
        $this->assertTrue(FetchInvoiceMailbox::isConfigured());

        $client = new class('imap.example.com') extends ImapClient
        {
            /** @var array<int, string> */
            public array $messages = [];

            /** @var list<int> */
            public array $seen = [];

            public function connect(string $username, string $password): void {}

            public function select(string $folder): int
            {
                return 7;
            }

            public function uidsAfter(int $uid): array
            {
                return array_values(array_filter(array_keys($this->messages), fn (int $id): bool => $id > $uid));
            }

            public function fetch(int $uid): ?string
            {
                return $this->messages[$uid] ?? null;
            }

            public function markSeen(int $uid): void
            {
                $this->seen[] = $uid;
            }

            public function logout(): void {}
        };
        $client->messages = [
            3 => $this->mail('Scan', [['name' => 'scan.pdf', 'mime' => 'application/pdf', 'data' => '%PDF-1.4 one']]),
            4 => $this->mail('Nog een', [['name' => 'two.pdf', 'mime' => 'application/pdf', 'data' => '%PDF-1.4 two']]),
        ];

        $mailbox = app(FetchInvoiceMailbox::class)->usingClient(fn (): ImapClient => $client);
        $this->assertSame(['messages' => 2, 'created' => 2, 'duplicates' => 0], $mailbox->run());
        $this->assertSame([3, 4], $client->seen);
        $this->assertSame(4, IntegrationState::read(FetchInvoiceMailbox::STATE_KEY)['last_uid']);

        $this->assertSame(['messages' => 0, 'created' => 0, 'duplicates' => 0], $mailbox->run());
        $this->assertSame(2, Expense::query()->where('source', 'mail')->count());
    }

    #[Test]
    public function new_doccle_documents_become_incoming_invoices(): void
    {
        ['xml' => $xml] = $this->earlierInvoiceXml(100);
        app(Settings::class)->save('finance', [...app(Settings::class)->get('finance'), 'vat_number' => 'BE0402326408', 'doccle_enabled' => true, 'doccle_api_url' => 'https://doccle.test/api', 'doccle_api_key' => Crypt::encryptString('key')]);

        Http::fake([
            'https://doccle.test/api/documents/d-1/content' => Http::response($xml),
            'https://doccle.test/api/documents/d-1/acknowledge' => Http::response([], 204),
            'https://doccle.test/api/documents*' => Http::response(['data' => [['id' => 'd-1', 'fileName' => 'invoice.xml', 'receivedAt' => '2026-11-19T10:00:00Z', 'sender' => ['name' => 'Power Installation NV']]]]),
        ]);

        $this->assertSame(['documents' => 1, 'created' => 1, 'duplicates' => 0], app(DoccleClient::class)->fetch());
        $this->assertSame('doccle:d-1', Expense::query()->sole()->inbox_reference);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer key'));
    }

    #[Test]
    public function the_accountant_export_holds_the_documents_and_journals_and_is_mailed(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('app');

        $invoice = Invoice::factory()->withLine(1000)->finalized()->create(['issue_date' => '2026-10-05']);
        $accepted = Expense::factory()->create(['invoice_date' => '2026-10-10', 'supplier_invoice_number' => 'F-1', 'status' => ExpenseStatus::Accepted]);
        $pending = Expense::factory()->create(['invoice_date' => '2026-10-11']);

        Livewire::test(ManageAccountingExports::class)
            ->callAction('export', [
                'preset' => 'previous_month', 'from' => '2026-10-01', 'until' => '2026-10-31',
                'parts' => [AccountingExport::SALES, AccountingExport::PURCHASES], 'only_new' => true,
                'send' => true, 'emails' => ['boekhouder@example.com'],
            ])
            ->assertHasNoActionErrors();

        $export = AccountingExport::query()->sole();
        $this->assertSame(1, $export->sales_count);
        $this->assertSame(1, $export->purchase_count);
        $this->assertSame('boekhouder@example.com', $export->emailed_to);
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('boekhouder@example.com') && count($mail->files) === 1);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($export->path)));
        $names = array_map(fn (int $i): string => $zip->getNameIndex($i), range(0, $zip->numFiles - 1));
        $slug = Str::slug($invoice->number);
        $this->assertContains("verkopen/{$slug}.pdf", $names);
        $this->assertContains("verkopen/{$slug}.xml", $names);
        $this->assertContains('verkoopdagboek.csv', $names);
        $this->assertContains('aankoopdagboek.csv', $names);
        $this->assertStringContainsString('1210,00', (string) $zip->getFromName('verkoopdagboek.csv'));
        $purchases = (string) $zip->getFromName('aankoopdagboek.csv');
        $this->assertStringContainsString('F-1', $purchases);
        $this->assertStringNotContainsString($pending->supplier_invoice_number, $purchases);
        $zip->close();

        $this->assertNotNull($invoice->fresh()->accounting_exported_at);
        $this->assertNotNull($accepted->fresh()->accounting_exported_at);

        // Only new: nothing left for the same period.
        $again = app(BuildAccountingExport::class)->handle(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'), [AccountingExport::SALES, AccountingExport::PURCHASES], true);
        $this->assertSame([0, 0], [$again->sales_count, $again->purchase_count]);

        $this->get(route('finance.accounting-export', ['export' => $export]))->assertOk()->assertDownload($export->filename());
    }

    #[Test]
    public function exports_are_closed_without_the_right(): void
    {
        $export = AccountingExport::query()->create(['period_from' => '2026-10-01', 'period_to' => '2026-10-31', 'parts' => ['sales'], 'path' => 'accounting-exports/x.zip']);
        $this->actingAs(User::factory()->withPermissions(['invoices' => ['view']])->inTeam()->create());

        $this->get(route('finance.accounting-export', ['export' => $export]))->assertForbidden();
        $this->get(ManageAccountingExports::getUrl(panel: 'app'))->assertForbidden();
    }

    /**
     * @param  array<string, string>  $files
     */
    protected function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($files as $name => $data) {
            $zip->addFromString($name, $data);
        }

        $zip->close();
        $data = (string) file_get_contents($path);
        unlink($path);

        return $data;
    }

    /**
     * A multipart mail from a supplier with the given attachments (base64).
     *
     * @param  list<array{name: string, mime: string, data: string}>  $attachments
     */
    protected function mail(string $subject, array $attachments): string
    {
        $boundary = 'b'.md5($subject);
        $raw = "From: \"Leverancier BV\" <Facturen@leverancier.be>\r\nSubject: {$subject}\r\nDate: Mon, 16 Nov 2026 09:00:00 +0100\r\nMessage-ID: <".md5($subject)."@leverancier.be>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";
        $raw .= "--{$boundary}\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nIn bijlage onze factuur.\r\n";

        foreach ($attachments as $attachment) {
            $raw .= "--{$boundary}\r\nContent-Type: {$attachment['mime']}; name=\"{$attachment['name']}\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$attachment['name']}\"\r\n\r\n".chunk_split(base64_encode($attachment['data']))."\r\n";
        }

        return $raw."--{$boundary}--\r\n";
    }
}
