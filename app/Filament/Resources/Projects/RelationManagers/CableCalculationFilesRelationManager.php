<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectFileSection;
use App\Filament\Resources\CableCalculations\CableCalculationResource;
use App\Models\CableCalculation;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * "Kabelberekeningen": the PDF of every cable calculation of the project (a new version on every save of
 * the calculation), a button to make a new calculation for this project and a link to the existing ones.
 * The PDFs are made by CableCalculationDocuments, so nothing is uploaded here by hand.
 */
class CableCalculationFilesRelationManager extends ProjectFilesRelationManager
{
    protected static string $relationship = 'cableCalculationFiles';

    public static function section(): ProjectFileSection
    {
        return ProjectFileSection::CableCalculations;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Project $ownerRecord */
        return parent::canViewForRecord($ownerRecord, $pageClass)
            && (CableCalculationResource::canCreate() || $ownerRecord->cableCalculationFiles()->exists() || CableCalculation::query()->where('project_id', $ownerRecord->id)->exists());
    }

    public function table(Table $table): Table
    {
        $table = parent::table($table);
        $project = fn () => $this->getOwnerRecord();
        $count = fn (): int => CableCalculation::query()->where('project_id', $project()->id)->count();

        return $table
            ->emptyStateHeading(__('erp.cable_calculator.none_for_project'))
            ->headerActions([
                Action::make('makeCableCalculation')
                    ->label(__('erp.cable_calculator.make_for_project'))
                    ->icon(Heroicon::OutlinedCalculator)
                    ->visible(fn (): bool => CableCalculationResource::canCreate() && $this->canManage())
                    ->url(fn (): string => CableCalculationResource::getUrl('create', ['project' => $project()->id])),
                Action::make('projectCableCalculations')
                    ->label(fn (): string => __('erp.cable_calculator.of_project', ['count' => $count()]))
                    ->icon(Heroicon::OutlinedListBullet)
                    ->color('gray')
                    ->visible(fn (): bool => Gate::allows('viewAny', CableCalculation::class) && $count() > 0)
                    ->url(fn (): string => CableCalculationResource::getUrl('index', ['filters' => ['project_id' => ['value' => $project()->id]]])),
            ]);
    }
}
