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

    /**
     * The client approved a GPW request: to be planned in the approved period.
     */
    case ToBePlanned = 'to_be_planned';

    case Planned = 'planned';

    case ToBeExecuted = 'to_be_executed';

    case InProgress = 'in_progress';

    case Executed = 'executed';

    case InvoicingToDo = 'invoicing_to_do';

    case Invoiced = 'invoiced';

    /**
     * Finished for good: the project is archived with a ZIP of all its files.
     */
    case Closed = 'closed';

    public function getLabel(): string
    {
        return __('erp.enums.project_status.'.$this->value);
    }

    /**
     * Open projects can be put in the planning: to be planned, planned, to be executed or in progress.
     */
    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::ToBePlanned, self::Planned, self::ToBeExecuted, self::InProgress];
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Offer => 'gray',
            self::ToBePlanned => 'warning',
            self::Planned => 'info',
            self::ToBeExecuted => 'primary',
            self::InProgress => 'warning',
            self::Executed => 'success',
            self::InvoicingToDo => 'danger',
            self::Invoiced => 'success',
            self::Closed => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Offer => Heroicon::OutlinedDocumentText,
            self::ToBePlanned => Heroicon::OutlinedCalendar,
            self::Planned => Heroicon::OutlinedCalendarDays,
            self::ToBeExecuted => Heroicon::OutlinedClock,
            self::InProgress => Heroicon::OutlinedWrenchScrewdriver,
            self::Executed => Heroicon::OutlinedCheckCircle,
            self::InvoicingToDo => Heroicon::OutlinedCurrencyEuro,
            self::Invoiced => Heroicon::OutlinedBanknotes,
            self::Closed => Heroicon::OutlinedArchiveBox,
        };
    }
}
