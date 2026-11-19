<?php

namespace App\Services\Inbox;

use App\Models\IntegrationState;
use Closure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The backup way in: a folder of a mailbox (e.g. "Facturen", filled by a mail rule) is read every
 * few minutes and the attached e-invoices and PDFs become incoming invoices. Messages are read
 * once (the last UID is remembered) and can be moved to a "processed" folder afterwards.
 */
class FetchInvoiceMailbox
{
    public const STATE_KEY = 'invoice_mailbox';

    /** Messages per run, so one run never takes too long. */
    public const BATCH = 50;

    /** @var (Closure(): ImapClient)|null */
    protected ?Closure $clientFactory = null;

    public function __construct(protected IncomingInvoices $inbox) {}

    public static function isConfigured(): bool
    {
        return (bool) settings()->get('finance.inbox_enabled')
            && filled(settings()->get('finance.inbox_host'))
            && filled(settings()->get('finance.inbox_username'))
            && filled(settings()->get('finance.inbox_password'));
    }

    /**
     * @param  Closure(): ImapClient  $factory
     */
    public function usingClient(Closure $factory): static
    {
        $this->clientFactory = $factory;

        return $this;
    }

    /**
     * @return array{messages: int, created: int, duplicates: int}
     */
    public function run(): array
    {
        $folder = (string) (settings()->get('finance.inbox_folder') ?: 'INBOX');
        $processed = trim((string) settings()->get('finance.inbox_processed_folder'));
        $client = $this->clientFactory ? ($this->clientFactory)() : new ImapClient(
            (string) settings()->get('finance.inbox_host'),
            (int) (settings()->get('finance.inbox_port') ?: 993),
            (string) (settings()->get('finance.inbox_encryption') ?: 'ssl'),
        );

        $client->connect((string) settings()->get('finance.inbox_username'), Crypt::decryptString((string) settings()->get('finance.inbox_password')));
        $totals = ['messages' => 0, 'created' => 0, 'duplicates' => 0];

        try {
            $validity = $client->select($folder);
            $state = (array) IntegrationState::read(self::STATE_KEY, []);

            // Another folder, or the server rebuilt it: start over (doubles are recognised anyway).
            $lastUid = ($state['folder'] ?? null) === $folder && (int) ($state['uidvalidity'] ?? -1) === $validity ? (int) ($state['last_uid'] ?? 0) : 0;

            foreach (array_slice($client->uidsAfter($lastUid), 0, self::BATCH) as $uid) {
                try {
                    $raw = $client->fetch($uid);

                    if ($raw !== null) {
                        $message = MimeMessage::parse($raw);
                        $from = $message->from();
                        $result = $this->inbox->ingest(
                            array_map(fn (array $file): array => ['name' => $file['name'], 'data' => $file['data']], $message->attachments),
                            'mail',
                            'mail:'.($message->messageId() ?? $uid),
                            ['sender_name' => $from['name'], 'sender_email' => $from['email'], 'date' => $message->date(), 'subject' => $message->subject()],
                        );
                        $totals['created'] += count($result['created']);
                        $totals['duplicates'] += $result['duplicates'];
                    }

                    $totals['messages']++;
                    $lastUid = $uid;
                    IntegrationState::write(self::STATE_KEY, ['folder' => $folder, 'uidvalidity' => $validity, 'last_uid' => $lastUid, 'checked_at' => now()->toIso8601String()]);

                    if ($processed !== '') {
                        $client->move($uid, $processed);
                    } else {
                        $client->markSeen($uid);
                    }
                } catch (Throwable $exception) {
                    // One bad message must not block the ones after it.
                    report($exception);
                    Log::warning('Invoice mailbox message failed', ['uid' => $uid, 'error' => $exception->getMessage()]);
                    $lastUid = $uid;
                    IntegrationState::write(self::STATE_KEY, ['folder' => $folder, 'uidvalidity' => $validity, 'last_uid' => $lastUid, 'checked_at' => now()->toIso8601String()]);
                }
            }

            if ($totals['messages'] === 0) {
                IntegrationState::write(self::STATE_KEY, ['folder' => $folder, 'uidvalidity' => $validity, 'last_uid' => $lastUid, 'checked_at' => now()->toIso8601String()]);
            }
        } finally {
            $client->logout();
        }

        return $totals;
    }
}
