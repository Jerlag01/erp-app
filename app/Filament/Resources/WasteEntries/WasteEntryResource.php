<?php

namespace App\Filament\Resources\WasteEntries;

use App\Enums\NavigationGroup;
use App\Enums\WasteRegion;
use App\Filament\Resources\WasteEntries\Pages\ManageWasteEntries;
use App\Models\WasteCategory;
use App\Models\WasteEntry;
use App\Models\WasteProcessor;
use App\Models\WorkSite;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use UnitEnum;

/**
 * The waste registry. The list page has four lists: the full registry and the battery waste
 * per region (Flanders, Brussels, Wallonia), each exportable to PDF.
 */
class WasteEntryResource extends Resource
{
    protected static ?string $model = WasteEntry::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'waste-registry';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Waste;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('erp.resources.waste_entry.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.waste_entry.plural');
    }

    public static function isBatteries(mixed $categoryId): bool
    {
        return filled($categoryId) && (bool) WasteCategory::query()->with('parent')->find($categoryId)?->isBatteries();
    }

    public static function form(Schema $schema): Schema
    {
        $batteries = fn (Get $get): bool => self::isBatteries($get('waste_category_id'));

        return $schema
            ->columns(2)
            ->components([
                DatePicker::make('date')
                    ->label(__('erp.fields.date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(today())
                    ->maxDate(today()->addDay())
                    ->required(),
                TextInput::make('weight_kg')
                    ->label(__('erp.waste.weight'))
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(9999999)
                    ->step(0.01)
                    ->suffix('kg')
                    ->required(),
                Select::make('waste_category_id')
                    ->label(__('erp.waste.type'))
                    ->options(fn (?WasteEntry $record): array => WasteCategory::selectOptions($record?->waste_category_id))
                    ->searchable()
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                Select::make('waste_processor_id')
                    ->label(__('erp.resources.waste_processor.singular'))
                    ->options(fn (?WasteEntry $record): array => WasteProcessor::query()
                        ->where(fn (Builder $query) => $query->where('is_active', true)->when($record, fn (Builder $query) => $query->orWhere('id', $record->waste_processor_id)))
                        ->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                TextInput::make('processor_reference')
                    ->label(__('erp.waste.processor_reference'))
                    ->helperText(__('erp.waste.processor_reference_help'))
                    ->maxLength(100),
                Section::make(__('erp.waste.batteries'))
                    ->description(__('erp.waste.batteries_help'))
                    ->icon(Heroicon::OutlinedBattery50)
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible($batteries)
                    ->schema([
                        ToggleButtons::make('region')
                            ->label(__('erp.waste.region'))
                            ->options(WasteRegion::class)
                            ->inline()
                            ->required($batteries)
                            ->columnSpanFull(),
                        TextInput::make('destruction_certificate')
                            ->label(__('erp.waste.destruction_certificate'))
                            ->helperText(__('erp.waste.destruction_certificate_help'))
                            ->placeholder('001')
                            ->required($batteries)
                            ->length(3)
                            ->regex(WasteEntry::CERTIFICATE_PATTERN)
                            ->extraInputAttributes(['inputmode' => 'numeric', 'style' => 'font-family: var(--erp-mono);']),
                        TextInput::make('po_number')
                            ->label(__('erp.waste.po_number'))
                            ->maxLength(30)
                            ->extraInputAttributes(['style' => 'font-family: var(--erp-mono);']),
                        Select::make('work_site_id')
                            ->label(__('erp.fields.cow_code'))
                            ->options(fn (): array => WorkSite::query()->orderBy('cow_code')->get()->mapWithKeys(fn (WorkSite $site): array => [$site->id => trim($site->cow_code.' · '.$site->city, ' ·')])->all())
                            ->searchable()
                            ->visible(fn (): bool => modules()->workSites()),
                        // Without the work sites module the COW code is typed in.
                        TextInput::make('cow_code')
                            ->label(__('erp.fields.cow_code'))
                            ->placeholder('02GAM')
                            ->maxLength(5)
                            ->regex(WorkSite::COW_CODE_PATTERN)
                            ->visible(fn (): bool => ! modules()->workSites())
                            ->extraInputAttributes(['style' => 'text-transform: uppercase; font-family: var(--erp-mono);']),
                    ]),
                Textarea::make('notes')
                    ->label(__('erp.fields.notes'))
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Whether the table shows one of the regional battery lists.
     */
    public static function onRegionTab(Component $livewire): bool
    {
        return WasteRegion::tryFrom((string) ($livewire->activeTab ?? '')) !== null;
    }

    public static function table(Table $table): Table
    {
        $fullList = fn (Component $livewire): bool => ! self::onRegionTab($livewire);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category.parent', 'processor']))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label(__('erp.fields.date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label(__('erp.waste.type'))
                    ->state(fn (WasteEntry $record): string => $record->category->name)
                    ->description(fn (WasteEntry $record): string => trim(($record->category->waste_code ?? '').($record->category->parent ? ' · '.$record->category->parent->name : ''), ' ·'))
                    ->wrap()
                    ->visible($fullList),
                TextColumn::make('weight_kg')
                    ->label(__('erp.waste.weight'))
                    ->formatStateUsing(fn (string $state): string => WasteEntry::formatKg($state))
                    ->fontFamily(FontFamily::Mono)
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('erp.waste.total'))->formatStateUsing(fn ($state): string => WasteEntry::formatKg($state))),
                TextColumn::make('processor.name')
                    ->label(__('erp.resources.waste_processor.singular'))
                    ->description(fn (WasteEntry $record): ?string => $record->processor_reference ? __('erp.waste.ref', ['reference' => $record->processor_reference]) : null)
                    ->searchable(['processor_reference'])
                    ->visible($fullList),
                TextColumn::make('region')
                    ->label(__('erp.waste.region'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->visible($fullList)
                    ->toggleable(),
                TextColumn::make('destruction_certificate')
                    ->label(__('erp.waste.destruction_certificate_short'))
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('cow_code')
                    ->label(__('erp.fields.cow_code'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight('bold')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('po_number')
                    ->label(__('erp.waste.po_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->placeholder('—'),
            ])
            ->filters([
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('erp.incidents.from'))->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label(__('erp.incidents.until'))->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date)))
                    ->indicateUsing(fn (array $data): ?string => ($data['from'] ?? null) || ($data['until'] ?? null)
                        ? __('erp.waste.period_indicator', ['from' => $data['from'] ?? '…', 'until' => $data['until'] ?? '…'])
                        : null),
                SelectFilter::make('category')
                    ->label(__('erp.resources.waste_category.singular'))
                    ->options(fn (): array => WasteCategory::query()->whereNull('parent_id')->ordered()->pluck('name', 'id')->all())
                    // A main category also covers its subcategories.
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $id) => $query
                        ->whereIn('waste_category_id', WasteCategory::query()->where('id', $id)->orWhere('parent_id', $id)->pluck('id')))),
                SelectFilter::make('waste_processor_id')
                    ->label(__('erp.resources.waste_processor.singular'))
                    ->relationship('processor', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWasteEntries::route('/'),
        ];
    }
}
