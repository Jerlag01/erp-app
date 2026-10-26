<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A message sent from the platform to the mobile app (see SendPushMessage), with a title and text per
 * language. Each recipient gets it in their own language, as a push notification and under "Messages".
 */
#[Fillable(['title', 'body', 'audience', 'team_ids', 'recipients_count', 'sent_by'])]
class PushMessage extends Model
{
    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_TEAMS = 'teams';

    public const AUDIENCE_USERS = 'users';

    public const AUDIENCES = [self::AUDIENCE_ALL, self::AUDIENCE_TEAMS, self::AUDIENCE_USERS];

    /** Sending needs this permission, also for administrators. */
    public const PERMISSION = 'push_messages.send';

    /**
     * @return BelongsToMany<User, $this>
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['locale', 'read_at'])->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function titleIn(string $locale): string
    {
        return (string) (((array) $this->title)[$locale] ?? ((array) $this->title)[config('app.fallback_locale')] ?? '');
    }

    public function bodyIn(string $locale): string
    {
        return (string) (((array) $this->body)[$locale] ?? ((array) $this->body)[config('app.fallback_locale')] ?? '');
    }

    public static function canSend(?User $user): bool
    {
        return $user instanceof User && $user->isAdmin() && $user->hasPermission(self::PERMISSION);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => 'array',
            'body' => 'array',
            'team_ids' => 'array',
        ];
    }
}
