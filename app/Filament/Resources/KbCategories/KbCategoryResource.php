<?php

namespace App\Filament\Resources\KbCategories;

use App\Enums\NavigationGroup;
use App\Filament\Resources\KbCategories\Pages\ManageKbCategories;
use App\Filament\Support\TranslatableField;
use App\Models\KbCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class KbCategoryResource extends Resource
{
    protected static ?string $model = KbCategory::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'knowledge-base-categories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::KnowledgeBase;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('erp.resources.kb_category.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.kb_category.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TranslatableField::make('name', __('erp.fields.name'), fn (string $path) => TextInput::make($path)->maxLength(150)),
                    TranslatableField::make('description', __('erp.fields.description'), fn (string $path) => Textarea::make($path)->rows(3)->maxLength(1000), required: false),
                    Toggle::make('is_visible')
                        ->label(__('erp.kb.is_visible'))
                        ->helperText(__('erp.kb.is_visible_help'))
                        ->default(true),
                    TextInput::make('sort_order')
                        ->label(__('erp.kb.sort_order'))
                        ->integer()
                        ->minValue(0)
                        ->default(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->withCount('articles'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->state(fn (KbCategory $record): string => $record->translate('name'))
                    ->description(fn (KbCategory $record): ?string => $record->translate('description') ?: null),
                TextColumn::make('articles_count')
                    ->label(__('erp.resources.kb_article.plural'))
                    ->badge()
                    ->color('gray'),
                ToggleColumn::make('is_visible')
                    ->label(__('erp.kb.is_visible')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription(__('erp.kb.delete_category_help')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKbCategories::route('/'),
        ];
    }
}
