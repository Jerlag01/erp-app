<?php

namespace App\Services\Peppol;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PeppolDocument;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Sends and receives documents over Peppol. The UBL is always produced and kept; actually
 * delivering it needs a Peppol access point (provider) set in Settings > Finance. Without one the
 * document is "ready": download the UBL and upload it in the provider's portal.
 *
 * The provider call is a generic REST POST of the UBL (Bearer API key). Most access points work
 * this way, but check the provider's API when connecting one (see SERVER-AND-API-TODO.txt).
 */
class PeppolGateway
{
    public const DISK = 'local';

    public const READY = 'ready';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const RECEIVED = 'received';

    public function __construct(protected UblWriter $writer, protected UblReader $reader) {}

    public static function isConnected(): bool
    {
        return (bool) settings()->get('finance.peppol_enabled')
            && filled(settings()->get('finance.peppol_provider_url'))
            && filled(settings()->get('finance.peppol_api_key'));
    }

    public function ublFor(Invoice $invoice, ?string $pdf = null): string
    {
        return $this->writer->write($invoice, $pdf);
    }

    /**
     * Produces the UBL of a final invoice and delivers it when an access point is connected.
     */
    public function send(Invoice $invoice, ?string $pdf = null): PeppolDocument
    {
        $xml = $this->writer->write($invoice, $pdf);
        $path = 'peppol/out/'.Str::slug((string) $invoice->number).'-'.now()->format('YmdHis').'.xml';
        Storage::disk(self::DISK)->put($path, $xml);

        $receiver = $invoice->client->peppolParticipant();
        $status = self::READY;
        $messageId = null;
        $error = null;

        if (self::isConnected() && $receiver !== null) {
            try {
                $response = Http::timeout(30)
                    ->withToken(Crypt::decryptString((string) settings()->get('finance.peppol_api_key')))
                    ->withHeaders(['X-Peppol-Receiver' => $receiver, 'X-Peppol-Sender' => (string) settings()->get('finance.peppol_id')])
                    ->withBody($xml, 'application/xml')
                    ->post((string) settings()->get('finance.peppol_provider_url'));

                $response->throw();
                $status = self::SENT;
                $messageId = (string) ($response->json('id') ?? $response->json('messageId') ?? $response->header('X-Message-Id')) ?: null;
            } catch (Throwable $exception) {
                report($exception);
                $status = self::FAILED;
                $error = Str::limit($exception->getMessage(), 500);
            }
        }

        $invoice->forceFill(['peppol_status' => $status, 'peppol_message_id' => $messageId])->saveQuietly();

        return PeppolDocument::create([
            'direction' => PeppolDocument::OUT,
            'document_type' => $invoice->type->value,
            'documentable_type' => $invoice->getMorphClass(),
            'documentable_id' => $invoice->id,
            'sender_id' => settings()->get('finance.peppol_id'),
            'receiver_id' => $receiver,
            'status' => $status,
            'message_id' => $messageId,
            'xml_path' => $path,
            'error' => $error,
        ]);
    }

    /**
     * Turns a received UBL document into an expense waiting for validation. A document that was
     * already received (same supplier VAT and invoice number) is not added twice.
     *
     * @return array{expense: Expense, duplicate: bool}
     */
    public function receive(string $xml, ?int $userId = null): array
    {
        $data = $this->reader->read($xml);

        if ($data['supplier_invoice_number'] === '' || $data['invoice_date'] === '') {
            throw new InvalidArgumentException('The document has no number or date.');
        }

        $vat = Client::normaliseVat($data['supplier_vat']);
        $existing = Expense::query()->where('supplier_vat', $vat)->where('supplier_invoice_number', $data['supplier_invoice_number'])->first();

        if ($existing !== null && $vat !== null) {
            return ['expense' => $existing, 'duplicate' => true];
        }

        return DB::transaction(function () use ($xml, $data, $vat, $userId): array {
            $folder = 'expenses/'.now()->format('Y/m');
            $ublPath = $folder.'/'.Str::random(24).'.xml';
            Storage::disk(Expense::DISK)->put($ublPath, $xml);

            $documentPath = null;

            if ($data['pdf'] !== null) {
                $documentPath = $folder.'/'.Str::random(24).'.pdf';
                Storage::disk(Expense::DISK)->put($documentPath, $data['pdf']['data']);
            }

            $expense = Expense::create([
                'supplier_name' => $data['supplier_name'],
                'supplier_vat' => $vat,
                'supplier_invoice_number' => $data['supplier_invoice_number'],
                'is_credit_note' => $data['is_credit_note'],
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'subtotal' => $data['subtotal'],
                'vat_total' => $data['vat_total'],
                'description' => $data['description'] ? Str::limit($data['description'], 250) : null,
                'document' => $documentPath,
                'document_name' => $data['pdf']['name'] ?? null,
                'source' => 'peppol',
                'ubl_path' => $ublPath,
                'created_by' => $userId,
            ]);

            PeppolDocument::create([
                'direction' => PeppolDocument::IN,
                'document_type' => $data['is_credit_note'] ? 'credit_note' : 'invoice',
                'documentable_type' => $expense->getMorphClass(),
                'documentable_id' => $expense->id,
                'sender_id' => $data['supplier_endpoint'],
                'receiver_id' => $data['receiver_endpoint'],
                'status' => self::RECEIVED,
                'xml_path' => $ublPath,
            ]);

            return ['expense' => $expense, 'duplicate' => false];
        });
    }
}
