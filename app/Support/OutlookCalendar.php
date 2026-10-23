<?php

namespace App\Support;

use App\Enums\PlanningKind;
use App\Filament\Pages\Planning as PlanningPage;
use App\Models\PlanningItem;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Puts planning items in the employee's Outlook calendar through Microsoft Graph (Microsoft 365):
 * an event per item, changed and removed together with the item. Uses an app registration with the
 * application permission Calendars.ReadWrite (Settings > Planning). The employee's mailbox is their
 * e-mail address in the platform. Failures are logged and never stop the planning itself.
 */
class OutlookCalendar
{
    public const GRAPH = 'https://graph.microsoft.com/v1.0';

    /** Windows name of the Belgian time zone, as Outlook expects it. */
    public const TIME_ZONE = 'Romance Standard Time';

    public function enabled(): bool
    {
        return modules()->planning()
            && (bool) settings()->get('planning.outlook_enabled')
            && filled(settings()->get('planning.outlook_tenant_id'))
            && filled(settings()->get('planning.outlook_client_id'))
            && filled(settings()->get('planning.outlook_client_secret'));
    }

    /**
     * Creates or updates the event of an item (in the current employee's calendar; when the item
     * moved to someone else, the old event is removed first).
     */
    public function sync(PlanningItem $item): void
    {
        $item->loadMissing('user', 'project.workSite');
        $mailbox = $item->user?->email;

        if (! $this->enabled() || blank($mailbox)) {
            return;
        }

        try {
            if (filled($item->outlook_event_id) && $item->outlook_mailbox !== $mailbox) {
                $this->deleteEvent($item->outlook_mailbox, $item->outlook_event_id);
                $item->outlook_event_id = null;
            }

            if (filled($item->outlook_event_id)) {
                $response = $this->graph()->patch($this->eventUrl($mailbox, $item->outlook_event_id), $this->payload($item));

                if ($response->status() !== 404) {
                    $response->throw();

                    return;
                }
            }

            $event = $this->graph()->post(self::GRAPH.'/users/'.rawurlencode($mailbox).'/events', $this->payload($item) + [
                // The same id for a retry, so Outlook does not make the event twice.
                'transactionId' => 'erp-planning-'.$item->id.'-'.$item->updated_at?->timestamp,
            ])->throw()->json();

            $item->forceFill(['outlook_event_id' => $event['id'] ?? null, 'outlook_mailbox' => $mailbox])->saveQuietly();
        } catch (Throwable $exception) {
            Log::warning('Planning item could not be put in Outlook', ['item' => $item->id, 'mailbox' => $mailbox, 'error' => $exception->getMessage()]);
        }
    }

    /**
     * Removes the event of a deleted item.
     */
    public function remove(?string $mailbox, ?string $eventId): void
    {
        if (! $this->enabled() || blank($mailbox) || blank($eventId)) {
            return;
        }

        try {
            $this->deleteEvent($mailbox, $eventId);
        } catch (Throwable $exception) {
            Log::warning('Outlook event could not be removed', ['mailbox' => $mailbox, 'error' => $exception->getMessage()]);
        }
    }

    /**
     * Checks the settings by fetching a token and reading the calendar of the given mailbox.
     * Returns null when it works, otherwise the reason.
     */
    public function test(string $mailbox): ?string
    {
        try {
            Cache::forget($this->tokenKey());
            $this->graph()->get(self::GRAPH.'/users/'.rawurlencode($mailbox).'/calendar', ['$select' => 'id,name'])->throw();
        } catch (RequestException $exception) {
            return $exception->response->json('error.message') ?? $exception->response->json('error_description') ?? $exception->getMessage();
        } catch (Throwable $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    /**
     * The event as Outlook gets it: topic, time in Belgian time, the place, and the details.
     *
     * @return array<string, mixed>
     */
    public function payload(PlanningItem $item): array
    {
        $item->loadMissing('project.workSite');
        $date = $item->date->format('Y-m-d');
        $subtitle = $item->subtitle();
        $location = $item->kind === PlanningKind::Project
            ? ($item->project?->addressLine() ?? $item->project?->workSite?->addressLine())
            : null;

        $lines = array_filter([
            $subtitle ? '<p><strong>'.e($subtitle).'</strong></p>' : null,
            filled($item->description) ? '<p>'.nl2br(e($item->description)).'</p>' : null,
            $item->taskList() !== [] ? '<ul>'.implode('', array_map(fn (array $task): string => '<li>'.e($task['text']).'</li>', $item->taskList())).'</ul>' : null,
            filled($item->notes) ? '<p>'.nl2br(e($item->notes)).'</p>' : null,
            '<p><a href="'.e(PlanningPage::getUrl(['date' => $date], panel: 'app')).'">'.e(__('erp.planning.outlook.open', [], $item->user?->preferredLocale())).'</a></p>',
        ]);

        return [
            'subject' => trim($item->topic().($subtitle ? ' · '.$subtitle : '')),
            'body' => ['contentType' => 'HTML', 'content' => implode('', $lines)],
            'start' => ['dateTime' => $date.'T'.$item->startsAt().':00', 'timeZone' => self::TIME_ZONE],
            'end' => ['dateTime' => $date.'T'.$item->endsAt().':00', 'timeZone' => self::TIME_ZONE],
            'location' => ['displayName' => (string) $location],
            'showAs' => 'busy',
            'isReminderOn' => true,
            'reminderMinutesBeforeStart' => 60,
        ];
    }

    protected function deleteEvent(string $mailbox, string $eventId): void
    {
        $response = $this->graph()->delete($this->eventUrl($mailbox, $eventId));

        // Already gone in Outlook: nothing left to do.
        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    protected function eventUrl(string $mailbox, string $eventId): string
    {
        return self::GRAPH.'/users/'.rawurlencode($mailbox).'/events/'.rawurlencode($eventId);
    }

    protected function graph(): PendingRequest
    {
        return Http::withToken($this->token())->acceptJson()->timeout(15)->retry(2, 500, throw: false);
    }

    /**
     * An app-only token (client credentials), kept until shortly before it expires.
     */
    protected function token(): string
    {
        $cached = Cache::get($this->tokenKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $secret = rescue(fn (): string => Crypt::decryptString((string) settings()->get('planning.outlook_client_secret')), '', false);
        $response = Http::asForm()->timeout(15)->post('https://login.microsoftonline.com/'.rawurlencode((string) settings()->get('planning.outlook_tenant_id')).'/oauth2/v2.0/token', [
            'grant_type' => 'client_credentials',
            'client_id' => settings()->get('planning.outlook_client_id'),
            'client_secret' => $secret,
            'scope' => 'https://graph.microsoft.com/.default',
        ])->throw()->json();

        $token = (string) ($response['access_token'] ?? throw new RuntimeException('Microsoft did not return a token.'));
        Cache::put($this->tokenKey(), $token, now()->addSeconds(max(60, (int) ($response['expires_in'] ?? 3600) - 120)));

        return $token;
    }

    protected function tokenKey(): string
    {
        return 'outlook-graph-token:'.md5((string) settings()->get('planning.outlook_tenant_id').'|'.settings()->get('planning.outlook_client_id'));
    }
}
