<?php

namespace App\Filament\Resources\EnersysDossiers;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\ScopesToVisibleRecords;
use App\Filament\Resources\EnersysDossiers\Pages\CreateEnersysDossier;
use App\Filament\Resources\EnersysDossiers\Pages\EditEnersysDossier;
use App\Filament\Resources\EnersysDossiers\Pages\ListEnersysDossiers;
use App\Filament\Resources\EnersysDossiers\Schemas\EnersysDossierForm;
use App\Models\EnersysDossier;
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
 * Enersys battery dossiers of 605 projects: the Excel "12V enersys" with its documents as PDF.
 */
class EnersysDossierResource extends Resource
{
    use ScopesToVisibleRecords;

    protected static ?string $model = EnersysDossier::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'enersys-dossiers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBattery50;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Batteries;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('erp.enersys.dossier');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.enersys.dossiers');
    }

    public static function form(Schema $schema): Schema
    {
        return EnersysDossierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project.workSite']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('project.reference')
                    ->label(__('erp.projects.reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->description(fn (EnersysDossier $record): string => $record->description())
                    ->searchable(),
                TextColumn::make('cow')
                    ->label(__('erp.batteries.cow_code'))
                    ->state(fn (EnersysDossier $record): ?string => $record->cowCode())
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('po_number')->label(__('erp.batteries.po_number'))->fontFamily(FontFamily::Mono)->searchable()->placeholder('—'),
                TextColumn::make('battery_type')
                    ->label(__('erp.enersys.type'))
                    ->description(fn (EnersysDossier $record): string => $record->new_count.' × '.$record->cells_per_battery.' '.__('erp.enersys.blocks'))
                    ->placeholder('—'),
                TextColumn::make('execution_date')->label(__('erp.batteries.execution_date'))->date('d/m/Y')->sortable()->placeholder('—'),
                TextColumn::make('finished_at')
                    ->label(__('erp.batteries.finish.status'))
                    ->state(fn (EnersysDossier $record): string => $record->isFinished() ? __('erp.batteries.finish.finished') : __('erp.batteries.finish.open'))
                    ->badge()
                    ->color(fn (EnersysDossier $record): string => $record->isFinished() ? 'success' : 'gray')
                    ->icon(fn (EnersysDossier $record): ?Heroicon => $record->isFinished() ? Heroicon::OutlinedLockClosed : null),
                TextColumn::make('updated_at')->label(__('erp.projects.updated'))->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnersysDossiers::route('/'),
            'create' => CreateEnersysDossier::route('/create'),
            'edit' => EditEnersysDossier::route('/{record}/edit'),
        ];
    }
}
