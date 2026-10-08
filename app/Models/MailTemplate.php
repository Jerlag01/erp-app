<?php

namespace App\Models;

use App\Enums\MailTemplateKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The editable text of a system e-mail in one language. Placeholders such as {client_name} are
 * filled in when the mail is sent (see App\Support\MailTemplates).
 */
#[Fillable(['key', 'locale', 'subject', 'body', 'is_active', 'updated_by'])]
class MailTemplate extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => MailTemplateKey::class,
            'is_active' => 'boolean',
        ];
    }
}
