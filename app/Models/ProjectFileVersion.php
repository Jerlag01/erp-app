<?php

namespace App\Models;

use App\Enums\ProjectFileSection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

/**
 * One uploaded version of a project file.
 */
#[Fillable(['project_file_id', 'version', 'path', 'original_name', 'mime_type', 'size', 'user_id', 'created_at'])]
class ProjectFileVersion extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<ProjectFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(ProjectFile::class, 'project_file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPreviewableImage(): bool
    {
        return ProjectFileSection::isPreviewableImage($this->mime_type);
    }

    public function humanSize(): string
    {
        return Number::fileSize((int) $this->size, precision: 1);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'size' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
