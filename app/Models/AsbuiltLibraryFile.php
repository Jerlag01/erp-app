<?php

namespace App\Models;

use App\Enums\AsbuiltCategory;
use Database\Factories\AsbuiltLibraryFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A standard file of the as-built register, reused by several as-builts: per COW-code (evacuation
 * routes; optional for the other categories) or general (always for external influences). Technical documents
 * are a register to pick from. Any file format, on the private disk.
 */
#[Fillable(['category', 'work_site_id', 'title', 'path', 'original_name', 'size', 'uploaded_by', 'uploaded_by_client_user_id'])]
class AsbuiltLibraryFile extends Model
{
    /** @use HasFactory<AsbuiltLibraryFileFactory> */
    use HasFactory;

    public const DISK = 'local';

    /**
     * @return BelongsTo<WorkSite, $this>
     */
    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The as-builts it is used in.
     *
     * @return HasMany<AsbuiltFile, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(AsbuiltFile::class);
    }

    protected static function booted(): void
    {
        // Global files (external influences) and the register (technical documents) have no COW-code.
        static::saving(function (self $file): void {
            if ($file->category->isGlobal() || $file->category->isRegister()) {
                $file->work_site_id = null;
            }
        });
        static::deleted(fn (self $file) => Storage::disk(self::DISK)->delete($file->path));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => AsbuiltCategory::class,
            'size' => 'integer',
        ];
    }
}
