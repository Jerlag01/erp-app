<?php

namespace App\Models;

use App\Models\Concerns\HasPrivatePhotos;
use Database\Factories\SuggestionResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reply in the conversation on an idea or complaint, by an administrator or by the employee who
 * submitted it, with text and photos (private disk).
 */
#[Fillable(['suggestion_id', 'user_id', 'author_name', 'from_admin', 'body', 'photos'])]
class SuggestionResponse extends Model
{
    /** @use HasFactory<SuggestionResponseFactory> */
    use HasFactory, HasPrivatePhotos;

    /**
     * @return BelongsTo<Suggestion, $this>
     */
    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(Suggestion::class);
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
        return [
            'from_admin' => 'boolean',
            'photos' => 'array',
        ];
    }
}
