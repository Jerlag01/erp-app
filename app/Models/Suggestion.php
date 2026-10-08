<?php

namespace App\Models;

use App\Enums\SuggestionStatus;
use App\Enums\SuggestionType;
use App\Models\Concerns\HasPrivatePhotos;
use Database\Factories\SuggestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An entry in the idea/complaint box. Name and time come from the system, not from the form.
 */
#[Fillable([
    'user_id', 'first_name', 'last_name', 'submitter_name', 'submitted_at', 'type', 'may_be_public', 'description',
    'photos', 'status', 'response', 'handled_by', 'handled_at',
])]
class Suggestion extends Model
{
    /** @use HasFactory<SuggestionFactory> */
    use HasFactory, HasPrivatePhotos;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    protected static function booted(): void
    {
        static::creating(function (self $suggestion): void {
            $suggestion->submitted_at ??= now();
            $suggestion->status ??= SuggestionStatus::New;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SuggestionType::class,
            'status' => SuggestionStatus::class,
            'submitted_at' => 'datetime',
            'handled_at' => 'datetime',
            'may_be_public' => 'boolean',
            'photos' => 'array',
        ];
    }
}
