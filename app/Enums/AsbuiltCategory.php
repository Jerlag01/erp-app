<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * The categories of an as-built, in the order of the ZIP ("0 - BTV control" … "8 - evacuation
 * routes"). Evacuation routes and external influences belong to a COW-code; technical documents are a
 * register picked from per as-built. The client may hand in evacuation routes, plans, external
 * influences and the ICC and Lmax calculations.
 */
enum AsbuiltCategory: string implements HasIcon, HasLabel
{
    case BtvReports = 'btv_reports';

    case CableDimensions = 'cable_dimensions';

    case IccCalculations = 'icc_calculations';

    case LmaxCalculations = 'lmax_calculations';

    case Plans = 'plans';

    case ElectricalSchematics = 'electrical_schematics';

    case TechnicalDocuments = 'technical_documents';

    case ExternalInfluences = 'external_influences';

    case EvacuationRoutes = 'evacuation_routes';

    /** Files the client hands in at once (mostly one ZIP), sorted afterwards. Not in the ZIP of the as-built. */
    public const GENERAL = 'general';

    public function getLabel(): string
    {
        return __('erp.enums.asbuilt_category.'.$this->value);
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::BtvReports => Heroicon::OutlinedClipboardDocumentCheck,
            self::CableDimensions => Heroicon::OutlinedArrowsPointingOut,
            self::IccCalculations, self::LmaxCalculations => Heroicon::OutlinedCalculator,
            self::Plans => Heroicon::OutlinedMap,
            self::ElectricalSchematics => Heroicon::OutlinedBolt,
            self::TechnicalDocuments => Heroicon::OutlinedDocumentText,
            self::ExternalInfluences => Heroicon::OutlinedCloud,
            self::EvacuationRoutes => Heroicon::OutlinedArrowRightStartOnRectangle,
        };
    }

    /**
     * The folder in the ZIP of the as-built.
     */
    public function folder(): string
    {
        return match ($this) {
            self::BtvReports => '0 - BTV control',
            self::CableDimensions => '1 - cable dimensions',
            self::IccCalculations => '2 - ICC calculations',
            self::LmaxCalculations => '3 - Lmax calculations',
            self::Plans => '4 - plans',
            self::ElectricalSchematics => '5 - electrical schematics',
            self::TechnicalDocuments => '6 - technical documents',
            self::ExternalInfluences => '7 - external influences',
            self::EvacuationRoutes => '8 - evacuation routes',
        };
    }

    /**
     * Standard files of these categories belong to a COW-code and are reused by its as-builts.
     */
    public function perCowCode(): bool
    {
        return in_array($this, [self::EvacuationRoutes, self::ExternalInfluences], true);
    }

    /**
     * A register of files picked per as-built, never added automatically.
     */
    public function isRegister(): bool
    {
        return $this === self::TechnicalDocuments;
    }

    /**
     * Files uploaded in an as-built go to the register too, so later as-builts can reuse them.
     */
    public function isReusable(): bool
    {
        return $this->perCowCode() || $this->isRegister();
    }

    public function clientMayUpload(): bool
    {
        return in_array($this, [self::EvacuationRoutes, self::Plans, self::ExternalInfluences, self::IccCalculations, self::LmaxCalculations], true);
    }

    /**
     * The order on screen: the order of the ZIP.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return self::cases();
    }
}
