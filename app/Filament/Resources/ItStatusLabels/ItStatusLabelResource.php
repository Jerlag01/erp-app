<?php

namespace App\Filament\Resources\ItStatusLabels;

use App\Enums\ItStatusType;
use App\Enums\NavigationGroup;
use App\Filament\Resources\ItStatusLabels\Pages\ManageItStatusLabels;
use App\Models\ItStatusLabel;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Asset statuses. The type decides what is possible: only "deployable" assets can be checked out.
 */
class ItStatusLabelResource extends Resource
{
    protected static ?string $model = ItStatusLabel::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'it-status-labels';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::It;

    protected static ?int $navigationSort = 9;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('erp.resources.it_status_label.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.it_status_label.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('erp.fields.name'))
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                Select::make('type')
                    ->label(__('erp.fields.type'))
                    ->helperText(__('erp.help.it_status_type'))
                    ->options(ItStatusType::class)
                    ->required(),
                ColorPicker::make('color')
                    ->label(__('erp.fields.color'))
                    ->regex('/^#[0-9a-fA-F]{6}$/'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('assets'))
            ->defaultSort('name')
            ->columns([
                ColorColumn::make('color')->label('')->width('1%'),
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('erp.fields.type'))
                    ->badge(),
                TextColumn::make('assets_count')
                    ->label(__('erp.resources.it_asset.plural'))
                    ->badge()
                    ->color('gray'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageItStatusLabels::route('/'),
        ];
    }
}
