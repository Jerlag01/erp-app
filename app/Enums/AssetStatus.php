<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetStatus: string implements HasColor, HasLabel
{
    case InService = 'in_service';

    case Damaged = 'damaged';

    case InRepair = 'in_repair';

    case OutOfService = 'out_of_service';

    case Retired = 'retired';

    public function getLabel(): string
    {
        return __('erp.enums.asset_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InService => 'success',
            self::Damaged => 'danger',
            self::InRepair => 'warning',
            self::OutOfService => 'gray',
            self::Retired => 'gray',
        };
    }
}
