<?php

namespace App\Models;

use App\Enums\MobileKeyRevokeReason;
use Database\Factories\MobileKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A key and secret of the mobile app for one user on one device. The app sends both with every
 * request ("Authorization: Bearer <key>.<secret>"); only a SHA-256 hash of the secret is stored, so
 * the secret is shown once, when the key is issued. A key is valid for a year from the sign-in.
 */
#[Fillable(['user_id', 'device_name', 'device_id', 'push_token', 'push_token_updated_at', 'expires_at', 'last_used_at', 'last_used_ip', 'revoked_at', 'revoked_reason'])]
#[Hidden(['secret_hash'])]
class MobileKey extends Model
{
    /** @use HasFactory<MobileKeyFactory> */
    use HasFactory;

    public const VALID_MONTHS = 12;

    public const KEY_PREFIX = 'mk_';

    /**
     * Seconds between two updates of last_used_at, so not every request writes to the database.
     */
    public const TOUCH_INTERVAL = 60;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Issues a new key for the user's device. Signing in again on the same device replaces its key.
     *
     * @return array{key: MobileKey, secret: string}
     */
    public static function issue(User $user, ?string $deviceName = null, ?string $deviceId = null): array
    {
        if (filled($deviceId)) {
            $user->mobileKeys()->usable()->where('device_id', $deviceId)->get()
                ->each(fn (self $key) => $key->revoke(MobileKeyRevokeReason::Replaced));
        }

        $secret = Str::random(64);

        $key = new self([
            'user_id' => $user->id,
            'device_name' => filled($deviceName) ? Str::limit($deviceName, 250, '') : null,
            'device_id' => $deviceId,
            'expires_at' => now()->addMonths(self::VALID_MONTHS),
        ]);
        $key->key = self::KEY_PREFIX.Str::random(32);
        $key->secret_hash = hash('sha256', $secret);
        $key->save();

        return ['key' => $key, 'secret' => $secret];
    }

    /**
     * The key matching a "<key>.<secret>" token, or null. Expired and revoked keys are returned too,
     * so the caller can tell the app why it has to sign in again.
     */
    public static function findByToken(?string $token): ?self
    {
        [$key, $secret] = array_pad(explode('.', (string) $token, 2), 2, '');

        if (! str_starts_with($key, self::KEY_PREFIX) || $secret === '') {
            return null;
        }

        $record = self::query()->where('key', $key)->first();

        return $record !== null && hash_equals($record->secret_hash, hash('sha256', $secret)) ? $record : null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function revoke(MobileKeyRevokeReason $reason): void
    {
        if (! $this->isRevoked()) {
            $this->forceFill(['revoked_at' => now(), 'revoked_reason' => $reason])->save();
        }
    }

    /**
     * Revokes every usable key of a user, e.g. when the login is switched off.
     */
    public static function revokeAllFor(User $user, MobileKeyRevokeReason $reason): void
    {
        self::query()->where('user_id', $user->id)->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoked_reason' => $reason->value, 'updated_at' => now()]);
    }

    public function markUsed(?string $ip): void
    {
        if ($this->last_used_at === null || $this->last_used_at->lt(now()->subSeconds(self::TOUCH_INTERVAL))) {
            $this->forceFill(['last_used_at' => now(), 'last_used_ip' => $ip])->saveQuietly();
        }
    }

    /**
     * @param  Builder<MobileKey>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'push_token_updated_at' => 'datetime',
            'revoked_reason' => MobileKeyRevokeReason::class,
        ];
    }
}
