<?php

namespace App\Console\Commands;

use App\Enums\MailTemplateKey;
use App\Mail\SystemMail;
use App\Models\HeldPlanningNotification;
use App\Models\User;
use App\Support\LeavePushDelay;
use App\Support\PushNotifications;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Sends the planning notifications held while an employee was on leave, on the last day of the leave:
 * the pushes as one message when there are several, the e-mails one by one as they were made. A leave
 * that was made longer meanwhile moves them on.
 */
#[Signature('planning:send-held')]
#[Description('Send the planning notifications (push and e-mail) held back during leave')]
class SendHeldPlanningNotifications extends Command
{
    public function handle(LeavePushDelay $delay, PushNotifications $push): int
    {
        $sent = 0;
        $userIds = HeldPlanningNotification::query()->due()->distinct()->pluck('user_id');

        foreach (User::query()->whereKey($userIds)->get() as $user) {
            $held = HeldPlanningNotification::query()->where('user_id', $user->id)->orderBy('id')->get();

            if ($until = $delay->until($user)) {
                HeldPlanningNotification::query()->whereKey($held->modelKeys())->update(['send_at' => $until]);

                continue;
            }

            if ($user->is_active) {
                $this->push($push, $user, $held->where('channel', HeldPlanningNotification::PUSH)->values());
                $held->where('channel', HeldPlanningNotification::MAIL)->each(fn (HeldPlanningNotification $mail) => $this->mail($user, $mail));
                $sent++;
            }

            HeldPlanningNotification::query()->whereKey($held->modelKeys())->delete();
        }

        $this->info("Sent held planning notifications to {$sent} employee(s).");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, HeldPlanningNotification>  $pushes
     */
    protected function push(PushNotifications $push, User $user, Collection $pushes): void
    {
        if ($pushes->isEmpty()) {
            return;
        }

        if ($pushes->count() === 1) {
            $payload = $pushes->first()->payload;
            $push->toUser($user, $payload['title'], $payload['body'], (array) ($payload['data'] ?? []));

            return;
        }

        $locale = $user->preferredLocale() ?? config('app.locale');
        $lines = $pushes->take(3)->map(fn (HeldPlanningNotification $held): string => (string) strtok((string) $held->payload['body'], "\n"));

        $push->toUser(
            $user,
            __('erp.planning.notifications.held_title', [], $locale),
            __('erp.planning.notifications.held_body', ['count' => $pushes->count()], $locale)."\n".$lines->implode("\n"),
            ['screen' => 'planning', 'date' => $pushes->map(fn (HeldPlanningNotification $held): ?string => $held->payload['data']['date'] ?? null)->filter()->sort()->first()],
        );
    }

    protected function mail(User $user, HeldPlanningNotification $held): void
    {
        $payload = $held->payload;
        $values = $payload['values'];

        foreach ($payload['html'] ?? [] as $key) {
            if (isset($values[$key])) {
                $values[$key] = new HtmlString($values[$key]);
            }
        }

        try {
            Mail::to($user->email, $user->name)->send(new SystemMail(MailTemplateKey::from($payload['key']), $payload['locale'], $values));
        } catch (Throwable $exception) {
            Log::warning('Held planning mail could not be sent', ['user' => $user->id, 'error' => $exception->getMessage()]);
        }
    }
}
