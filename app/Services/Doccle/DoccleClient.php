<?php

namespace App\Services\Doccle;

use App\Models\IntegrationState;
use App\Services\Inbox\IncomingInvoices;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Doccle: the company's Doccle (Peppol) mailbox, where suppliers' e-invoices and documents arrive.
 * New documents are fetched every quarter of an hour and become incoming invoices (UBL with all
 * data, else the PDF to complete).
 *
 * Doccle has no public API documentation. The calls below follow the usual document-inbox REST
 * shape (list new documents, download one, confirm it) with an API key from Doccle; check the
 * paths against what Doccle supplies when the access is granted (SERVER-AND-API-TODO.txt). Until
 * then the mailbox folder and the file import are the ways in.
 */
class DoccleClient
{
    public const STATE_KEY = 'doccle';

    public function __construct(protected IncomingInvoices $inbox) {}

    public static function isConfigured(): bool
    {
        return (bool) settings()->get('finance.doccle_enabled')
            && filled(settings()->get('finance.doccle_api_url'))
            && filled(settings()->get('finance.doccle_api_key'));
    }

    /**
     * @return array{documents: int, created: int, duplicates: int}
     */
    public function fetch(): array
    {
        $state = (array) IntegrationState::read(self::STATE_KEY, []);
        $totals = ['documents' => 0, 'created' => 0, 'duplicates' => 0];

        foreach ($this->documents($state['since'] ?? null) as $document) {
            $id = (string) ($document['id'] ?? '');

            if ($id === '') {
                continue;
            }

            try {
                $files = [['name' => (string) ($document['fileName'] ?? $document['filename'] ?? $document['name'] ?? $id), 'data' => $this->download($id)]];

                // A document can carry its PDF and its UBL as separate files.
                foreach ((array) ($document['attachments'] ?? []) as $attachment) {
                    if (filled($attachment['id'] ?? null)) {
                        $files[] = ['name' => (string) ($attachment['fileName'] ?? $attachment['name'] ?? $attachment['id']), 'data' => $this->download($id, (string) $attachment['id'])];
                    }
                }

                $result = $this->inbox->ingest($files, 'doccle', 'doccle:'.$id, [
                    'sender_name' => $document['sender']['name'] ?? $document['senderName'] ?? null,
                    'date' => isset($document['documentDate']) ? substr((string) $document['documentDate'], 0, 10) : null,
                    'subject' => $document['title'] ?? $document['subject'] ?? null,
                ]);

                $totals['documents']++;
                $totals['created'] += count($result['created']);
                $totals['duplicates'] += $result['duplicates'];
                $this->acknowledge($id);
            } catch (Throwable $exception) {
                report($exception);
                Log::warning('Doccle document failed', ['id' => $id, 'error' => $exception->getMessage()]);
            }

            if (filled($document['receivedAt'] ?? $document['createdAt'] ?? null)) {
                $state['since'] = max((string) ($state['since'] ?? ''), (string) ($document['receivedAt'] ?? $document['createdAt']));
            }
        }

        IntegrationState::write(self::STATE_KEY, [...$state, 'checked_at' => now()->toIso8601String()]);

        return $totals;
    }

    /**
     * The new documents in the mailbox.
     *
     * @return list<array<string, mixed>>
     */
    protected function documents(?string $since): array
    {
        $documents = [];
        $url = $this->url('documents');
        $query = array_filter(['status' => 'new', 'since' => $since, 'limit' => 100]);
        $guard = 0;

        while ($url !== null && $guard++ < 50) {
            $response = $this->http()->get($url, $query)->throw();
            $json = $response->json();
            $items = array_is_list((array) $json) ? (array) $json : (array) ($json['data'] ?? $json['documents'] ?? $json['items'] ?? []);
            array_push($documents, ...array_values(array_filter($items, 'is_array')));
            $url = $json['links']['next'] ?? $json['next'] ?? null;
            $query = [];
        }

        return $documents;
    }

    protected function download(string $id, ?string $attachmentId = null): string
    {
        $path = 'documents/'.rawurlencode($id).($attachmentId ? '/attachments/'.rawurlencode($attachmentId) : '').'/content';
        $body = $this->http()->accept('*/*')->get($this->url($path))->throw()->body();

        if ($body === '') {
            throw new RuntimeException("Doccle document {$id} is empty.");
        }

        return $body;
    }

    protected function acknowledge(string $id): void
    {
        try {
            $this->http()->post($this->url('documents/'.rawurlencode($id).'/acknowledge'));
        } catch (Throwable $exception) {
            // Not fatal: the document is recognised as already received next time.
            Log::info('Doccle acknowledge failed', ['id' => $id, 'error' => $exception->getMessage()]);
        }
    }

    protected function url(string $path): string
    {
        return rtrim((string) settings()->get('finance.doccle_api_url'), '/').'/'.$path;
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(60)
            ->acceptJson()
            ->withToken(Crypt::decryptString((string) settings()->get('finance.doccle_api_key')));
    }
}
