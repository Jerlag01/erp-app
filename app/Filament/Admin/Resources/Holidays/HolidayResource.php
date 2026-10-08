<?php

namespace App\Filament\Admin\Resources\Holidays;

use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\Holidays\Pages\ManageHolidays;
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

class HolidayResource extends Resource
{
    protected static ?string $model = Holiday::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('erp.resources.holiday.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.holiday.plural');
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
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label(__('erp.fields.name'))
                    ->required()
                    ->maxLength(100),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date')
            ->paginated([25, 50])
            ->columns([
                TextColumn::make('date')
                    ->label(__('erp.fields.date'))
                    ->date('D d/m/Y')
                    ->fontFamily('mono')
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
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
            'index' => ManageHolidays::route('/'),
        ];
    }
}
