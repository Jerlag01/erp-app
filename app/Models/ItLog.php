<?php

namespace App\Models;

use App\Enums\ItLogAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * History of IT items: checkouts, checkins, audits, consumption, installs.
 */
#[Fillable(['loggable_type', 'loggable_id', 'action', 'target_type', 'target_id', 'quantity', 'note', 'user_id', 'created_at'])]
class ItLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return MorphTo<Model, $this>
     */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user, location or asset the action was about.
     *
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Who did it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targetName(): ?string
    {
        return match (true) {
            $this->target instanceof User => $this->target->name,
            $this->target instanceof Location => $this->target->name,
            $this->target instanceof ItAsset => $this->target->label(),
            default => null,
        };
    }

    public static function record(Model $loggable, ItLogAction $action, ?Model $target = null, ?string $note = null, ?int $quantity = null): self
    {
        return static::create([
            'loggable_type' => $loggable->getMorphClass(),
            'loggable_id' => $loggable->getKey(),
            'action' => $action,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'quantity' => $quantity,
            'note' => $note,
            'user_id' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ItLogAction::class,
        ];
    }
}
