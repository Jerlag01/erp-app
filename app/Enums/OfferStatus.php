<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Draft → sent to the client → accepted (and invoiced) or rejected.
 */
enum OfferStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';

    case Sent = 'sent';

    case Accepted = 'accepted';

    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return __('erp.enums.offer_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'danger',
        };
    }
}
