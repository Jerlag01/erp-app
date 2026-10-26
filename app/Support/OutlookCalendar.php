<?php

namespace App\Support;

use App\Enums\PlanningKind;
use App\Filament\Pages\Planning as PlanningPage;
use App\Models\Absence;
use App\Models\Holiday;
use App\Models\OutlookEvent;
use App\Models\PlanningItem;
use App\Models\Training;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
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
 * e-mail address in the platform. Absences (sickness, overtime as leave, family, unpaid and justified
 * leave), holidays and IR days, and trainings go along: one event per employee concerned, kept in
 * outlook_events. Failures are logged and never stop the planning itself.
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
     * Updates the events of a project's planning from today on, e.g. when the batteries of its dossier
     * or BAE order change (they are in the event title). After the response.
     */
    public function syncProject(?int $projectId): void
    {
        if (! $projectId || ! $this->enabled()) {
            return;
        }

        defer(function () use ($projectId): void {
            PlanningItem::query()
                ->where('project_id', $projectId)
                ->whereDate('date', '>=', today())
                ->with(['user', 'project.workSite', 'project.category', 'project.batteryDossier', 'project.baeOrder'])
                ->get()
                ->each(fn (PlanningItem $item) => $this->sync($item));
        });
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
     * Creates, changes or removes the events of an absence (its employee), a holiday or IR day
     * (every active employee) or a training (its participants), so the calendars follow the platform.
     */
    public function syncEntry(Absence|Holiday|Training $source): void
    {
        if (! $this->enabled()) {
            return;
        }

        $users = $this->recipients($source)->keyBy('id');
        $events = OutlookEvent::query()->whereMorphedTo('source', $source)->get();

        foreach ($events as $event) {
            // No longer concerned, or another mailbox: out of that calendar.
            if ($users->get($event->user_id)?->email !== $event->mailbox && $this->removeEvent($event)) {
                $events = $events->except($event->getKey());
            }
        }

        $events = $events->keyBy('user_id');

        foreach ($users as $user) {
            $payload = $this->entryPayload($source, $user);
            $event = $events->get($user->id);

            try {
                if ($event) {
                    $response = $this->graph()->patch($this->eventUrl($event->mailbox, $event->event_id), $payload);

                    if ($response->status() !== 404) {
                        $response->throw();

                        continue;
                    }
                }

                $created = $this->graph()->post(self::GRAPH.'/users/'.rawurlencode($user->email).'/events', $payload + [
                    'transactionId' => 'erp-'.$source->getMorphClass().'-'.$source->getKey().'-'.$user->id.'-'.$source->updated_at?->timestamp,
                ])->throw()->json();

                OutlookEvent::query()->updateOrCreate(
                    ['source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(), 'user_id' => $user->id],
                    ['mailbox' => $user->email, 'event_id' => (string) ($created['id'] ?? '')],
                );
            } catch (Throwable $exception) {
                Log::warning('Calendar entry could not be put in Outlook', ['source' => $source->getMorphClass().'#'.$source->getKey(), 'mailbox' => $user->email, 'error' => $exception->getMessage()]);
            }
        }
    }

    /**
     * The events of a removed absence, holiday or training (taken before it was deleted).
     *
     * @param  iterable<OutlookEvent>  $events
     */
    public function removeEntries(iterable $events): void
    {
        foreach ($events as $event) {
            $this->enabled() ? $this->removeEvent($event) : $event->delete();
        }
    }

    /**
     * Who has the entry in their calendar: only employees with an e-mail address.
     *
     * @return Collection<int, User>
     */
    public function recipients(Absence|Holiday|Training $source): Collection
    {
        $users = match (true) {
            $source instanceof Absence => new Collection(array_filter([$source->user()->first()])),
            $source instanceof Training => $source->participants()->get(),
            default => User::query()->active()->get(),
        };

        return $users->filter(fn (User $user): bool => filled($user->email))->values();
    }

    /**
     * Absences, holidays and IR days show as "out of office" for whole days; private reasons are
     * marked private. Trainings are busy with the place and the details, and a reminder the evening before.
     *
     * @return array<string, mixed>
     */
    public function entryPayload(Absence|Holiday|Training $source, User $user): array
    {
        $locale = $user->preferredLocale();
        [$start, $end] = $source instanceof Holiday ? [$source->date, $source->date] : [$source->start_date, $source->end_date];

        $payload = [
            'isAllDay' => true,
            'start' => ['dateTime' => $start->format('Y-m-d').'T00:00:00', 'timeZone' => self::TIME_ZONE],
            'end' => ['dateTime' => $end->copy()->addDay()->format('Y-m-d').'T00:00:00', 'timeZone' => self::TIME_ZONE],
            'showAs' => 'oof',
            'isReminderOn' => false,
            'body' => ['contentType' => 'HTML', 'content' => ''],
            'location' => ['displayName' => ''],
        ];

        if ($source instanceof Absence) {
            return [
                ...$payload,
                'subject' => __('erp.enums.absence_type.'.$source->type->value, [], $locale).($source->half_day ? ' ('.__('erp.planning.outlook.half_day', [], $locale).')' : ''),
                'sensitivity' => $source->type->isPrivate() ? 'private' : 'normal',
            ];
        }

        if ($source instanceof Holiday) {
            return [...$payload, 'subject' => $source->label()];
        }

        $details = array_filter([
            $source->company,
            implode(' · ', array_filter([$source->contact_phone, $source->contact_email])),
            $source->notes,
        ], 'filled');

        return [
            ...$payload,
            'subject' => __('erp.planning.outlook.training', ['name' => $source->name], $locale),
            'body' => ['contentType' => 'HTML', 'content' => implode('', array_map(fn (string $line): string => '<p>'.nl2br(e($line)).'</p>', $details))],
            'location' => ['displayName' => (string) $source->addressLine()],
            'showAs' => 'busy',
            'isReminderOn' => true,
            'reminderMinutesBeforeStart' => 360,
        ];
    }

    /**
     * Removes one event from its calendar and forgets it; false when Outlook could not be reached.
     */
    protected function removeEvent(OutlookEvent $event): bool
    {
        try {
            $this->deleteEvent($event->mailbox, $event->event_id);
            $event->delete();

            return true;
        } catch (Throwable $exception) {
            Log::warning('Outlook event could not be removed', ['mailbox' => $event->mailbox, 'error' => $exception->getMessage()]);

            return false;
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
     * The event as Outlook gets it: topic (with the batteries of a 604 project), a full day, the place,
     * and the details.
     *
     * @return array<string, mixed>
     */
    public function payload(PlanningItem $item): array
    {
        $item->loadMissing(['project.workSite', 'project.category', 'project.batteryDossier', 'project.baeOrder']);
        $batteries = $item->batteries();
        $date = $item->date->format('Y-m-d');
        $subtitle = $item->subtitle();
        $location = $item->kind === PlanningKind::Project
            ? ($item->project?->addressLine() ?? $item->project?->workSite?->addressLine())
            : null;

        $lines = array_filter([
            $subtitle ? '<p><strong>'.e($subtitle).'</strong></p>' : null,
            $batteries ? '<p><strong>'.e($batteries).'</strong></p>' : null,
            filled($item->description) ? '<p>'.nl2br(e($item->description)).'</p>' : null,
            $item->taskList() !== [] ? '<ul>'.implode('', array_map(fn (array $task): string => '<li>'.e($task['text']).'</li>', $item->taskList())).'</ul>' : null,
            filled($item->notes) ? '<p>'.nl2br(e($item->notes)).'</p>' : null,
            '<p><a href="'.e(PlanningPage::getUrl(['date' => $date], panel: 'app')).'">'.e(__('erp.planning.outlook.open', [], $item->user?->preferredLocale())).'</a></p>',
        ]);

        return [
            'subject' => implode(' · ', array_filter([$item->topic(), $subtitle, $batteries])),
            'body' => ['contentType' => 'HTML', 'content' => implode('', $lines)],
            // Always a full day in Outlook: the hours of the planning are not sent.
            'isAllDay' => true,
            'start' => ['dateTime' => $date.'T00:00:00', 'timeZone' => self::TIME_ZONE],
            'end' => ['dateTime' => $item->date->copy()->addDay()->format('Y-m-d').'T00:00:00', 'timeZone' => self::TIME_ZONE],
            'location' => ['displayName' => (string) $location],
            'showAs' => 'busy',
            'isReminderOn' => true,
            // The evening before, at 18:00.
            'reminderMinutesBeforeStart' => 360,
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
