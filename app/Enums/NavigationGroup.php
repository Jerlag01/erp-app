<?php

namespace App\Enums;

use Filament\Navigation\NavigationGroup as FilamentNavigationGroup;
use Filament\Support\Contracts\HasLabel;

enum NavigationGroup implements HasLabel
{
    case Organisation;
    case HumanResources;
    case Fleet;
    case Assets;
    case Stock;
    case It;
    case WorkSites;
    case Projects;
    case KnowledgeBase;
    case Waste;
    case Safety;
    case Purchasing;
    case System;

    public function getLabel(): string
    {
        return __('erp.groups.'.str($this->name)->snake());
    }

    /**
     * Panel group order. Labels are closures so they're translated per request, not once at boot.
     *
     * @return list<FilamentNavigationGroup>
     */
    public static function navigationGroups(): array
    {
        return array_map(
            fn (self $case): FilamentNavigationGroup => FilamentNavigationGroup::make(fn (): string => $case->getLabel()),
            self::cases(),
        );
    }
}
