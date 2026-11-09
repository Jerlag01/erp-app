<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectFileSection;
use App\Support\BatteryReports;
use Illuminate\Database\Eloquent\Model;

/**
 * The waste report (PDF) of a battery replacement (604): uploaded once the batteries were taken away,
 * and shown to the client with the other documents of the project (see BatteryReports).
 */
class WasteReportsRelationManager extends ProjectFilesRelationManager
{
    protected static string $relationship = 'wasteReports';

    public static function section(): ProjectFileSection
    {
        return ProjectFileSection::WasteReports;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (string) $ownerRecord->category?->code === BatteryReports::CATEGORY && parent::canViewForRecord($ownerRecord, $pageClass);
    }
}
