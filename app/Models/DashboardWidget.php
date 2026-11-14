<?php

namespace App\Models;

use Database\Factories\DashboardWidgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dashboard block someone built: a counter, a follow-up list or a status overview on one list (a
 * resource of the panel, see DashboardSource) with filters, display and size in "config". Its owner
 * edits it; a shared block (administrators) can be put on everyone's dashboard.
 */
#[Fillable(['user_id', 'panel', 'type', 'title', 'source', 'config', 'column_span', 'is_shared'])]
class DashboardWidget extends Model
{
    /** @use HasFactory<DashboardWidgetFactory> */
    use HasFactory;

    public const COUNTER = 'counter';

    public const LIST = 'list';

    public const STATUS = 'status';

    public const TYPES = [self::COUNTER, self::LIST, self::STATUS];

    public const SPANS = ['1', '2', 'full'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The key of the block in a dashboard layout.
     */
    public function key(): string
    {
        return 'custom:'.$this->id;
    }

    public static function idFromKey(string $key): ?int
    {
        return str_starts_with($key, 'custom:') ? (int) substr($key, 7) : null;
    }

    /**
     * The blocks a user may put on their dashboard of this panel: their own and the shared ones.
     *
     * @param  Builder<DashboardWidget>  $query
     */
    public function scopeAvailableTo(Builder $query, User $user, string $panel): void
    {
        $query->where('panel', $panel)->where(fn (Builder $query) => $query->where('user_id', $user->id)->orWhere('is_shared', true));
    }

    public function isEditableBy(?User $user): bool
    {
        return $user !== null && ($this->user_id === $user->id || ($this->is_shared && $user->isAdmin()));
    }

    /**
     * One setting of the block.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_shared' => 'boolean',
        ];
    }
}
