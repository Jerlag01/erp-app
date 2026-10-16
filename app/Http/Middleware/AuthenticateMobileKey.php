<?php

namespace App\Http\Middleware;

use App\Enums\MobileKeyRevokeReason;
use App\Models\MobileKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs in the requests of the mobile app with "Authorization: Bearer <key>.<secret>". The key must
 * not be revoked or expired, and the login must still be active: when it was switched off, its keys
 * are revoked here as well. Failures answer 401 with a "code" the app uses to show the sign-in again.
 */
class AuthenticateMobileKey
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = MobileKey::findByToken($request->bearerToken());

        if ($key === null) {
            return self::unauthorized('key_invalid');
        }

        if ($key->isRevoked()) {
            return self::unauthorized($key->revoked_reason === MobileKeyRevokeReason::UserInactive ? 'account_inactive' : 'key_revoked');
        }

        if ($key->isExpired()) {
            return self::unauthorized('key_expired');
        }

        $user = $key->user;

        if (! $user->is_active || $user->role_id === null || $user->employee?->isEmployed() === false) {
            MobileKey::revokeAllFor($user, MobileKeyRevokeReason::UserInactive);

            return self::unauthorized('account_inactive');
        }

        $key->markUsed($request->ip());
        Auth::setUser($user);
        $request->attributes->set('mobile_key', $key);

        if ($locale = $user->preferredLocale()) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    public static function unauthorized(string $code): JsonResponse
    {
        return response()->json(['message' => __('erp.mobile.errors.'.$code), 'code' => $code], 401);
    }
}
