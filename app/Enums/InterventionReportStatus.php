<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * After the employee closes an intervention it waits for an administrator: approved (ready to
 * invoice) or rejected (back to the employee to correct and close again).
 */
enum InterventionReportStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';

    case Approved = 'approved';

    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return __('erp.enums.intervention_report_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::Approved => Heroicon::OutlinedCheckBadge,
            self::Rejected => Heroicon::OutlinedArrowUturnLeft,
        };
    }
}
