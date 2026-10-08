<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Where a project stands, from offer to invoiced. The same for every category.
 */
enum ProjectStatus: string implements HasColor, HasIcon, HasLabel
{
    case Offer = 'offer';

    case Planned = 'planned';

    case ToBeExecuted = 'to_be_executed';

    case InProgress = 'in_progress';

    case Executed = 'executed';

    case InvoicingToDo = 'invoicing_to_do';

    case Invoiced = 'invoiced';

    public function getLabel(): string
    {
        return __('erp.enums.project_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Offer => 'gray',
            self::Planned => 'info',
            self::ToBeExecuted => 'primary',
            self::InProgress => 'warning',
            self::Executed => 'success',
            self::InvoicingToDo => 'danger',
            self::Invoiced => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Offer => Heroicon::OutlinedDocumentText,
            self::Planned => Heroicon::OutlinedCalendarDays,
            self::ToBeExecuted => Heroicon::OutlinedClock,
            self::InProgress => Heroicon::OutlinedWrenchScrewdriver,
            self::Executed => Heroicon::OutlinedCheckCircle,
            self::InvoicingToDo => Heroicon::OutlinedCurrencyEuro,
            self::Invoiced => Heroicon::OutlinedBanknotes,
        };
    }
}
