<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * The categories of an as-built, in the order of the ZIP ("0 - BTV control" … "8 - evacuation
 * routes"). Evacuation routes belong to a COW-code; external influences are global (in every
 * as-built); technical documents are a register picked from per as-built. The client may hand in evacuation routes, plans, external
 * influences and the ICC and Lmax calculations.
 * Battery rooms (609) have their own set (forBatteryRoom): BTV control, plans and ventilation calculation
 * per COW-code, their own global external influences, the wiring diagram (taken from the closed 604),
 * the evacuation list per COW-code and edited floor plans.
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

    // Battery rooms (609).
    case RoomPlans = 'room_plans';

    case RoomExternalInfluences = 'room_external_influences';

    case VentilationCalculations = 'ventilation_calculations';

    case WiringDiagrams = 'wiring_diagrams';

    case RoomEvacuationLists = 'room_evacuation_lists';

    case EditedFloorPlans = 'edited_floor_plans';

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
            self::Plans, self::RoomPlans => Heroicon::OutlinedMap,
            self::VentilationCalculations => Heroicon::OutlinedCalculator,
            self::WiringDiagrams => Heroicon::OutlinedBolt,
            self::RoomEvacuationLists => Heroicon::OutlinedArrowRightStartOnRectangle,
            self::EditedFloorPlans => Heroicon::OutlinedPencilSquare,
            self::ElectricalSchematics => Heroicon::OutlinedBolt,
            self::TechnicalDocuments => Heroicon::OutlinedDocumentText,
            self::ExternalInfluences, self::RoomExternalInfluences => Heroicon::OutlinedCloud,
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
            self::RoomPlans => '1 - plans',
            self::RoomExternalInfluences => '2 - external influences',
            self::VentilationCalculations => '3 - ventilation calculation',
            self::WiringDiagrams => '4 - wiring diagram',
            self::RoomEvacuationLists => '5 - evacuation list',
            self::EditedFloorPlans => '6 - edited floor plans',
        };
    }

    /**
     * Standard files of these categories belong to a COW-code and are reused by its as-builts.
     */
    public function perCowCode(): bool
    {
        return in_array($this, [self::EvacuationRoutes, self::RoomPlans, self::VentilationCalculations, self::RoomEvacuationLists], true);
    }

    /**
     * Global over all projects: never tied to a COW-code, part of every as-built.
     */
    public function isGlobal(): bool
    {
        return in_array($this, [self::ExternalInfluences, self::RoomExternalInfluences], true);
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
        return $this->perCowCode() || $this->isGlobal() || $this->isRegister();
    }

    public function clientMayUpload(): bool
    {
        return in_array($this, [self::EvacuationRoutes, self::Plans, self::ExternalInfluences, self::IccCalculations, self::LmaxCalculations, self::RoomPlans, self::RoomExternalInfluences, self::RoomEvacuationLists], true);
    }

    public function isForBatteryRoom(): bool
    {
        return in_array($this, self::forBatteryRoom(), true) && $this !== self::BtvReports;
    }

    /**
     * The order on screen of an ordinary as-built: the order of the ZIP.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::BtvReports, self::CableDimensions, self::IccCalculations, self::LmaxCalculations, self::Plans, self::ElectricalSchematics, self::TechnicalDocuments, self::ExternalInfluences, self::EvacuationRoutes];
    }

    /**
     * The as-built of a battery room (609), in the order of its ZIP.
     *
     * @return list<self>
     */
    public static function forBatteryRoom(): array
    {
        return [self::BtvReports, self::RoomPlans, self::RoomExternalInfluences, self::VentilationCalculations, self::WiringDiagrams, self::RoomEvacuationLists, self::EditedFloorPlans];
    }

    /**
     * Every category, for the register: the ordinary ones, then the battery room ones.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return [...self::ordered(), ...array_values(array_filter(self::forBatteryRoom(), fn (self $category): bool => $category !== self::BtvReports))];
    }
}
