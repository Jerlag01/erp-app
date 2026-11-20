<?php

namespace App\Models;

use App\Enums\ProjectFileSection;
use Database\Factories\BtvControlFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

/**
 * A BTV control of a project, apart from the as-built: requested on a day, confirmed by BTV for a
 * date, and afterwards its report, which is kept in the project's files ("BTV control", with
 * versions when it is replaced).
 */
#[Fillable(['project_id', 'requested_on', 'confirmed_on', 'project_file_id', 'notes', 'created_by', 'updated_by'])]
class BtvControl extends Model
{
    /** @use HasFactory<BtvControlFactory> */
    use HasFactory;

    public const REQUESTED = 'requested';

    public const CONFIRMED = 'confirmed';

    public const REPORTED = 'reported';

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The report in the project's files.
     *
     * @return BelongsTo<ProjectFile, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(ProjectFile::class, 'project_file_id');
    }

    /**
     * Requested (no date from BTV yet), confirmed (a date, no report) or reported.
     */
    public function status(): string
    {
        return match (true) {
            $this->project_file_id !== null => self::REPORTED,
            $this->confirmed_on !== null => self::CONFIRMED,
            default => self::REQUESTED,
        };
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            self::REPORTED => 'success',
            self::CONFIRMED => 'info',
            default => 'warning',
        };
    }

    /**
     * Stores the report in the project's files (a new version when there already is one).
     */
    public function attachReport(string|UploadedFile $upload, ?string $originalName, ?User $user): ProjectFile
    {
        $file = ProjectFile::store($this->project, ProjectFileSection::BtvControl, $upload, $originalName, $user, $this->report);
        $this->forceFill(['project_file_id' => $file->id, 'updated_by' => $user?->id])->save();

        return $file;
    }

    public function reportUrl(): ?string
    {
        return $this->project_file_id ? route('projects.file', ['project' => $this->project_id, 'file' => $this->project_file_id]) : null;
    }

    /**
     * @param  Builder<BtvControl>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('project', fn (Builder $query) => $query->visibleTo($user, 'btv_controls'));
    }

    /**
     * @param  Builder<BtvControl>  $query
     */
    public function scopeWithStatus(Builder $query, string $status): void
    {
        match ($status) {
            self::REPORTED => $query->whereNotNull('project_file_id'),
            self::CONFIRMED => $query->whereNull('project_file_id')->whereNotNull('confirmed_on'),
            default => $query->whereNull('project_file_id')->whereNull('confirmed_on'),
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_on' => 'date',
            'confirmed_on' => 'date',
        ];
    }
}
