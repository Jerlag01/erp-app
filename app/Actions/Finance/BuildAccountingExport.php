<?php

namespace App\Actions\Finance;

use App\Enums\ExpenseStatus;
use App\Enums\MailTemplateKey;
use App\Mail\SystemMail;
use App\Models\AccountingExport;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Peppol\PeppolGateway;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * The export for the accountant: a ZIP with, for a period, every sales invoice and credit note
 * (UBL with the PDF inside, and the PDF) and every accepted incoming invoice (its UBL and/or PDF),
 * plus the sales journal, the VAT breakdown and the purchase journal as CSV (semicolon, decimal
 * comma: opens directly in Excel and reads into Exact, Octopus, WinBooks, Yuki, Clearfacts…).
 * The documents are marked as exported, so the next export can take only the new ones.
 */
class BuildAccountingExport
{
    /** Larger ZIP files are not sent by mail (most mail servers refuse them). */
    public const MAX_MAIL_BYTES = 20 * 1024 * 1024;

    public function __construct(protected InvoicePdf $pdf, protected PeppolGateway $peppol) {}

    /**
     * @param  list<string>  $parts  sales and/or purchases
     */
    public function handle(CarbonInterface $from, CarbonInterface $until, array $parts, bool $onlyNew = false, ?User $user = null): AccountingExport
    {
        $sales = in_array(AccountingExport::SALES, $parts, true) ? $this->sales($from, $until, $onlyNew) : new Collection;
        $purchases = in_array(AccountingExport::PURCHASES, $parts, true) ? $this->purchases($from, $until, $onlyNew) : new Collection;

        $path = 'accounting-exports/'.$from->format('Ymd').'-'.$until->format('Ymd').'-'.Str::random(8).'.zip';
        Storage::disk(AccountingExport::DISK)->makeDirectory('accounting-exports');
        $zip = new ZipArchive;

        if ($zip->open(Storage::disk(AccountingExport::DISK)->path($path), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The ZIP file could not be made.');
        }

        if ($sales->isNotEmpty() || in_array(AccountingExport::SALES, $parts, true)) {
            $this->addSales($zip, $sales);
        }

        if ($purchases->isNotEmpty() || in_array(AccountingExport::PURCHASES, $parts, true)) {
            $this->addPurchases($zip, $purchases);
        }

        $zip->close();

        $now = now();
        Invoice::query()->whereKey($sales->modelKeys())->update(['accounting_exported_at' => $now]);
        Expense::query()->whereKey($purchases->modelKeys())->update(['accounting_exported_at' => $now]);

        return AccountingExport::create([
            'period_from' => $from->toDateString(),
            'period_to' => $until->toDateString(),
            'parts' => array_values($parts),
            'sales_count' => $sales->count(),
            'purchase_count' => $purchases->count(),
            'path' => $path,
            'size' => Storage::disk(AccountingExport::DISK)->size($path),
            'created_by' => $user?->id,
        ]);
    }

    /**
     * Mails the ZIP to the accountant.
     *
     * @param  list<string>  $emails
     */
    public function send(AccountingExport $export, array $emails, ?User $user = null): void
    {
        $emails = array_values(array_unique(array_filter(array_map(fn (string $email): string => strtolower(trim($email)), $emails), fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));

        if ($emails === []) {
            throw new RuntimeException(__('erp.finance.accounting.no_email'));
        }

        if ($export->size > self::MAX_MAIL_BYTES) {
            throw new RuntimeException(__('erp.finance.accounting.too_large'));
        }

        Mail::to($emails)->send(new SystemMail(MailTemplateKey::AccountingExport, (string) (settings()->get('finance.accountant_locale') ?: 'nl'), [
            'from' => $export->period_from->format('d/m/Y'),
            'until' => $export->period_to->format('d/m/Y'),
            'sales_count' => $export->sales_count,
            'purchase_count' => $export->purchase_count,
            'file_name' => $export->filename(),
            'sender' => $user?->name,
        ], [[
            'data' => (string) Storage::disk(AccountingExport::DISK)->get($export->path),
            'name' => $export->filename(),
            'mime' => 'application/zip',
        ]]));

        $export->forceFill(['emailed_to' => implode(', ', $emails), 'emailed_at' => now()])->save();
    }

    /**
     * @return list<string>
     */
    public static function accountantEmails(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) settings()->get('finance.accountant_email')))));
    }

    /**
     * @return Collection<int, Invoice>
     */
    protected function sales(CarbonInterface $from, CarbonInterface $until, bool $onlyNew): Collection
    {
        return Invoice::query()
            ->booked()
            ->whereBetween('issue_date', [$from->toDateString(), $until->toDateString()])
            ->when($onlyNew, fn ($query) => $query->whereNull('accounting_exported_at'))
            ->with(['client.country', 'lines', 'creditedInvoice'])
            ->orderBy('type')
            ->orderBy('number')
            ->get();
    }

    /**
     * Only accepted incoming invoices: pending ones are not checked yet, rejected ones are not booked.
     *
     * @return Collection<int, Expense>
     */
    protected function purchases(CarbonInterface $from, CarbonInterface $until, bool $onlyNew): Collection
    {
        return Expense::query()
            ->where('status', ExpenseStatus::Accepted)
            ->whereBetween('invoice_date', [$from->toDateString(), $until->toDateString()])
            ->when($onlyNew, fn ($query) => $query->whereNull('accounting_exported_at'))
            ->with(['project', 'validator'])
            ->orderBy('invoice_date')
            ->get();
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    protected function addSales(ZipArchive $zip, Collection $invoices): void
    {
        $journal = [['Type', 'Nummer', 'Datum', 'Vervaldatum', 'Klant', 'Klant BTW-nummer', 'Maatstaf van heffing', 'BTW', 'Totaal', 'Gestructureerde mededeling', 'Status', 'Betaald op', 'Creditnota voor', 'Bestand']];
        $vat = [['Nummer', 'Datum', 'BTW-code', 'Omschrijving', 'Percentage', 'Categorie', 'Medecontractant', 'Maatstaf van heffing', 'BTW']];
        $sign = fn (Invoice $invoice): int => $invoice->isCreditNote() ? -1 : 1;

        foreach ($invoices as $invoice) {
            $name = 'verkopen/'.Str::slug((string) $invoice->number);

            try {
                $pdf = $this->invoicePdf($invoice);
                $zip->addFromString($name.'.pdf', $pdf);
                $xml = $invoice->source_xml_path && Storage::disk('local')->exists($invoice->source_xml_path)
                    ? (string) Storage::disk('local')->get($invoice->source_xml_path)
                    : $this->peppol->ublFor($invoice, $pdf);
                $zip->addFromString($name.'.xml', $xml);
            } catch (Throwable $exception) {
                report($exception);
            }

            $journal[] = [
                __('erp.enums.invoice_type.'.$invoice->type->value, [], 'nl'),
                $invoice->number,
                $invoice->issue_date?->format('d/m/Y'),
                $invoice->due_date?->format('d/m/Y'),
                $invoice->client?->company_name,
                $invoice->client?->vat_number,
                self::amount($sign($invoice) * (float) $invoice->subtotal),
                self::amount($sign($invoice) * (float) $invoice->vat_total),
                self::amount($sign($invoice) * (float) $invoice->total),
                $invoice->structured_reference,
                __('erp.enums.invoice_status.'.$invoice->status->value, [], 'nl'),
                $invoice->paid_on?->format('d/m/Y'),
                $invoice->creditedInvoice?->number,
                basename($name).'.pdf',
            ];

            foreach ($invoice->vatBreakdown() as $code => $group) {
                $vat[] = [
                    $invoice->number,
                    $invoice->issue_date?->format('d/m/Y'),
                    $code,
                    $group['rate']->label('nl'),
                    self::amount($group['rate']->percentage()),
                    $group['rate']->ublCategory(),
                    $group['rate']->isReverseCharge() ? 'ja' : 'nee',
                    self::amount($sign($invoice) * $group['base']),
                    self::amount($sign($invoice) * $group['vat']),
                ];
            }
        }

        $zip->addFromString('verkoopdagboek.csv', self::csv($journal));
        $zip->addFromString('verkopen-btw.csv', self::csv($vat));
    }

    /**
     * @param  Collection<int, Expense>  $expenses
     */
    protected function addPurchases(ZipArchive $zip, Collection $expenses): void
    {
        $journal = [['Type', 'Leverancier', 'Leverancier BTW-nummer', 'Factuurnummer', 'Datum', 'Vervaldatum', 'Maatstaf van heffing', 'BTW', 'Totaal', 'Betalingsreferentie', 'Betaald op', 'Project', 'Omschrijving', 'Goedgekeurd door', 'Bestand']];
        $used = [];

        foreach ($expenses as $expense) {
            $base = 'aankopen/'.$expense->invoice_date->format('Ymd').'-'.Str::limit(Str::slug($expense->supplier_name), 40, '').'-'.(Str::slug((string) $expense->supplier_invoice_number) ?: $expense->id);

            if (isset($used[$base])) {
                $base .= '-'.$expense->id;
            }

            $used[$base] = true;
            $files = [];

            foreach (['document' => null, 'ubl_path' => 'xml'] as $column => $extension) {
                $path = $expense->{$column};

                if ($path && Storage::disk(Expense::DISK)->exists($path)) {
                    $file = $base.'.'.($extension ?? (strtolower(pathinfo($expense->document_name ?: $path, PATHINFO_EXTENSION)) ?: 'pdf'));
                    $zip->addFromString($file, (string) Storage::disk(Expense::DISK)->get($path));
                    $files[] = basename($file);
                }
            }

            $sign = $expense->is_credit_note ? -1 : 1;
            $journal[] = [
                $expense->is_credit_note ? 'Creditnota' : 'Factuur',
                $expense->supplier_name,
                $expense->supplier_vat,
                $expense->supplier_invoice_number,
                $expense->invoice_date->format('d/m/Y'),
                $expense->due_date?->format('d/m/Y'),
                self::amount($sign * (float) $expense->subtotal),
                self::amount($sign * (float) $expense->vat_total),
                self::amount($sign * (float) $expense->total),
                $expense->payment_reference,
                $expense->paid_on?->format('d/m/Y'),
                $expense->project?->reference,
                $expense->description,
                $expense->validator?->name,
                implode(' ', $files),
            ];
        }

        $zip->addFromString('aankoopdagboek.csv', self::csv($journal));
    }

    protected function invoicePdf(Invoice $invoice): string
    {
        return $this->pdf->render($invoice);
    }

    protected static function amount(float $amount): string
    {
        return number_format($amount, 2, ',', '');
    }

    /**
     * Semicolon-separated with a BOM, so Excel opens it with the right columns and accents.
     *
     * @param  list<list<scalar|null>>  $rows
     */
    protected static function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($value): string => (string) $value, $row), ';', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
