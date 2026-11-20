<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One planning of a project without an approved GPW request: who did it, when, why and what was
 * planned ("planned"). Without a reason it was done by someone with "planning.bypass_gpw".
 */
#[Fillable(['project_id', 'user_id', 'reason', 'planned'])]
class ProjectGpwBypass extends Model
{
    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
