<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProfileResource;
use App\Models\MobileKey;
use Illuminate\Http\Request;

/**
 * The signed-in user's own details for the mobile app: the login, the employee register record
 * (when there is one) and until when the app's key is valid.
 */
class ProfileController extends Controller
{
    public function __invoke(Request $request): ProfileResource
    {
        /** @var MobileKey $key */
        $key = $request->attributes->get('mobile_key');

        return ProfileResource::make($request->user())->additional([
            'meta' => ['key_expires_at' => $key->expires_at->toIso8601String()],
        ]);
    }
}
