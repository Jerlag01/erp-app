<?php

namespace App\Filament\Resources\BatteryDossiers;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\ScopesToVisibleRecords;
use App\Filament\Resources\BatteryDossiers\Pages\CreateBatteryDossier;
use App\Filament\Resources\BatteryDossiers\Pages\EditBatteryDossier;
use App\Filament\Resources\BatteryDossiers\Pages\ListBatteryDossiers;
use App\Filament\Resources\BatteryDossiers\Schemas\BatteryDossierForm;
use App\Models\BatteryDossier;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Battery dossiers of 604 projects: the Excel "Online Dossier BAE NL" with its documents as PDF.
 */
class BatteryDossierResource extends Resource
{
    use ScopesToVisibleRecords;

    protected static ?string $model = BatteryDossier::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'battery-dossiers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBattery100;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Batteries;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('erp.batteries.dossier');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.batteries.dossiers');
    }

    public static function form(Schema $schema): Schema
    {
        return BatteryDossierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project.workSite', 'batteryModel']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('project.reference')
                    ->label(__('erp.projects.reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->description(fn (BatteryDossier $record): string => $record->description())
                    ->searchable(),
                TextColumn::make('cow')
                    ->label(__('erp.batteries.cow_code'))
                    ->state(fn (BatteryDossier $record): ?string => $record->cowCode())
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('po_number')->label(__('erp.batteries.po_number'))->fontFamily(FontFamily::Mono)->searchable()->placeholder('—'),
                TextColumn::make('batteryModel.name')
                    ->label(__('erp.batteries.model'))
                    ->description(fn (BatteryDossier $record): string => $record->new_count.' × '.$record->cells_per_battery.' '.__('erp.batteries.cells'))
                    ->placeholder('—'),
                TextColumn::make('execution_date')->label(__('erp.batteries.execution_date'))->date('d/m/Y')->sortable()->placeholder('—'),
                TextColumn::make('finished_at')
                    ->label(__('erp.batteries.finish.status'))
                    ->state(fn (BatteryDossier $record): string => $record->isFinished() ? __('erp.batteries.finish.finished') : __('erp.batteries.finish.open'))
                    ->badge()
                    ->color(fn (BatteryDossier $record): string => $record->isFinished() ? 'success' : 'gray')
                    ->icon(fn (BatteryDossier $record): ?Heroicon => $record->isFinished() ? Heroicon::OutlinedLockClosed : null),
                TextColumn::make('updated_at')->label(__('erp.projects.updated'))->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBatteryDossiers::route('/'),
            'create' => CreateBatteryDossier::route('/create'),
            'edit' => EditBatteryDossier::route('/{record}/edit'),
        ];
    }
}
