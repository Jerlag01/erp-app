<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The messages sent to the employee from the platform, in the language they got them in; opening
 * them in the app marks them read.
 */
class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $messages = PushMessage::query()
            ->join('push_message_user', 'push_message_user.push_message_id', '=', 'push_messages.id')
            ->where('push_message_user.user_id', $user->id)
            ->with('sender')
            ->select('push_messages.*', 'push_message_user.locale as recipient_locale', 'push_message_user.read_at as recipient_read_at')
            ->latest('push_messages.id')
            ->limit(50)
            ->get();

        return response()->json(['data' => $messages->map(fn (PushMessage $message): array => [
            'id' => $message->id,
            'title' => $message->titleIn((string) $message->recipient_locale),
            'body' => $message->bodyIn((string) $message->recipient_locale),
            'sender' => $message->sender?->name,
            'sent_at' => $message->created_at->toIso8601String(),
            'read' => $message->recipient_read_at !== null,
        ])->values()]);
    }

    /**
     * Marks the employee's unread messages read (all, or the given one).
     */
    public function read(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['nullable', 'integer']]);

        $request->user()->pushMessages()
            ->wherePivotNull('read_at')
            ->when($data['id'] ?? null, fn ($query, int $id) => $query->whereKey($id))
            ->get()
            ->each(fn (PushMessage $message) => $message->pivot->update(['read_at' => now()]));

        return response()->json(['unread' => self::unread($request->user())]);
    }

    public static function unread(User $user): int
    {
        return $user->pushMessages()->wherePivotNull('read_at')->count();
    }
}
