<?php

namespace App\Filament\Resources\WasteCategories;

use App\Enums\NavigationGroup;
use App\Filament\Resources\WasteCategories\Pages\ManageWasteCategories;
use App\Models\WasteCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The waste categories and subcategories with their EURAL waste code.
 */
class WasteCategoryResource extends Resource
{
    protected static ?string $model = WasteCategory::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'waste-categories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Waste;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('erp.resources.waste_category.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.waste_category.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('parent_id')
                    ->label(__('erp.waste.parent_category'))
                    ->helperText(__('erp.waste.parent_category_help'))
                    ->options(fn (?WasteCategory $record): array => WasteCategory::query()->whereNull('parent_id')->when($record, fn (Builder $query) => $query->whereKeyNot($record->id))->ordered()->pluck('name', 'id')->all())
                    ->live()
                    // A category with subcategories stays a main category (one level deep).
                    ->disabled(fn (?WasteCategory $record): bool => (bool) $record?->children()->exists())
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->label(__('erp.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('waste_code')
                    ->label(__('erp.waste.waste_code'))
                    ->helperText(__('erp.waste.waste_code_help'))
                    ->placeholder('16 06 01*')
                    ->maxLength(20)
                    ->regex('/^\d{2} ?\d{2} ?\d{2}\*?$/')
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? self::normaliseCode($state) : null)
                    ->extraInputAttributes(['style' => 'font-family: var(--erp-mono);']),
                Toggle::make('is_batteries')
                    ->label(__('erp.waste.is_batteries'))
                    ->helperText(__('erp.waste.is_batteries_help'))
                    ->visible(fn (Get $get): bool => blank($get('parent_id'))),
                Toggle::make('is_active')
                    ->label(__('erp.fields.active'))
                    ->helperText(__('erp.waste.category_active_help'))
                    ->default(true),
                TextInput::make('sort_order')
                    ->label(__('erp.kb.sort_order'))
                    ->integer()
                    ->minValue(0)
                    ->default(0),
            ]);
    }

    /**
     * "160601*" → "16 06 01*".
     */
    public static function normaliseCode(string $code): string
    {
        $digits = preg_replace('/\D/', '', $code);

        return trim(chunk_split($digits, 2, ' ')).(str_contains($code, '*') ? '*' : '');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('parent')->withCount('entries')
                // Main categories first, each followed by its subcategories.
                ->orderByRaw('COALESCE((SELECT p.sort_order FROM waste_categories p WHERE p.id = waste_categories.parent_id), waste_categories.sort_order)')
                ->orderByRaw('COALESCE(parent_id, id)')
                ->orderByRaw('parent_id IS NOT NULL')
                ->orderBy('sort_order')
                ->orderBy('name'))
            ->paginated([50, 100, 'all'])
            ->defaultPaginationPageOption(100)
            ->columns([
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->formatStateUsing(fn (WasteCategory $record): string => $record->parent_id ? '↳ '.$record->name : $record->name)
                    ->weight(fn (WasteCategory $record): ?string => $record->parent_id ? null : 'bold')
                    ->searchable(),
                TextColumn::make('waste_code')
                    ->label(__('erp.waste.waste_code'))
                    ->fontFamily(FontFamily::Mono)
                    ->color(fn (WasteCategory $record): ?string => $record->isHazardous() ? 'danger' : null)
                    ->placeholder('—')
                    ->searchable(),
                IconColumn::make('is_batteries')
                    ->label(__('erp.waste.batteries'))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedBattery50)
                    ->falseIcon(Heroicon::OutlinedMinus),
                TextColumn::make('entries_count')
                    ->label(__('erp.waste.entries'))
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label(__('erp.fields.active'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('erp.fields.active')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWasteCategories::route('/'),
        ];
    }
}
