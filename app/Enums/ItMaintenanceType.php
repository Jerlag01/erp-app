<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ItMaintenanceType: string implements HasColor, HasLabel
{
    case Maintenance = 'maintenance';

    case Repair = 'repair';

    case Upgrade = 'upgrade';

    case PatTest = 'pat_test';

    case Calibration = 'calibration';

    case SoftwareSupport = 'software_support';

    case HardwareSupport = 'hardware_support';

    public function getLabel(): string
    {
        return __('erp.enums.it_maintenance_type.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Maintenance => 'info',
            self::Repair => 'danger',
            self::Upgrade => 'success',
            self::PatTest => 'warning',
            self::Calibration => 'gray',
            self::SoftwareSupport => 'gray',
            self::HardwareSupport => 'gray',
        };
    }
}
