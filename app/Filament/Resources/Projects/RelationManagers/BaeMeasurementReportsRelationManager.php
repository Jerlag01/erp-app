<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectFileSection;
use App\Support\BatteryReports;
use Illuminate\Database\Eloquent\Model;

/**
 * The measurement report (PDF) BAE made of a battery replacement (604): uploaded afterwards, and shown to
 * the client with the other documents of the project (see BatteryReports).
 */
class BaeMeasurementReportsRelationManager extends ProjectFilesRelationManager
{
    protected static string $relationship = 'baeMeasurementReports';

    public static function section(): ProjectFileSection
    {
        return ProjectFileSection::BaeMeasurementReports;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (string) $ownerRecord->category?->code === BatteryReports::CATEGORY && parent::canViewForRecord($ownerRecord, $pageClass);
    }
}
