<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * A document an employee signed, kept as PDF in their "signed documents" (e.g. the delivery report of an
 * IT asset). The source is what it was signed for.
 */
#[Fillable(['user_id', 'kind', 'title', 'path', 'source_type', 'source_id', 'signed_at'])]
class SignedDocument extends Model
{
    public const DISK = 'local';

    public const KIND_IT_DELIVERY = 'it_delivery_report';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function kindLabel(): string
    {
        return __('erp.signed_documents.kinds.'.$this->kind);
    }

    public function fileName(): string
    {
        return basename($this->path);
    }

    /**
     * The employee themselves, or an administrator (who manages the employee register).
     */
    public function mayBeOpenedBy(?User $user): bool
    {
        return $user !== null && ($user->id === $this->user_id || $user->isAdmin());
    }

    protected static function booted(): void
    {
        static::deleted(fn (self $document) => Storage::disk(self::DISK)->delete($document->path));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }
}
