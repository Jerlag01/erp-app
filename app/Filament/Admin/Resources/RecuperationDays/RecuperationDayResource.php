<?php

namespace App\Filament\Admin\Resources\RecuperationDays;

use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\RecuperationDays\Pages\ManageRecuperationDays;
use App\Models\Holiday;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * "Inhaalrust" (IR) days: extra days off planned for all employees, outside vacation, family and medical
 * leave. Like public holidays they are no working days: they never count as vacation, block the planning
 * and are noted as IR (a full day) on the prestation sheet.
 */
class RecuperationDayResource extends Resource
{
    protected static ?string $model = Holiday::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'recuperation-days';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('erp.recuperation_days.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.recuperation_days.plural');
    }

    /**
     * @return Builder<Holiday>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->recuperationDays();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->label(__('erp.fields.date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required()
                    ->unique(table: 'holidays', column: 'date', ignoreRecord: true)
                    ->validationMessages(['unique' => __('erp.holidays.date_taken')]),
                TextInput::make('name')
                    ->label(__('erp.recuperation_days.description'))
                    ->placeholder(__('erp.recuperation_days.description_placeholder'))
                    ->default(__('erp.recuperation_days.default_name'))
                    ->required()
                    ->maxLength(100),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date')
            ->paginated([25, 50])
            ->emptyStateHeading(__('erp.recuperation_days.empty'))
            ->emptyStateDescription(__('erp.recuperation_days.empty_help'))
            ->emptyStateIcon(Heroicon::OutlinedCalendar)
            ->columns([
                TextColumn::make('date')
                    ->label(__('erp.fields.date'))
                    ->date('D d/m/Y')
                    ->fontFamily('mono')
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('erp.recuperation_days.description'))
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label(__('erp.fields.year'))
                    ->options(fn (): array => collect(range((int) date('Y') + 2, (int) date('Y') - 2))->mapWithKeys(fn (int $year): array => [$year => $year])->all())
                    ->default((string) date('Y'))
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, $year) => $query->whereYear('date', $year))),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRecuperationDays::route('/'),
        ];
    }
}
