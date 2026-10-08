<?php

namespace App\Filament\Resources\ItModels;

use App\Enums\ItCategoryType;
use App\Enums\NavigationGroup;
use App\Filament\Resources\ItModels\Pages\ManageItModels;
use App\Models\ItCategory;
use App\Models\ItModel;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Hardware models, e.g. "Dell Latitude 5440". Assets are instances of a model.
 */
class ItModelResource extends Resource
{
    protected static ?string $model = ItModel::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'it-models';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::It;

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('erp.resources.it_model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.it_model.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label(__('erp.fields.name'))
                    ->placeholder('Latitude 5440')
                    ->required()
                    ->maxLength(150),
                Select::make('it_manufacturer_id')
                    ->label(__('erp.resources.it_manufacturer.singular'))
                    ->relationship('manufacturer', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([TextInput::make('name')->label(__('erp.fields.name'))->required()->maxLength(100)]),
                Select::make('it_category_id')
                    ->label(__('erp.fields.category'))
                    ->relationship('category', 'name', fn (Builder $query) => $query->ofType(ItCategoryType::Asset))
                    ->preload()
                    ->createOptionForm([TextInput::make('name')->label(__('erp.fields.name'))->required()->maxLength(100)])
                    ->createOptionUsing(fn (array $data): int => ItCategory::create([...$data, 'type' => ItCategoryType::Asset])->id),
                TextInput::make('model_number')
                    ->label(__('erp.fields.model_number'))
                    ->maxLength(100),
                TextInput::make('eol_months')
                    ->label(__('erp.fields.eol_months'))
                    ->helperText(__('erp.help.eol_months'))
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(240)
                    ->suffix(__('erp.fields.months')),
                FileUpload::make('image')
                    ->label(__('erp.fields.image'))
                    ->image()
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(2048)
                    ->disk('public')
                    ->directory('it-models')
                    ->visibility('public'),
                Textarea::make('notes')
                    ->label(__('erp.fields.notes'))
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['manufacturer', 'category'])->withCount('assets'))
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->imageHeight(32),
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->description(fn (ItModel $record): ?string => $record->manufacturer?->name)
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('model_number')
                    ->label(__('erp.fields.model_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—'),
                TextColumn::make('category.name')
                    ->label(__('erp.fields.category'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('eol_months')
                    ->label(__('erp.fields.eol_months'))
                    ->suffix(' '.__('erp.fields.months'))
                    ->placeholder('—'),
                TextColumn::make('assets_count')
                    ->label(__('erp.resources.it_asset.plural'))
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('manufacturer')
                    ->label(__('erp.resources.it_manufacturer.singular'))
                    ->relationship('manufacturer', 'name')
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageItModels::route('/'),
        ];
    }
}
