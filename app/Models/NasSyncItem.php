<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One file to write on the NAS (see NasSync): where (target, below the root folder), from what (source:
 * a stored file or a PDF made at sending time) and how it went.
 */
#[Fillable(['nas_target_id', 'target', 'target_hash', 'source', 'status', 'attempts', 'last_error', 'synced_at'])]
class NasSyncItem extends Model
{
    /**
     * @return BelongsTo<NasTarget, $this>
     */
    public function nasTarget(): BelongsTo
    {
        return $this->belongsTo(NasTarget::class);
    }

    public const PENDING = 'pending';

    public const DONE = 'done';

    public const FAILED = 'failed';

    /** After this many failed tries an item waits for "send again". */
    public const MAX_ATTEMPTS = 10;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => 'array',
            'attempts' => 'integer',
            'synced_at' => 'datetime',
        ];
    }
}
