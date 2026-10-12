<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Equipment a team added during the yearly control waits for an administrator.
 */
enum AssetProposalStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';

    case Approved = 'approved';

    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return __('erp.enums.asset_proposal_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
