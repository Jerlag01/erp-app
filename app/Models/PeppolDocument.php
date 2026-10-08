<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Log of every Peppol (UBL) document sent or received.
 */
#[Fillable(['direction', 'document_type', 'documentable_type', 'documentable_id', 'sender_id', 'receiver_id', 'status', 'message_id', 'xml_path', 'error'])]
class PeppolDocument extends Model
{
    public const OUT = 'out';

    public const IN = 'in';

    /**
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
