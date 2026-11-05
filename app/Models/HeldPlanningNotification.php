<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A planning notification held back while the employee is on leave, sent at send_at (the last day of the
 * leave):
 * - a push (channel "push"): payload {title, body, data};
 * - an e-mail to the employee (channel "mail"): payload {key, locale, values, html} with html the values
 *   that are Markdown built by the planning (the lists of items).
 */
#[Fillable(['user_id', 'channel', 'payload', 'send_at'])]
class HeldPlanningNotification extends Model
{
    public const PUSH = 'push';

    public const MAIL = 'mail';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDue(Builder $query): void
    {
        $query->where('send_at', '<=', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'send_at' => 'datetime',
        ];
    }
}
