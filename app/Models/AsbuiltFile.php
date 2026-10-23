<?php

namespace App\Models;

use App\Enums\AsbuiltCategory;
use Database\Factories\AsbuiltFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file of an as-built: a standard file of the register (linked, so it is shared) or a file
 * uploaded to this as-built by an employee or the client. "category" is an AsbuiltCategory or
 * AsbuiltCategory::GENERAL for what the client hands in at once.
 */
#[Fillable(['asbuilt_id', 'category', 'asbuilt_library_file_id', 'path', 'original_name', 'size', 'uploaded_by', 'uploaded_by_client_user_id'])]
class AsbuiltFile extends Model
{
    /** @use HasFactory<AsbuiltFileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Asbuilt, $this>
     */
    public function asbuilt(): BelongsTo
    {
        return $this->belongsTo(Asbuilt::class);
    }

    /**
     * @return BelongsTo<AsbuiltLibraryFile, $this>
     */
    public function libraryFile(): BelongsTo
    {
        return $this->belongsTo(AsbuiltLibraryFile::class, 'asbuilt_library_file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<ClientUser, $this>
     */
    public function clientUploader(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'uploaded_by_client_user_id');
    }

    public function categoryEnum(): ?AsbuiltCategory
    {
        return AsbuiltCategory::tryFrom($this->category);
    }

    public function storagePath(): ?string
    {
        return $this->libraryFile?->path ?? $this->path;
    }

    public function fileName(): string
    {
        return (string) ($this->libraryFile?->original_name ?? $this->original_name ?? basename((string) $this->path));
    }

    public function title(): string
    {
        return (string) ($this->libraryFile?->title ?? $this->fileName());
    }

    public function sizeBytes(): int
    {
        return (int) ($this->libraryFile?->size ?? $this->size);
    }

    public function isFromLibrary(): bool
    {
        return $this->asbuilt_library_file_id !== null;
    }

    public function uploaderName(): ?string
    {
        return $this->libraryFile?->uploader?->name ?? $this->uploader?->name ?? $this->clientUploader?->name;
    }

    protected static function booted(): void
    {
        // Only the as-built's own copy is removed; a file of the register stays.
        static::deleted(function (self $file): void {
            if ($file->asbuilt_library_file_id === null && filled($file->path)) {
                Storage::disk(Asbuilt::DISK)->delete($file->path);
            }
        });
    }
}
