<?php

namespace App\Support;

use App\Models\MobileKey;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Push notifications to the mobile app, through Expo's push service: one message per phone on which
 * the user is signed in (a usable key with a push token). Sending happens after the response, so a
 * slow or unreachable push service never holds up saving. Phones that uninstalled the app
 * ("DeviceNotRegistered") lose their token.
 */
class PushNotifications
{
    /** Expo's explanation of the last refused message (e.g. which app it has no Firebase key for). */
    public ?string $lastDetail = null;

    /**
     * @param  array<string, mixed>  $data  what the app needs to open the right screen ("screen", ids)
     */
    public function toUser(User $user, string $title, string $body, array $data = []): void
    {
        if (! config('services.expo_push.enabled')) {
            return;
        }

        $tokens = MobileKey::query()->usable()->where('user_id', $user->id)->whereNotNull('push_token')->pluck('push_token', 'id');

        if ($tokens->isEmpty()) {
            return;
        }

        $messages = $tokens->unique()->map(fn (string $token): array => [
            'to' => $token,
            'title' => mb_substr($title, 0, 150),
            'body' => mb_substr($body, 0, 1000),
            'data' => (object) $data,
            'sound' => 'default',
            'channelId' => 'default',
            'priority' => 'high',
        ])->values()->all();

        defer(fn () => $this->send($messages));
    }

    /**
     * The same message to many people at once (e.g. a message from the platform), in batches of
     * 100 as Expo takes them, after the response.
     *
     * @param  iterable<User>  $users
     * @param  array<string, mixed>  $data
     */
    public function toUsers(iterable $users, string $title, string $body, array $data = []): void
    {
        if (! config('services.expo_push.enabled')) {
            return;
        }

        $ids = collect($users)->map(fn (User $user): int => $user->id)->all();
        $tokens = MobileKey::query()->usable()->whereIn('user_id', $ids ?: [0])->whereNotNull('push_token')->pluck('push_token')->unique()->values();

        if ($tokens->isEmpty()) {
            return;
        }

        $messages = $tokens->map(fn (string $token): array => [
            'to' => $token,
            'title' => mb_substr($title, 0, 150),
            'body' => mb_substr($body, 0, 1000),
            'data' => (object) $data,
            'sound' => 'default',
            'channelId' => 'default',
            'priority' => 'high',
        ]);

        defer(fn () => $messages->chunk(100)->each(fn ($chunk) => $this->send($chunk->values()->all())));
    }

    /**
     * Sends a test message to one phone at once (not after the response), so the app can show whether
     * push works: null when Expo accepted it, else its error ("InvalidCredentials", "DeviceNotRegistered", …).
     * Expo's own explanation of the last refusal is kept in $lastDetail.
     */
    public function test(string $token, string $title, string $body): ?string
    {
        $this->lastDetail = null;

        if (! config('services.expo_push.enabled')) {
            return 'disabled';
        }

        $errors = $this->send([[
            'to' => $token, 'title' => $title, 'body' => $body, 'data' => (object) ['screen' => 'test'],
            'sound' => 'default', 'channelId' => 'default', 'priority' => 'high',
        ]]);

        return $errors[0] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array<int, string> the error per message that Expo refused, by its position
     */
    public function send(array $messages): array
    {
        $errors = [];

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->when(filled(config('services.expo_push.access_token')), fn ($request) => $request->withToken((string) config('services.expo_push.access_token')))
                ->post((string) config('services.expo_push.url'), $messages);

            foreach ((array) $response->json('data', []) as $index => $ticket) {
                if (($ticket['status'] ?? null) !== 'error') {
                    continue;
                }

                $errors[$index] = $error = (string) ($ticket['details']['error'] ?? 'Unknown');
                $this->lastDetail = mb_substr((string) ($ticket['message'] ?? ''), 0, 500) ?: null;

                if ($error === 'DeviceNotRegistered') {
                    MobileKey::query()->where('push_token', $messages[$index]['to'] ?? '')->update(['push_token' => null, 'push_token_updated_at' => now()]);
                } else {
                    // E.g. InvalidCredentials: the FCM key of the Android app is missing in EAS.
                    Log::warning('Push notification refused by Expo', ['error' => $error, 'message' => mb_substr((string) ($ticket['message'] ?? ''), 0, 300)]);
                }
            }

            if ($response->failed()) {
                Log::warning('Push notifications could not be sent', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);
                $errors = $errors ?: array_fill(0, count($messages), 'HTTP '.$response->status());
            }
        } catch (Throwable $exception) {
            Log::warning('Push notifications could not be sent', ['error' => $exception->getMessage()]);
            $errors = array_fill(0, count($messages), 'Unreachable');
        }

        return $errors;
    }
}
