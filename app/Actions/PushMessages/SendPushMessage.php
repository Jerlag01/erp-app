<?php

namespace App\Actions\PushMessages;

use App\Models\PushMessage;
use App\Models\User;
use App\Support\PushNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sends a message to the mobile app: to every active employee, to the members of teams, or to chosen
 * people. Each recipient gets only the text of their own language (that of their profile, else the
 * platform's default); the message also stays in the app under "Messages".
 */
class SendPushMessage
{
    public function __construct(protected PushNotifications $push) {}

    /**
     * @param  array{title: array<string, string>, body: array<string, string>, audience: string, team_ids?: array<int, int|string>, user_ids?: array<int, int|string>}  $data
     */
    public function handle(array $data, User $sender): PushMessage
    {
        $recipients = $this->recipients($data);

        $message = DB::transaction(function () use ($data, $sender, $recipients): PushMessage {
            $message = PushMessage::query()->create([
                'title' => array_map('trim', $data['title']),
                'body' => array_map('trim', $data['body']),
                'audience' => $data['audience'],
                'team_ids' => $data['audience'] === PushMessage::AUDIENCE_TEAMS ? array_values(array_map('intval', $data['team_ids'] ?? [])) : null,
                'recipients_count' => $recipients->count(),
                'sent_by' => $sender->id,
            ]);

            $message->recipients()->attach($recipients->mapWithKeys(fn (User $user): array => [$user->id => ['locale' => self::localeOf($user)]])->all());

            return $message;
        });

        $recipients->groupBy(fn (User $user): string => self::localeOf($user))
            ->each(fn (Collection $users, string $locale) => $this->push->toUsers(
                $users,
                $message->titleIn($locale),
                $message->bodyIn($locale),
                ['screen' => 'messages', 'id' => $message->id],
            ));

        return $message;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, User>
     */
    public function recipients(array $data): Collection
    {
        return User::query()->active()
            ->when($data['audience'] === PushMessage::AUDIENCE_TEAMS, fn (Builder $query) => $query->whereHas('teams', fn (Builder $query) => $query->whereKey((array) ($data['team_ids'] ?? []) ?: [0])))
            ->when($data['audience'] === PushMessage::AUDIENCE_USERS, fn (Builder $query) => $query->whereKey((array) ($data['user_ids'] ?? []) ?: [0]))
            ->get();
    }

    public static function localeOf(User $user): string
    {
        return $user->preferredLocale() ?? (string) config('app.locale');
    }
}
