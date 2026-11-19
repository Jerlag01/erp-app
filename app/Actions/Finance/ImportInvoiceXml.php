<?php

namespace App\Actions\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\VatCode;
use App\Services\Inbox\IncomingInvoices;
use App\Services\Peppol\PeppolGateway;
use App\Services\Peppol\UblReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Imports earlier invoices from their UBL files (several at once, ZIP files too), e.g. from the
 * previous invoicing software or Doccle. Our own invoices (we are the supplier) become final
 * invoices or credit notes under their original number, with the client made when it is new;
 * the others become incoming invoices. A PDF with the same file name as the XML is kept as the
 * original document. Nothing is imported twice.
 */
class ImportInvoiceXml
{
    public const AUTO = 'auto';

    public const SALES = 'sales';

    public const PURCHASES = 'purchases';

    public const DISK = 'local';

    public function __construct(protected UblReader $reader, protected PeppolGateway $peppol, protected IncomingInvoices $inbox) {}

    /**
     * @param  list<array{name: string, data: string}>  $files
     * @return array{sales: int, purchases: int, duplicates: list<string>, problems: list<string>}
     */
    public function import(array $files, string $direction = self::AUTO, bool $markPaid = false, ?int $userId = null): array
    {
        $files = $this->inbox->expand($files);
        $summary = ['sales' => 0, 'purchases' => 0, 'duplicates' => [], 'problems' => []];
        $ownVat = Client::normaliseVat((string) settings()->get('finance.vat_number'));
        $pdfs = [];

        foreach ($files as $file) {
            if (IncomingInvoices::isPdf($file['data'])) {
                $pdfs[strtolower(pathinfo($file['name'], PATHINFO_FILENAME))] = $file;
            }
        }

        foreach ($files as $file) {
            if (! IncomingInvoices::isXml($file)) {
                if (! IncomingInvoices::isPdf($file['data'])) {
                    $summary['problems'][] = $file['name'].': '.__('erp.finance.import.not_xml');
                }

                continue;
            }

            try {
                $data = $this->reader->read($file['data']);
            } catch (InvalidArgumentException $exception) {
                $summary['problems'][] = $file['name'].': '.$exception->getMessage();

                continue;
            }

            $pdf = $pdfs[strtolower(pathinfo($file['name'], PATHINFO_FILENAME))] ?? null;
            $isSale = match ($direction) {
                self::SALES => true,
                self::PURCHASES => false,
                default => $ownVat !== null && Client::normaliseVat($data['supplier_vat']) === $ownVat,
            };

            if ($direction === self::AUTO && $ownVat === null) {
                $summary['problems'][] = $file['name'].': '.__('erp.finance.import.no_company_vat');

                continue;
            }

            try {
                if ($isSale) {
                    $invoice = $this->importSale($file['data'], $data, $pdf, $markPaid, $userId);

                    if ($invoice === null) {
                        $summary['duplicates'][] = $data['supplier_invoice_number'];
                    } else {
                        $summary['sales']++;
                    }

                    continue;
                }

                $result = $this->peppol->receive($file['data'], $userId, 'import');

                if ($result['duplicate']) {
                    $summary['duplicates'][] = $data['supplier_invoice_number'];

                    continue;
                }

                if ($pdf !== null && blank($result['expense']->document)) {
                    $path = 'expenses/'.now()->format('Y/m').'/'.Str::random(24).'.pdf';
                    Storage::disk(self::DISK)->put($path, $pdf['data']);
                    $result['expense']->forceFill(['document' => $path, 'document_name' => $pdf['name'], 'document_hash' => hash('sha256', $pdf['data'])])->save();
                }

                $summary['purchases']++;
            } catch (InvalidArgumentException $exception) {
                $summary['problems'][] = $file['name'].': '.$exception->getMessage();
            }
        }

        return $summary;
    }

