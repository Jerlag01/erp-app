<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ItStatusType: string implements HasColor, HasLabel
{
    case Deployable = 'deployable';

    case Pending = 'pending';

    case Undeployable = 'undeployable';

    case Archived = 'archived';

    public function getLabel(): string
    {
        return __('erp.enums.it_status_type.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Deployable => 'success',
            self::Pending => 'warning',
            self::Undeployable => 'danger',
            self::Archived => 'gray',
        };
    }
}
