<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a charging station reaches its back office.
 */
enum ChargingConnectivity: string implements HasLabel
{
    case Mobile = 'mobile';

    case ModemWired = 'modem_wired';

    case Wired = 'wired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mobile => 'Mobile',
            self::ModemWired => '4G-Modem + Wired',
            self::Wired => 'Wired',
        };
    }
}
