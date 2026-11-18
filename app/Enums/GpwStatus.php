<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Where a GPW request stands: waiting for the client, approved or refused by them, planned for a
 * team in the approved period, in progress (work has begun in the period), executed (project archived), or
 * withdrawn by us. From approved on it follows the project by itself (SyncGpwStatus).
 */
enum GpwStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';

    case Approved = 'approved';

    case Rejected = 'rejected';

    case Planned = 'planned';

    case InProgress = 'in_progress';

    case Executed = 'executed';

    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __('erp.enums.gpw_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Planned => 'info',
            self::InProgress => 'primary',
            self::Executed => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::Approved => Heroicon::OutlinedCheckCircle,
            self::Rejected => Heroicon::OutlinedXCircle,
            self::Planned => Heroicon::OutlinedCalendarDays,
            self::InProgress => Heroicon::OutlinedWrenchScrewdriver,
            self::Executed => Heroicon::OutlinedCheckBadge,
            self::Cancelled => Heroicon::OutlinedMinusCircle,
        };
    }
}
