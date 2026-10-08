<?php

namespace App\Models;

use App\Enums\ProjectFileSection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A file in one of a project's upload zones. Every upload of a new version is kept, so earlier
 * versions stay available; deleting the file removes all versions from the disk.
 */
#[Fillable(['project_id', 'section', 'name', 'version', 'description'])]
class ProjectFile extends Model
{
    /**
     * Project files live on the private disk and are only served through ProjectFileController.
     */
    public const DISK = 'local';

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<ProjectFileVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ProjectFileVersion::class)->orderByDesc('version');
    }

    /**
     * @return HasOne<ProjectFileVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(ProjectFileVersion::class)->ofMany('version', 'max');
    }

    public static function directoryFor(Project $project, ProjectFileSection $section): string
    {
        return "projects/{$project->id}/{$section->value}";
    }

    /**
     * Stores an uploaded file as version 1 of a new file, or as the next version of $file.
     * $path is a file already stored on the disk (by Filament), $file a fresh upload (in code).
     */
    public static function store(Project $project, ProjectFileSection $section, string|UploadedFile $upload, ?string $originalName = null, ?User $user = null, ?self $file = null): self
    {
        $disk = Storage::disk(self::DISK);

        if ($upload instanceof UploadedFile) {
            $originalName ??= $upload->getClientOriginalName();
            $path = $upload->store(self::directoryFor($project, $section), self::DISK);
        } else {
            $path = $upload;
        }

        $originalName = basename($originalName ?? $path);

        return DB::transaction(function () use ($project, $section, $path, $originalName, $user, $file, $disk): self {
            $file ??= $project->files()->create(['section' => $section->value, 'name' => $originalName, 'version' => 0]);
            $version = $file->version + 1;

            $file->versions()->create([
                'version' => $version,
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $disk->mimeType($path) ?: null,
                'size' => $disk->size($path),
                'user_id' => $user?->id,
                'created_at' => now(),
            ]);

            $file->update(['version' => $version, 'name' => $originalName]);

            return $file->unsetRelation('latestVersion');
        });
    }

    public function sectionEnum(): ProjectFileSection
    {
        return ProjectFileSection::from($this->section);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $file): void {
            Storage::disk(self::DISK)->delete($file->versions()->pluck('path')->all());
        });
    }
}
