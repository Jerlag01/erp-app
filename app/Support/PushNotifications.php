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
     * @param  list<array<string, mixed>>  $messages
     */
    public function send(array $messages): void
    {
        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->when(filled(config('services.expo_push.access_token')), fn ($request) => $request->withToken((string) config('services.expo_push.access_token')))
                ->post((string) config('services.expo_push.url'), $messages);

            foreach ((array) $response->json('data', []) as $index => $ticket) {
                if (($ticket['status'] ?? null) === 'error' && (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered')) {
                    MobileKey::query()->where('push_token', $messages[$index]['to'] ?? '')->update(['push_token' => null, 'push_token_updated_at' => now()]);
                }
            }

            if ($response->failed()) {
                Log::warning('Push notifications could not be sent', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);
            }
        } catch (Throwable $exception) {
            Log::warning('Push notifications could not be sent', ['error' => $exception->getMessage()]);
        }
    }
}
