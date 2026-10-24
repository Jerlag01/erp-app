<?php

namespace App\Filament\Resources\WasteEntries;

use App\Enums\NavigationGroup;
use App\Enums\WasteRegion;
use App\Filament\Resources\WasteEntries\Pages\ManageWasteEntries;
use App\Models\Project;
use App\Models\WasteCategory;
use App\Models\WasteEntry;
use App\Models\WasteEntryLine;
use App\Models\WasteProcessor;
use App\Models\WorkSite;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
                // Battery waste weighs the sum of its lines (below).
                TextInput::make('weight_kg')
                    ->label(__('erp.waste.weight'))
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(9999999)
                    ->step(0.01)
                    ->suffix('kg')
                    ->required(fn (Get $get): bool => ! $batteries($get))
                    ->visible(fn (Get $get): bool => ! $batteries($get))
                    ->dehydrated(fn (Get $get): bool => ! $batteries($get)),
                Text::make(fn (Get $get): string => __('erp.waste.lines_total', ['weight' => WasteEntry::formatKg(collect((array) $get('lines'))->sum(fn ($line): float => (float) str_replace(',', '.', (string) ($line['weight_kg'] ?? 0))))]))
                    ->visible($batteries),
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
                    ->description(__('erp.waste.lines_help'))
                    ->icon(Heroicon::OutlinedBattery50)
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible($batteries)
                    ->schema([
                        Repeater::make('lines')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort')
                            ->schema([
                                ToggleButtons::make('region')
                                    ->label(__('erp.waste.region'))
                                    ->options(WasteRegion::class)
                                    ->inline()
                                    ->required()
                                    ->columnSpanFull(),
                                TextInput::make('destruction_certificate')
                                    ->label(__('erp.waste.destruction_certificate'))
                                    ->helperText(__('erp.waste.destruction_certificate_help'))
                                    ->placeholder('001')
                                    ->required()
                                    ->length(3)
                                    ->regex(WasteEntry::CERTIFICATE_PATTERN)
                                    ->extraInputAttributes(['inputmode' => 'numeric', 'style' => 'font-family: var(--erp-mono);']),
                                TextInput::make('weight_kg')
                                    ->label(__('erp.waste.weight'))
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->maxValue(9999999)
                                    ->step(0.01)
                                    ->suffix('kg')
                                    ->required()
                                    ->live(onBlur: true),
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
                                TextInput::make('po_number')
                                    ->label(__('erp.waste.po_number'))
                                    ->maxLength(30)
                                    ->extraInputAttributes(['style' => 'font-family: var(--erp-mono);']),
                                // A project of the platform (suggested while typing) or any other reference.
                                TextInput::make('project_reference')
                                    ->label(__('erp.waste.project_reference'))
                                    ->helperText(__('erp.waste.project_reference_help'))
                                    ->datalist(fn (): array => self::projectReferences())
                                    ->maxLength(60)
                                    ->extraInputAttributes(['style' => 'font-family: var(--erp-mono);']),
                                FileUpload::make('pdf_path')
                                    ->label(__('erp.waste.pdf'))
                                    ->helperText(__('erp.waste.pdf_help'))
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->disk(WasteEntryLine::DISK)
                                    ->directory(fn (): string => 'waste/'.now()->format('Y'))
                                    ->visibility('private')
                                    ->storeFileNamesIn('pdf_name')
                                    ->maxSize(51200)
                                    ->downloadable()
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->required($batteries)
                            ->live()
                            ->itemLabel(fn (array $state): ?string => filled($state['destruction_certificate'] ?? null) ? __('erp.waste.certificate_label', ['number' => $state['destruction_certificate']]) : null)
                            ->addActionLabel(__('erp.waste.add_line'))
                            ->columnSpanFull(),
                    ]),
                Textarea::make('notes')
                    ->label(__('erp.fields.notes'))
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Project references of the platform, newest first, suggested while typing.
     *
     * @return list<string>
     */
    public static function projectReferences(): array
    {
        return Project::query()->latest('id')->limit(1000)->pluck('reference')->filter()->values()->all();
    }

    /**
     * Whether the table shows one of the regional battery lists.
     */
    public static function onRegionTab(Component $livewire): bool
    {
        return self::tabRegion($livewire) !== null;
    }

    public static function tabRegion(Component $livewire): ?WasteRegion
    {
        return WasteRegion::tryFrom((string) ($livewire->activeTab ?? ''));
    }

    /**
     * The years that have entries, newest first (and at least the current year).
     *
     * @return array<int, string>
     */
    public static function yearOptions(): array
    {
        $first = WasteEntry::query()->min('date');
        $last = WasteEntry::query()->max('date');
        $from = $first ? (int) substr((string) $first, 0, 4) : (int) now()->year;
        $until = max((int) now()->year, $last ? (int) substr((string) $last, 0, 4) : 0);

        return collect(range($until, min($from, $until)))->mapWithKeys(fn (int $year): array => [$year => (string) $year])->all();
    }

    public static function table(Table $table): Table
    {
        $fullList = fn (Component $livewire): bool => ! self::onRegionTab($livewire);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category.parent', 'processor', 'lines']))
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
                // On a regional list only the lines of that region count.
                TextColumn::make('weight_kg')
                    ->label(__('erp.waste.weight'))
                    ->state(fn (WasteEntry $record, Component $livewire): float => ($region = self::tabRegion($livewire))
                        ? (float) $record->lines->where('region', $region)->sum('weight_kg')
                        : (float) $record->weight_kg)
                    ->formatStateUsing(fn ($state): string => WasteEntry::formatKg($state))
                    ->fontFamily(FontFamily::Mono)
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Summarizer::make()
                        ->label(__('erp.waste.total'))
                        ->using(fn (QueryBuilder $query, Component $livewire): float => ($region = self::tabRegion($livewire))
                            ? (float) WasteEntryLine::query()->whereIn('waste_entry_id', (clone $query)->select('waste_entries.id'))->where('region', $region->value)->sum('weight_kg')
                            : (float) (clone $query)->sum('weight_kg'))
                        ->formatStateUsing(fn ($state): string => WasteEntry::formatKg($state))),
                TextColumn::make('processor.name')
                    ->label(__('erp.resources.waste_processor.singular'))
                    ->description(fn (WasteEntry $record): ?string => $record->processor_reference ? __('erp.waste.ref', ['reference' => $record->processor_reference]) : null)
                    ->searchable(['processor_reference'])
                    ->visible($fullList),
                TextColumn::make('regions')
                    ->label(__('erp.waste.region'))
                    ->state(fn (WasteEntry $record): array => $record->lineValues('region'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->visible($fullList)
                    ->toggleable(),
                TextColumn::make('certificates')
                    ->label(__('erp.waste.destruction_certificate_short'))
                    ->state(fn (WasteEntry $record, Component $livewire): array => $record->lineValues('destruction_certificate', self::tabRegion($livewire)))
                    ->listWithLineBreaks()
                    ->fontFamily(FontFamily::Mono)
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhereHas('lines', fn (Builder $query) => $query->where('destruction_certificate', 'like', '%'.addcslashes($search, '%_\\').'%')))
                    ->placeholder('—'),
                TextColumn::make('cow_codes')
                    ->label(__('erp.fields.cow_code'))
                    ->state(fn (WasteEntry $record, Component $livewire): array => $record->lineValues('cow_code', self::tabRegion($livewire)))
                    ->listWithLineBreaks()
                    ->fontFamily(FontFamily::Mono)
                    ->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhereHas('lines', fn (Builder $query) => $query->where('cow_code', 'like', '%'.addcslashes($search, '%_\\').'%')))
                    ->placeholder('—'),
                TextColumn::make('po_numbers')
                    ->label(__('erp.waste.po_number'))
                    ->state(fn (WasteEntry $record, Component $livewire): array => $record->lineValues('po_number', self::tabRegion($livewire)))
                    ->listWithLineBreaks()
                    ->fontFamily(FontFamily::Mono)
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhereHas('lines', fn (Builder $query) => $query->where('po_number', 'like', '%'.addcslashes($search, '%_\\').'%')))
                    ->placeholder('—'),
                TextColumn::make('projects')
                    ->label(__('erp.waste.project_reference'))
                    ->state(fn (WasteEntry $record): array => $record->lineValues('project_reference'))
                    ->listWithLineBreaks()
                    ->fontFamily(FontFamily::Mono)
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhereHas('lines', fn (Builder $query) => $query->where('project_reference', 'like', '%'.addcslashes($search, '%_\\').'%')))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('pdfs')
                    ->label(__('erp.waste.pdf_short'))
                    ->state(fn (WasteEntry $record): ?string => ($count = $record->lines->whereNotNull('pdf_path')->count()) > 0 ? $count.' / '.$record->lines->count() : null)
                    ->badge()
                    ->color(fn (WasteEntry $record): string => $record->lines->whereNull('pdf_path')->isEmpty() ? 'success' : 'warning')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label(__('erp.waste.year'))
                    ->options(fn (): array => self::yearOptions())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $year) => $query->whereYear('date', (int) $year))),
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
