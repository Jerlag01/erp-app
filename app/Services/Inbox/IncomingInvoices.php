<?php

namespace App\Services\Inbox;

use App\Models\Client;
use App\Models\Expense;
use App\Services\Peppol\PeppolGateway;
use App\Services\Peppol\UblReader;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ZipArchive;

/**
 * One way in for supplier invoices that arrive as files (Doccle, the mailbox folder, an upload):
 * a UBL e-invoice becomes an expense with all its data; a PDF without UBL becomes an expense to
 * complete. ZIP files are opened. Nothing is added twice (same supplier and number, or the same PDF).
 */
class IncomingInvoices
{
    public const MAX_FILE_BYTES = 30 * 1024 * 1024;

    public function __construct(protected PeppolGateway $peppol, protected UblReader $reader) {}

    /**
     * @param  list<array{name: string, data: string}>  $files
     * @param  array{sender_name?: ?string, sender_email?: ?string, date?: ?string, subject?: ?string}  $meta
     * @return array{created: list<Expense>, duplicates: int, ignored: list<string>}
     */
    public function ingest(array $files, string $source, ?string $reference = null, array $meta = [], ?int $userId = null): array
    {
        $files = $this->expand($files);
        $created = [];
        $duplicates = 0;
        $ignored = [];
        $pdfs = array_values(array_filter($files, fn (array $file): bool => self::isPdf($file['data'])));
        $hadUbl = false;
        $ownVat = Client::normaliseVat((string) settings()->get('finance.vat_number'));

        foreach ($files as $file) {
            if (! self::isXml($file)) {
                continue;
            }

            try {
                $data = $this->reader->read($file['data']);
            } catch (InvalidArgumentException) {
                $ignored[] = $file['name'];

                continue;
            }

            // Our own invoice (a copy sent to ourselves) is not an expense.
            if ($ownVat !== null && Client::normaliseVat($data['supplier_vat']) === $ownVat) {
                $ignored[] = $file['name'];

                continue;
            }

            try {
                $result = $this->peppol->receive($file['data'], $userId, $source, $reference);
            } catch (InvalidArgumentException) {
                $ignored[] = $file['name'];

                continue;
            }

            $hadUbl = true;

            if ($result['duplicate']) {
                $duplicates++;

                continue;
            }

            // The UBL without its picture: the PDF next to it in the mail.
            if (blank($result['expense']->document) && ($pdf = array_shift($pdfs)) !== null) {
                $this->attachPdf($result['expense'], $pdf);
            }

            $created[] = $result['expense'];
        }

        // Only a PDF (no e-invoice): an expense to complete by whoever validates it.
        if (! $hadUbl) {
            foreach ($pdfs as $pdf) {
                $hash = hash('sha256', $pdf['data']);

                if (Expense::query()->where('document_hash', $hash)->exists()) {
                    $duplicates++;

                    continue;
                }

                $expense = new Expense([
                    'supplier_name' => Str::limit(trim((string) ($meta['sender_name'] ?? '')) ?: (string) ($meta['sender_email'] ?? '') ?: pathinfo($pdf['name'], PATHINFO_FILENAME), 250, ''),
                    'invoice_date' => $meta['date'] ?? today()->toDateString(),
                    'subtotal' => 0,
                    'vat_total' => 0,
                    'description' => Str::limit(__('erp.finance.inbox.to_complete').(filled($meta['subject'] ?? null) ? ' · '.$meta['subject'] : ''), 250, ''),
                    'source' => $source,
                    'inbox_reference' => $reference ? Str::limit($reference, 190, '') : null,
                    'created_by' => $userId,
                ]);
                $expense->save();
                $this->attachPdf($expense, $pdf);
                $created[] = $expense;
            }
        }

        foreach ($files as $file) {
            if (! self::isXml($file) && ! self::isPdf($file['data'])) {
                $ignored[] = $file['name'];
            }
        }

        return ['created' => $created, 'duplicates' => $duplicates, 'ignored' => array_values(array_unique($ignored))];
    }

    /**
     * @param  array{name: string, data: string}  $pdf
     */
    protected function attachPdf(Expense $expense, array $pdf): void
    {
        $path = 'expenses/'.now()->format('Y/m').'/'.Str::random(24).'.pdf';
        Storage::disk(Expense::DISK)->put($path, $pdf['data']);

        $expense->forceFill([
            'document' => $path,
            'document_name' => Str::limit(basename($pdf['name']) ?: 'invoice.pdf', 250, ''),
            'document_hash' => hash('sha256', $pdf['data']),
        ])->save();
    }

    /**
     * Opens ZIP files (one level) and drops what is too large.
     *
     * @param  list<array{name: string, data: string}>  $files
     * @return list<array{name: string, data: string}>
     */
    public function expand(array $files): array
    {
        $result = [];

        foreach ($files as $file) {
            if (strlen($file['data']) > self::MAX_FILE_BYTES) {
                continue;
            }

            if (! str_starts_with($file['data'], "PK\x03\x04")) {
                $result[] = $file;

                continue;
            }

            $temp = tempnam(sys_get_temp_dir(), 'inbox');
            file_put_contents($temp, $file['data']);
            $zip = new ZipArchive;

            if ($zip->open($temp) === true) {
                for ($i = 0; $i < min($zip->numFiles, 500); $i++) {
                    $stat = $zip->statIndex($i);
                    $name = (string) ($stat['name'] ?? '');

                    if ($name === '' || str_ends_with($name, '/') || str_starts_with(basename($name), '.') || ($stat['size'] ?? 0) > self::MAX_FILE_BYTES) {
                        continue;
                    }

                    $data = $zip->getFromIndex($i);

                    if (is_string($data)) {
                        $result[] = ['name' => basename($name), 'data' => $data];
                    }
                }

                $zip->close();
            }

            @unlink($temp);
        }

        return $result;
    }

    /**
     * @param  array{name: string, data: string}  $file
     */
    public static function isXml(array $file): bool
    {
        $start = ltrim(substr($file['data'], 0, 500), "\xEF\xBB\xBF \t\r\n");

        return str_ends_with(strtolower($file['name']), '.xml') || str_starts_with($start, '<?xml') || str_starts_with($start, '<Invoice') || str_starts_with($start, '<CreditNote');
    }

    public static function isPdf(string $data): bool
    {
        return str_starts_with($data, '%PDF');
    }
}
