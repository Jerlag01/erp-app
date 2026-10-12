<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ChargingStationType: string implements HasLabel
{
    case Standalone = 'standalone';

    case Cluster = 'cluster';

    public function getLabel(): string
    {
        return match ($this) {
            self::Standalone => 'Standalone',
            self::Cluster => 'Cluster',
        };
    }
}
