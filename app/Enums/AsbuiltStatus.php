<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Where an as-built stands: being put together, the Normec BTV inspection is planned, or closed
 * (with the BTV report, zipped and available to the client).
 */
enum AsbuiltStatus: string implements HasColor, HasIcon, HasLabel
{
    case InProgress = 'in_progress';

    case BtvPlanned = 'btv_planned';

    case Closed = 'closed';

    public function getLabel(): string
    {
        return __('erp.enums.asbuilt_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InProgress => 'warning',
            self::BtvPlanned => 'info',
            self::Closed => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::InProgress => Heroicon::OutlinedWrenchScrewdriver,
            self::BtvPlanned => Heroicon::OutlinedCalendarDays,
            self::Closed => Heroicon::OutlinedArchiveBox,
        };
    }
}