    /**
     * One of our own earlier invoices or credit notes; null when it is already there.
     *
     * @param  array<string, mixed>  $data
     * @param  array{name: string, data: string}|null  $pdf
     */
    public function importSale(string $xml, array $data, ?array $pdf = null, bool $markPaid = false, ?int $userId = null): ?Invoice
    {
        $number = trim((string) $data['supplier_invoice_number']);

        if ($number === '' || $data['invoice_date'] === '') {
            throw new InvalidArgumentException(__('erp.finance.import.no_number'));
        }

        if (mb_strlen($number) > 30) {
            throw new InvalidArgumentException(__('erp.finance.import.number_too_long', ['number' => $number]));
        }

        if (Invoice::query()->where('number', $number)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($xml, $data, $pdf, $markPaid, $userId, $number): Invoice {
            $type = $data['is_credit_note'] ? InvoiceType::CreditNote : InvoiceType::Invoice;
            $client = $this->client($data['customer']);
            $issueDate = substr((string) $data['invoice_date'], 0, 10);
            $year = (int) substr($issueDate, 0, 4);
            $folder = 'invoices/imported/'.$year;
            $xmlPath = $folder.'/'.Str::slug($number).'-'.Str::random(8).'.xml';
            Storage::disk(self::DISK)->put($xmlPath, $xml);

            $pdfData = $pdf['data'] ?? $data['pdf']['data'] ?? null;
            $pdfPath = null;

            if ($pdfData !== null) {
                $pdfPath = $folder.'/'.Str::slug($number).'-'.Str::random(8).'.pdf';
                Storage::disk(self::DISK)->put($pdfPath, $pdfData);
            }

            $sequence = preg_match('/(\d+)\D*$/', $number, $match) ? (int) $match[1] : null;
            $digits = preg_replace('/\D/', '', (string) $data['payment_id']);
            $structured = BankTransaction::isValidStructured($digits) ? BankTransaction::formatStructured($digits) : null;
            $dueDate = $data['due_date'] ? substr((string) $data['due_date'], 0, 10) : null;
            $paid = $markPaid && $type === InvoiceType::Invoice;

            $invoice = new Invoice([
                'type' => $type,
                'client_id' => $client->id,
                'issue_date' => $issueDate,
                'due_date' => $type === InvoiceType::Invoice ? ($dueDate ?? now()->parse($issueDate)->addDays($client->payment_term_days)->toDateString()) : $dueDate,
                'subject' => $data['note'] ? Str::limit((string) $data['note'], 250, '') : null,
                'client_reference' => ($reference = $data['buyer_reference'] ?? $data['order_reference']) ? Str::limit((string) $reference, 100, '') : null,
                'created_by' => $userId,
            ]);
            $invoice->forceFill([
                'source' => 'import',
                'status' => $paid ? InvoiceStatus::Paid : InvoiceStatus::Sent,
                'paid_on' => $paid ? min($dueDate ?? $issueDate, today()->toDateString()) : null,
                'number' => $number,
                'number_year' => $year,
                'number_sequence' => $sequence,
                'structured_reference' => $structured,
                'finalized_at' => $issueDate,
                'sent_at' => $issueDate,
                'credited_invoice_id' => $type === InvoiceType::CreditNote && filled($data['billing_reference'])
                    ? Invoice::query()->where('number', $data['billing_reference'])->value('id')
                    : null,
                'source_xml_path' => $xmlPath,
                'original_pdf_path' => $pdfPath,
            ]);
            $invoice->save();

            foreach ($data['lines'] as $index => $line) {
                $model = new InvoiceLine([
                    'invoice_id' => $invoice->id,
                    'description' => Str::limit((string) ($line['name'] ?: '—'), 250, ''),
                    'quantity' => $line['quantity'] ?: 1,
                    'unit' => self::unit($line['unit']),
                    'unit_price' => $line['price'] ?? ($line['quantity'] ? $line['line_total'] / $line['quantity'] : $line['line_total']),
                    'vat_rate' => $this->vatCode($line['vat_category'], $line['vat_percent'])->code,
                    'sort_order' => $index + 1,
                ]);
                // The amount of the original invoice counts, not a recalculation.
                $model->line_total = round((float) $line['line_total'], 2);
                $model->saveQuietly();
            }

            $invoice->forceFill([
                'subtotal' => round((float) $data['subtotal'], 2),
                'vat_total' => round((float) $data['vat_total'], 2),
                'total' => round((float) $data['total'], 2),
            ])->saveQuietly();

            // Our own numbering continues after an imported number of the same series.
            if ($sequence !== null && $number === Invoice::formatNumber($type, $year, $sequence)) {
                DB::table('finance_counters')->insertOrIgnore(['type' => $type->value, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('finance_counters')->where(['type' => $type->value, 'year' => $year])->where('last_number', '<', $sequence)->update(['last_number' => $sequence, 'updated_at' => now()]);
            }

            return $invoice;
        });
    }

    /**
     * The client by VAT number (else by name), made when it is new.
     *
     * @param  array<string, ?string>  $customer
     */
    protected function client(array $customer): Client
    {
        $vat = Client::normaliseVat($customer['vat'] ?? null);
        $name = trim((string) ($customer['name'] ?? '')) ?: __('erp.finance.import.unknown_client');

        $client = $vat !== null
            ? Client::query()->where('vat_number', $vat)->first()
            : Client::query()->where('company_name', $name)->first();

        if ($client !== null) {
            return $client;
        }

        return Client::create([
            'short_name' => $this->shortName($name),
            'company_name' => Str::limit($name, 250, ''),
            'is_private' => $vat === null,
            'vat_number' => $vat,
            'peppol_id' => $customer['endpoint'] ?? null,
            'accounting_email' => $customer['email'] ?? null,
            'street' => isset($customer['street']) ? Str::limit((string) $customer['street'], 150, '') : null,
            'house_number' => isset($customer['house_number']) ? Str::limit((string) $customer['house_number'], 20, '') : null,
            'postal_code' => isset($customer['postal_code']) ? Str::limit((string) $customer['postal_code'], 20, '') : null,
            'city' => isset($customer['city']) ? Str::limit((string) $customer['city'], 100, '') : null,
            'country_id' => filled($customer['country'] ?? null) ? Country::query()->where('code', strtoupper((string) $customer['country']))->value('id') : null,
            'payment_term_days' => (int) (settings()->get('finance.payment_term_days') ?: 30),
        ]);
    }

    protected function shortName(string $name): string
    {
        $base = Str::limit(strtoupper(Str::slug($name, '')) ?: 'KLANT', 24, '');
        $candidate = $base;
        $i = 1;

        while (Client::query()->where('short_name', $candidate)->exists()) {
            $candidate = $base.'-'.++$i;
        }

        return $candidate;
    }

    /**
     * The VAT code for a UBL tax category and rate; an unknown rate gets an inactive code of its own.
     */
    public function vatCode(?string $category, ?float $percent): VatCode
    {
        $category = strtoupper((string) $category);
        $percent = round((float) $percent, 2);
        $codes = VatCode::query()->orderByDesc('is_active')->orderBy('sort')->get();

        if (in_array($category, ['AE', 'K', 'G'], true) && ($code = $codes->first(fn (VatCode $code): bool => $code->category === $category))) {
            return $code;
        }

        if ($category === 'AE' && ($code = $codes->first(fn (VatCode $code): bool => $code->is_reverse_charge))) {
            return $code;
        }

        $sameRate = $codes->filter(fn (VatCode $code): bool => ! $code->is_reverse_charge && abs((float) $code->percentage - $percent) < 0.001);

        if ($code = $sameRate->first(fn (VatCode $code): bool => $code->category === $category) ?? $sameRate->first()) {
            return $code;
        }

        $value = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');

        return VatCode::query()->firstOrCreate(['code' => $value], [
            'name' => $value.'%', 'name_fr' => $value.'%', 'name_en' => $value.'%',
            'percentage' => $percent, 'category' => $category ?: 'S', 'is_active' => false, 'sort' => 90,
        ]);
    }

    protected static function unit(?string $code): ?string
    {
        return match (strtoupper((string) $code)) {
            '', 'C62', 'H87', 'EA', 'ZZ' => null,
            'HUR' => 'u',
            'DAY' => 'dag',
            'MTR' => 'm',
            'KGM' => 'kg',
            'LTR' => 'l',
            'MTK' => 'm²',
            default => Str::limit(strtolower((string) $code), 20, ''),
        };
    }
}
