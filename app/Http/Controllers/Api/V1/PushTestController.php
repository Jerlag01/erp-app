<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileKey;
use App\Support\PushNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Send a test notification" in the app: pushes a message to this phone straight away and says what
 * happened, so a missing registration or a refusal by the push service (e.g. no Firebase key in EAS)
 * shows up at once instead of notifications silently never arriving.
 */
class PushTestController extends Controller
{
    public function __invoke(Request $request, PushNotifications $push): JsonResponse
    {
        /** @var MobileKey $key */
        $key = $request->attributes->get('mobile_key');

        if (blank($key->push_token)) {
            return response()->json(['data' => ['registered' => false, 'sent' => false, 'error' => null, 'message' => __('erp.mobile.push_test.not_registered')]]);
        }

        $error = $push->test($key->push_token, __('erp.mobile.push_test.title'), __('erp.mobile.push_test.body'));
        $message = match (true) {
            $error === null => __('erp.mobile.push_test.sent'),
            $error === 'disabled' => __('erp.mobile.push_test.disabled'),
            $error === 'InvalidCredentials' => __('erp.mobile.push_test.credentials'),
            $error === 'DeviceNotRegistered' => __('erp.mobile.push_test.not_registered'),
            default => __('erp.mobile.push_test.failed', ['error' => $error]),
        };

        return response()->json(['data' => [
            'registered' => true,
            'sent' => $error === null,
            'error' => $error,
            // Expo's own explanation shows what is missing where (e.g. for which app or project).
            'detail' => $push->lastDetail,
            'message' => $error !== null && filled($push->lastDetail) ? $message."\n\nExpo: ".$push->lastDetail : $message,
        ]]);
    }
}
