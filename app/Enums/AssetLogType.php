<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetLogType: string implements HasColor, HasLabel
{
    case Damage = 'damage';

    case Repair = 'repair';

    case Inspection = 'inspection';

    case Note = 'note';

    public function getLabel(): string
    {
        return __('erp.enums.asset_log_type.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Damage => 'danger',
            self::Repair => 'success',
            self::Inspection => 'info',
            self::Note => 'gray',
        };
    }
}
