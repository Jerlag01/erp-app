<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A backup made to a target, or a restore from one (kind "restore"): when, what, the result.
 */
#[Fillable(['backup_target_id', 'target_name', 'kind', 'file_name', 'size', 'parts', 'status', 'message', 'user_id', 'started_at', 'finished_at'])]
class Backup extends Model
{
    /**
     * @return BelongsTo<BackupTarget, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(BackupTarget::class, 'backup_target_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['parts' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
