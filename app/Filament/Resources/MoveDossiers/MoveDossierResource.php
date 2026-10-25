<?php

namespace App\Filament\Resources\MoveDossiers;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\ScopesToVisibleRecords;
use App\Filament\Resources\MoveDossiers\Pages\CreateMoveDossier;
use App\Filament\Resources\MoveDossiers\Pages\EditMoveDossier;
use App\Filament\Resources\MoveDossiers\Pages\ListMoveDossiers;
use App\Filament\Resources\MoveDossiers\Schemas\MoveDossierForm;
use App\Models\MoveDossier;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Battery moves of 605 projects: batteries moved from one COW site to another, with the documents of
 * the 604 dossier and two shipping notes.
 */
class MoveDossierResource extends Resource
{
    use ScopesToVisibleRecords;

    protected static ?string $model = MoveDossier::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'move-dossiers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Batteries;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('erp.moves.dossier');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.moves.dossiers');
    }

    public static function form(Schema $schema): Schema
    {
        return MoveDossierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project.workSite', 'fromSite', 'toSite', 'batteryModel']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('project.reference')
                    ->label(__('erp.projects.reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->description(fn (MoveDossier $record): string => $record->description())
                    ->searchable(),
                TextColumn::make('route')
                    ->label(__('erp.moves.route'))
                    ->state(fn (MoveDossier $record): string => ($record->sourceCowCode() ?? '—').' → '.($record->cowCode() ?? '—'))
                    ->fontFamily(FontFamily::Mono),
                IconColumn::make('decommission_source')->label(__('erp.moves.decommission_short'))->boolean(),
                TextColumn::make('execution_date')->label(__('erp.batteries.execution_date'))->date('d/m/Y')->sortable()->placeholder('—'),
                TextColumn::make('finished_at')
                    ->label(__('erp.batteries.finish.status'))
                    ->state(fn (MoveDossier $record): string => $record->isFinished() ? __('erp.batteries.finish.finished') : __('erp.batteries.finish.open'))
                    ->badge()
                    ->color(fn (MoveDossier $record): string => $record->isFinished() ? 'success' : 'gray')
                    ->icon(fn (MoveDossier $record): ?Heroicon => $record->isFinished() ? Heroicon::OutlinedLockClosed : null),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMoveDossiers::route('/'),
            'create' => CreateMoveDossier::route('/create'),
            'edit' => EditMoveDossier::route('/{record}/edit'),
        ];
    }
}
