<?php

namespace App\Models;

use App\Enums\AsbuiltCategory;
use App\Enums\AsbuiltStatus;
use Database\Factories\AsbuiltFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * The as-built of a project: made when the project is executed, filled per category, inspected by
 * Normec BTV on the planned date and closed with the BTV report into a ZIP for the client.
 */
#[Fillable(['project_id', 'status', 'origin', 'created_by', 'btv_date', 'closed_at', 'zip_path', 'client_notified_at'])]
class Asbuilt extends Model
{
    /** @use HasFactory<AsbuiltFactory> */
    use HasFactory;

    public const DISK = 'local';

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public const ORIGIN_AUTOMATIC = 'automatic';

    public const ORIGIN_MANUAL = 'manual';

    /**
     * Who made it by hand with the button on the project (empty when made automatically).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function wasCreatedManually(): bool
    {
        return $this->origin === self::ORIGIN_MANUAL;
    }

    /**
     * @return HasMany<AsbuiltFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(AsbuiltFile::class)->orderBy('id');
    }

    /**
     * @return HasMany<AsbuiltFile, $this>
     */
    public function filesIn(AsbuiltCategory|string $category): HasMany
    {
        return $this->files()->where('category', $category instanceof AsbuiltCategory ? $category->value : $category);
    }

    public function isClosed(): bool
    {
        return $this->status === AsbuiltStatus::Closed;
    }

    /**
     * "610-012-02GAM - ASBUILT.zip": category, project number and COW-code.
     */
    public function zipName(): string
    {
        $name = str_replace(['/', '\\'], '-', (string) $this->project?->reference).' - ASBUILT.zip';

        return trim($name);
    }

    public function hasZip(): bool
    {
        return filled($this->zip_path) && Storage::disk(self::DISK)->exists($this->zip_path);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $asbuilt): void {
            // Its own files go with it; files of the register stay for other as-builts.
            $asbuilt->files()->whereNull('asbuilt_library_file_id')->get()->each->delete();

            if (filled($asbuilt->zip_path)) {
                Storage::disk(self::DISK)->delete($asbuilt->zip_path);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AsbuiltStatus::class,
            'btv_date' => 'date',
            'closed_at' => 'datetime',
            'client_notified_at' => 'datetime',
        ];
    }
}
