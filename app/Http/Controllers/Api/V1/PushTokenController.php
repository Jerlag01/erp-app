<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The phone registers (or, with null, forgets) its Expo push token for the key it signed in with.
 * A token belongs to one phone: another key holding it (an earlier sign-in) loses it.
 */
class PushTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var MobileKey $key */
        $key = $request->attributes->get('mobile_key');
        $data = $request->validate([
            'token' => ['present', 'nullable', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[[^\]]+\]$/'],
        ]);

        if (filled($data['token'])) {
            MobileKey::query()->where('push_token', $data['token'])->whereKeyNot($key->id)->update(['push_token' => null]);
        }

        $key->update(['push_token' => $data['token'], 'push_token_updated_at' => now()]);

        return response()->json(['data' => ['registered' => filled($data['token'])]]);
    }
}
