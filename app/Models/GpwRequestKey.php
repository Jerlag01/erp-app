<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The key of one GPW request mail (see RequestGpw::mail; one mail goes to all its recipients): the links
 * "Approve" and "Refuse" carry it, so the recipients decide without logging in; the decision is recorded
 * with the names the mail went to. Only the hash is stored; a key works
 * once, until expires_at, and not after it was revoked (a new mailing) or the request was decided.
 */
#[Fillable(['gpw_request_id', 'work_site_contact_id', 'name', 'email', 'locale', 'token_hash', 'expires_at', 'used_at', 'revoked_at'])]
class GpwRequestKey extends Model
{
    /**
     * @return BelongsTo<GpwRequest, $this>
     */
    public function gpwRequest(): BelongsTo
    {
        return $this->belongsTo(GpwRequest::class);
    }

    /**
     * A new key for a recipient; returns [the key as it goes in the link, the stored record].
     *
     * @return array{0: string, 1: self}
     */
    public static function issue(GpwRequest $request, string $email, string $name, string $locale, ?int $contactId): array
    {
        $token = Str::random(48);
        $expires = CarbonImmutable::parse($request->ends_on)->endOfDay();

        $key = self::query()->create([
            'gpw_request_id' => $request->id,
            'work_site_contact_id' => $contactId,
            'name' => mb_substr($name, 0, 150),
            'email' => mb_substr($email, 0, 150),
            'locale' => $locale,
            'token_hash' => self::hash($token),
            'expires_at' => $expires->isAfter(now()->addDay()) ? $expires : now()->addDay(),
        ]);

        return [$token, $key];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The key of a link, when it can still be used.
     */
    public static function usable(string $token): ?self
    {
        $key = strlen($token) === 48 ? self::query()->with('gpwRequest.project.workSite', 'gpwRequest.requester')->where('token_hash', self::hash($token))->first() : null;

        return $key !== null && $key->used_at === null && $key->revoked_at === null && $key->expires_at->isFuture() && $key->gpwRequest?->isPending() ? $key : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
