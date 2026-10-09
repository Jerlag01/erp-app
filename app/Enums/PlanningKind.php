<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * What a planning item is about: an open project, a job in the warehouse (with a description and
 * task list) or anything else, which needs the "planning.other" permission.
 */
enum PlanningKind: string implements HasColor, HasIcon, HasLabel
{
    case Project = 'project';

    case Warehouse = 'warehouse';

    case Other = 'other';

    public function getLabel(): string
    {
        return __('erp.enums.planning_kind.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Project => 'primary',
            self::Warehouse => 'info',
            self::Other => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Project => Heroicon::OutlinedBriefcase,
            self::Warehouse => Heroicon::OutlinedArchiveBox,
            self::Other => Heroicon::OutlinedCalendarDays,
        };
    }

    /**
     * Calendar colour of the kind.
     */
    public function hex(): string
    {
        return match ($this) {
            self::Project => '#2a78d6',
            self::Warehouse => '#0f8a63',
            self::Other => '#6d5bd0',
        };
    }
}
