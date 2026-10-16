<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MobileKeyRevokeReason;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateMobileKey;
use App\Http\Resources\V1\ProfileResource;
use App\Models\MobileKey;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Sign-in and sign-out of the mobile app. Signing in checks e-mail and password once and issues a key
 * and secret for the device, valid for a year; the app keeps them in the phone's secure storage.
 */
class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], (string) $user->password)) {
            return response()->json(['message' => __('erp.mobile.errors.invalid_credentials'), 'code' => 'invalid_credentials'], 422);
        }

        // Same rule as the employee panel: an active login with a role, and not an employee who left.
        if (! $user->is_active || $user->role_id === null || $user->employee?->isEmployed() === false) {
            return AuthenticateMobileKey::unauthorized('account_inactive');
        }

        ['key' => $key, 'secret' => $secret] = MobileKey::issue($user, $data['device_name'] ?? null, $data['device_id'] ?? null);

        if ($locale = $user->preferredLocale()) {
            app()->setLocale($locale);
        }

        return response()->json([
            'key' => $key->key,
            'secret' => $secret,
            'expires_at' => $key->expires_at->toIso8601String(),
            'profile' => ProfileResource::make($user),
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var MobileKey $key */
        $key = $request->attributes->get('mobile_key');
        $key->revoke(MobileKeyRevokeReason::Logout);

        return response()->json(['message' => __('erp.mobile.logged_out')]);
    }
}
