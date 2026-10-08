<?php

namespace App\Filament\Admin\Resources\AssetCategories;

use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\AssetCategories\Pages\ManageAssetCategories;
use App\Models\AssetCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AssetCategoryResource extends Resource
{
    protected static ?string $model = AssetCategory::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Assets;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('erp.resources.asset_category.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.asset_category.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('erp.fields.name'))
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                TextInput::make('inspection_interval_months')
                    ->label(__('erp.fields.inspection_interval'))
                    ->helperText(__('erp.help.inspection_interval'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(120)
                    ->suffix(__('erp.fields.months')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('assets'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('inspection_interval_months')
                    ->label(__('erp.fields.inspection_interval'))
                    ->suffix(' '.__('erp.fields.months'))
                    ->placeholder('—'),
                TextColumn::make('assets_count')
                    ->label(__('erp.resources.asset.plural'))
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
            'index' => ManageAssetCategories::route('/'),
        ];
    }
}
