<?php

namespace App\Filament\Resources\ItCategories;

use App\Enums\ItCategoryType;
use App\Enums\NavigationGroup;
use App\Filament\Resources\ItCategories\Pages\ManageItCategories;
use App\Models\ItCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class ItCategoryResource extends Resource
{
    protected static ?string $model = ItCategory::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'it-categories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::It;

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('erp.resources.it_category.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.it_category.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label(__('erp.fields.type'))
                    ->options(ItCategoryType::class)
                    ->default(ItCategoryType::Asset)
                    ->required(),
                TextInput::make('name')
                    ->label(__('erp.fields.name'))
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('type', $get('type') instanceof ItCategoryType ? $get('type')->value : $get('type'))),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultGroup(Group::make('type')->label(__('erp.fields.type'))->getTitleFromRecordUsing(fn (ItCategory $record): string => $record->type->getLabel()))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('erp.fields.type'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('erp.fields.type'))
                    ->options(ItCategoryType::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageItCategories::route('/'),
        ];
    }
}
