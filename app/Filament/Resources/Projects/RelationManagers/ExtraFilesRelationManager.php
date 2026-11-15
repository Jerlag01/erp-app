<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectFileSection;
use App\Filament\Resources\CableCalculations\CableCalculationResource;
use App\Models\CableCalculation;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * The extra files of a project, with the PDFs of its cable calculations (kept up to date on every save),
 * a button to make a new calculation for this project and a link to the existing ones.
 */
class ExtraFilesRelationManager extends ProjectFilesRelationManager
{
    protected static string $relationship = 'extraFiles';

    public static function section(): ProjectFileSection
    {
        return ProjectFileSection::Extra;
    }

    public function table(Table $table): Table
    {
        $table = parent::table($table);
        $project = fn () => $this->getOwnerRecord();
        $count = fn (): int => CableCalculation::query()->where('project_id', $project()->id)->count();

        return $table->headerActions([
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
            ...$table->getHeaderActions(),
        ]);
    }
}
