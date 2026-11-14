<?php

namespace App\Filament\Resources\Offers\Schemas;

use App\Enums\OfferStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Offer;
use App\Models\OfferCategory;
use App\Models\OfferLine;
use App\Models\Project;
use App\Models\StandardOfferLine;
use App\Models\StockItem;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The form of an offer: client, project (its COW code, address and description fill in the
 * references), the lines per category with a supply and an installation price, default lines to add
 * per category, the hourly rate for the workdays and the remarks (exclusions).
 */
class OfferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            // Invoiced: read-only (the invoice is changed instead).
            ->disabled(fn (?Offer $record): bool => $record?->invoice_id !== null)
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        Select::make('client_id')
                            ->label(__('erp.resources.client.singular'))
                            ->options(fn (?Offer $record): array => Client::query()
                                ->where(fn (Builder $query) => $query->where('is_active', true)->when($record?->client_id, fn (Builder $query) => $query->orWhere('id', $record->client_id)))
                                ->orderBy('short_name')->get()->mapWithKeys(fn (Client $client): array => [$client->id => $client->label()])->all())
                            ->searchable()
                            ->required()
                            // The finance clients only while the finance module is on; else a name.
                            ->visible(fn (): bool => modules()->finance()),
                        TextInput::make('client_name')
                            ->label(__('erp.resources.client.singular'))
                            ->maxLength(255)
                            ->visible(fn (): bool => ! modules()->finance()),
                        Select::make('project_id')
                            ->label(__('erp.resources.project.singular'))
                            // The latest 500 projects, and the chosen one when it is older.
                            ->options(fn (Get $get): array => Project::query()->with('category')->latest()->limit(500)->get()
                                ->when(filled($get('project_id')), fn ($projects) => $projects->push(...Project::query()->with('category')->whereKey($get('project_id'))->get()))
                                ->unique('id')
                                ->mapWithKeys(fn (Project $project): array => [$project->id => trim($project->reference.' · '.$project->subtitle(), ' ·')])->all())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (mixed $state, Get $get, Set $set) => self::fillFromProject($state, $get, $set))
                            ->visible(fn (): bool => modules()->projects()),
                        TextInput::make('subject')
                            ->label(__('erp.finance.offers.subject'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('client_reference')
                            ->label(__('erp.finance.offers.client_reference'))
                            ->helperText(__('erp.finance.offers.client_reference_help'))
                            ->maxLength(255),
                        TextInput::make('our_reference')
                            ->label(__('erp.finance.offers.our_reference'))
                            ->placeholder('600/KI/091')
                            ->maxLength(255),
                        DatePicker::make('offer_date')
                            ->label(__('erp.finance.offers.date'))
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(today())
                            ->required(),
                        DatePicker::make('valid_until')
                            ->label(__('erp.finance.offers.valid_until'))
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('offer_date'),
                        Select::make('status')
                            ->label(__('erp.fields.status'))
                            ->options(OfferStatus::class)
                            ->default(OfferStatus::Draft->value)
                            ->required()
                            ->selectablePlaceholder(false),
                        Textarea::make('site_address')
                            ->label(__('erp.finance.offers.site_address'))
                            ->helperText(__('erp.finance.offers.site_address_help'))
                            ->rows(3)
                            ->maxLength(500),
                    ]),
                Section::make(__('erp.finance.invoices.totals'))
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        Text::make(fn (Get $get): string => self::totalsText((array) $get('lines'), $get('hourly_rate'), $get('hours_per_day'))),
                        TextInput::make('hourly_rate')
                            ->label(__('erp.finance.offers.hourly_rate'))
                            ->helperText(__('erp.finance.offers.hourly_rate_help'))
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->prefix('€')
                            ->live(debounce: 500)
                            ->required(),
                        TextInput::make('hours_per_day')
                            ->label(__('erp.finance.offers.hours_per_day'))
                            ->numeric()
                            ->step(0.25)
                            ->minValue(1)
                            ->maxValue(24)
                            ->live(debounce: 500)
                            ->required(),
                    ]),
                Actions::make([
                    Action::make('addStandardLines')
                        ->label(__('erp.finance.offers.add_standard'))
                        ->icon(Heroicon::OutlinedListBullet)
                        ->modalHeading(__('erp.finance.offers.add_standard'))
                        ->modalSubmitActionLabel(__('erp.finance.offers.add'))
                        ->schema([
                            Select::make('categories')
                                ->label(__('erp.finance.offers.whole_categories'))
                                ->helperText(__('erp.finance.offers.whole_categories_help'))
                                ->options(fn (): array => OfferCategory::options())
                                ->multiple(),
                            CheckboxList::make('lines')
                                ->label(__('erp.finance.offers.single_lines'))
                                ->options(fn (): array => self::standardLineOptions())
                                ->searchable()
                                ->bulkToggleable()
                                ->columns(1),
                        ])
                        ->action(fn (array $data, Get $get, Set $set) => self::addStandardLines($data, $get, $set)),
                    // Several warehouse items at once, in one category, with one profit factor.
                    Action::make('addStockItems')
                        ->label(__('erp.finance.offers.add_stock'))
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->color('gray')
                        ->visible(fn (): bool => modules()->stock())
                        ->modalHeading(__('erp.finance.offers.add_stock'))
                        ->modalSubmitActionLabel(__('erp.finance.offers.add'))
                        ->schema([
                            Select::make('offer_category_id')
                                ->label(__('erp.finance.offers.category'))
                                ->options(fn (): array => OfferCategory::options())
                                ->required(),
                            Select::make('stock_items')
                                ->label(__('erp.finance.offers.stock_items'))
                                ->multiple()
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => self::stockSearch($search))
                                ->getOptionLabelsUsing(fn (array $values): array => self::stockLabels($values))
                                ->required(),
                            TextInput::make('factor')
                                ->label(__('erp.finance.offers.factor'))
                                ->helperText(__('erp.finance.offers.factor_help'))
                                ->numeric()
                                ->step(0.01)
                                ->minValue(0.01)
                                ->default(fn (): float => OfferLine::defaultFactor())
                                ->required(),
                        ])
                        ->action(fn (array $data, Get $get, Set $set) => self::addStockItems($data, $get, $set)),
                ])->key('offer_actions')->columnSpanFull(),
                Repeater::make('lines')
                    ->label(__('erp.finance.offers.lines'))
                    ->relationship()
                    ->orderColumn('sort_order')
                    ->addActionLabel(__('erp.finance.invoices.add_line'))
                    ->columns(12)
                    ->reorderableWithButtons()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => filled($state['offer_category_id'] ?? null) ? (OfferCategory::query()->find($state['offer_category_id'])?->label()) : null)
                    ->live(debounce: 500)
                    ->schema([
                        Select::make('offer_category_id')
                            ->label(__('erp.finance.offers.category'))
                            ->options(fn (?OfferLine $record): array => OfferCategory::options(array_filter([$record?->offer_category_id])))
                            ->required()
                            ->columnSpan(['default' => 12, 'md' => 3]),
                        TextInput::make('description')
                            ->label(__('erp.finance.pdf.description'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(['default' => 12, 'md' => 9]),
                        // A warehouse item: its price as cost price, × the profit factor = supply price.
                        Select::make('stock_item_id')
                            ->label(__('erp.finance.offers.stock_item'))
                            ->placeholder(__('erp.finance.offers.stock_item_placeholder'))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => self::stockSearch($search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => self::stockLabels([$value])[$value] ?? null)
                            ->live()
                            ->afterStateUpdated(fn (mixed $state, Get $get, Set $set) => self::fillFromStockItem($state, $get, $set))
                            ->visible(fn (Get $get): bool => modules()->stock() || filled($get('stock_item_id')))
                            ->columnSpan(['default' => 12, 'md' => 6]),
                        TextInput::make('cost_price')
                            ->label(__('erp.finance.offers.cost_price'))
                            ->numeric()
                            ->step(0.01)
                            ->prefix('€')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => $set('supply_price', OfferLine::priceFromCost($get('cost_price'), $get('factor'))))
                            ->visible(fn (Get $get): bool => filled($get('stock_item_id')))
                            ->required(fn (Get $get): bool => filled($get('stock_item_id')))
                            ->columnSpan(['default' => 6, 'md' => 3]),
                        TextInput::make('factor')
                            ->label(__('erp.finance.offers.factor'))
                            ->helperText(__('erp.finance.offers.factor_help'))
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => $set('supply_price', OfferLine::priceFromCost($get('cost_price'), $get('factor'))))
                            ->visible(fn (Get $get): bool => filled($get('stock_item_id')))
                            ->required(fn (Get $get): bool => filled($get('stock_item_id')))
                            ->columnSpan(['default' => 6, 'md' => 3]),
                        TextInput::make('quantity')
                            ->label(__('erp.finance.pdf.quantity'))
                            ->helperText(__('erp.finance.offers.quantity_help'))
                            ->numeric()
                            ->step('any')
                            ->columnSpan(['default' => 4, 'md' => 2]),
                        TextInput::make('unit')
                            ->label(__('erp.finance.invoices.unit'))
                            ->placeholder('st/m')
                            ->maxLength(20)
                            ->columnSpan(['default' => 4, 'md' => 1]),
                        TextInput::make('supply_price')
                            ->label(__('erp.finance.offers.supply_price'))
                            ->helperText(fn (Get $get): ?string => filled($get('stock_item_id')) ? __('erp.finance.offers.supply_from_factor') : null)
                            ->numeric()
                            ->step(0.01)
                            ->prefix('€')
                            // A stock line: cost price × factor (also recomputed when saved).
                            ->disabled(fn (Get $get): bool => filled($get('stock_item_id')))
                            ->dehydrated()
                            ->columnSpan(['default' => 6, 'md' => 2]),
                        TextInput::make('install_price')
                            ->label(__('erp.finance.offers.install_price'))
                            ->numeric()
                            ->step(0.01)
                            ->prefix('€')
                            ->columnSpan(['default' => 6, 'md' => 2]),
                        Text::make(fn (Get $get): string => __('erp.finance.offers.line_totals', [
                            'supply' => Invoice::money(OfferLine::totalFor($get('quantity'), $get('supply_price'))),
                            'install' => Invoice::money(OfferLine::totalFor($get('quantity'), $get('install_price'))),
                        ]))
                            ->columnSpan(['default' => 12, 'md' => 5]),
                    ])
                    ->columnSpanFull(),
                Textarea::make('remarks')
                    ->label(__('erp.finance.offers.remarks'))
                    ->helperText(__('erp.finance.offers.remarks_help'))
                    ->rows(8)
                    ->maxLength(5000)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Another project: its description (Betreft), COW code and city (Uw ref.), reference (Onze ref.)
     * and site address; each can be changed afterwards.
     */
    protected static function fillFromProject(mixed $projectId, Get $get, Set $set): void
    {
        $project = filled($projectId) ? Project::query()->with(['workSite', 'category'])->find($projectId) : null;

        if ($project === null) {
            return;
        }

        $defaults = Offer::defaults($project);

        foreach (['subject', 'client_reference', 'our_reference', 'site_address'] as $field) {
            $set($field, $defaults[$field]);
        }
    }

    /**
     * Active warehouse items by name or reference (manufacturer, distributor, EAN), with their price.
     *
     * @return array<int, string>
     */
    public static function stockSearch(string $search): array
    {
        $like = '%'.addcslashes($search, '%_\\').'%';

        return StockItem::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('name_nl', 'like', $like)->orWhere('name_fr', 'like', $like)
                ->orWhere('manufacturer_reference', 'like', $like)->orWhere('distributor_reference', 'like', $like)->orWhere('ean', 'like', $like))
            ->orderBy('name')
            ->limit(50)
            ->pluck('id')
            ->pipe(fn ($ids): array => self::stockLabels($ids->all()));
    }

    /**
     * "PDU 9x(2P+A)16A · 50578 — € 53,96".
     *
     * @param  array<int|string>  $ids
     * @return array<int, string>
     */
    public static function stockLabels(array $ids): array
    {
        return StockItem::query()->whereKey(array_filter($ids))->orderBy('name')->get()
            ->mapWithKeys(fn (StockItem $item): array => [$item->id => trim($item->localizedName().($item->manufacturer_reference ? ' · '.$item->manufacturer_reference : ''))
                .' — '.($item->market_price !== null ? Invoice::money($item->market_price) : __('erp.finance.offers.no_price'))])
            ->all();
    }

    /**
     * A warehouse item chosen on a line: its name, unit and price (cost price), the default profit factor
     * when the line has none yet, and the supply price from both.
     */
    protected static function fillFromStockItem(mixed $stockItemId, Get $get, Set $set): void
    {
        $item = filled($stockItemId) ? StockItem::query()->with('unit')->find($stockItemId) : null;

        if ($item === null) {
            $set('cost_price', null);
            $set('factor', null);

            return;
        }

        $factor = filled($get('factor')) ? (float) $get('factor') : OfferLine::defaultFactor();
        $set('description', mb_substr($item->localizedName(), 0, 255));
        $set('unit', $item->unit?->abbreviation ?: $get('unit'));
        $set('cost_price', $item->market_price !== null ? (float) $item->market_price : null);
        $set('factor', $factor);
        $set('supply_price', OfferLine::priceFromCost($item->market_price, $factor));
        $set('quantity', filled($get('quantity')) ? $get('quantity') : 1);
    }

    /**
     * @param  array{offer_category_id: int|string, stock_items: array<int|string>, factor: float|string}  $data
     */
    protected static function addStockItems(array $data, Get $get, Set $set): void
    {
        $state = (array) $get('lines');

        foreach (StockItem::query()->with('unit')->whereKey(array_map('intval', (array) $data['stock_items']))->orderBy('name')->get() as $item) {
            $state[(string) Str::uuid()] = [
                'offer_category_id' => (int) $data['offer_category_id'],
                'stock_item_id' => $item->id,
                'description' => mb_substr($item->localizedName(), 0, 255),
                'quantity' => 1,
                'unit' => $item->unit?->abbreviation,
                'cost_price' => $item->market_price !== null ? (float) $item->market_price : null,
                'factor' => (float) $data['factor'],
                'supply_price' => OfferLine::priceFromCost($item->market_price, $data['factor']),
                'install_price' => null,
            ];
        }

        $set('lines', $state);
    }

    /**
     * "A · PDU 9x(2P+A)16A — € 53,96 / –" for every active default line, in the order of the categories.
     *
     * @return array<int, string>
     */
    public static function standardLineOptions(): array
    {
        return StandardOfferLine::query()
            ->with('category')
            ->where('is_active', true)
            ->whereHas('category', fn (Builder $query) => $query->where('is_active', true))
            ->get()
            ->sortBy(fn (StandardOfferLine $line): array => [$line->category->sort_order, $line->category->code, $line->sort_order, $line->id])
            ->mapWithKeys(fn (StandardOfferLine $line): array => [$line->id => $line->category->code.' · '.$line->description.' — '
                .($line->supply_price !== null ? Invoice::money($line->supply_price) : '–').' / '
                .($line->is_labour ? __('erp.finance.offers.hourly_rate') : ($line->install_price !== null ? Invoice::money($line->install_price) : '–'))])
            ->all();
    }

    /**
     * Adds the chosen default lines (and all lines of the chosen categories) to the offer, each once.
     *
     * @param  array{categories?: array<int|string>, lines?: array<int|string>}  $data
     */
    protected static function addStandardLines(array $data, Get $get, Set $set): void
    {
        $lines = StandardOfferLine::query()
            ->with('category')
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereIn('id', array_map('intval', (array) ($data['lines'] ?? [])))
                ->orWhereIn('offer_category_id', array_map('intval', (array) ($data['categories'] ?? []))))
            ->get()
            ->sortBy(fn (StandardOfferLine $line): array => [$line->category?->sort_order, $line->category?->code, $line->sort_order, $line->id]);

        $state = (array) $get('lines');
        $rate = (float) $get('hourly_rate');

        foreach ($lines as $line) {
            $state[(string) Str::uuid()] = $line->toOfferLine($rate);
        }

        $set('lines', $state);
    }

    /**
     * The totals as the offer shows them: supply and installation of the works, the general costs,
     * the total, and the workdays per crew size.
     *
     * @param  array<array-key, array<string, mixed>>  $lines
     */
    public static function totalsText(array $lines, mixed $hourlyRate, mixed $hoursPerDay): string
    {
        $general = OfferCategory::query()->where('is_general_costs', true)->pluck('id')->map(fn ($id): string => (string) $id)->all();
        $supply = $install = $generalTotal = 0.0;

        foreach ($lines as $line) {
            $lineSupply = OfferLine::totalFor($line['quantity'] ?? 0, $line['supply_price'] ?? 0);
            $lineInstall = OfferLine::totalFor($line['quantity'] ?? 0, $line['install_price'] ?? 0);

            if (in_array((string) ($line['offer_category_id'] ?? ''), $general, true)) {
                $generalTotal += $lineSupply + $lineInstall;
            } else {
                $supply += $lineSupply;
                $install += $lineInstall;
            }
        }

        $hours = (float) $hourlyRate > 0 ? $install / (float) $hourlyRate : 0;
        $days = collect(Offer::crewSizes())
            ->map(fn (int $crew): string => __('erp.finance.offers.days_with', ['days' => number_format($hours / (((float) $hoursPerDay ?: 8) * $crew), 2, ',', '.'), 'crew' => $crew]))
            ->implode(' · ');

        return __('erp.finance.offers.totals_text', [
            'supply' => Invoice::money($supply),
            'install' => Invoice::money($install),
            'general' => Invoice::money($generalTotal),
            'total' => Invoice::money($supply + $install + $generalTotal),
            'hours' => number_format($hours, 2, ',', '.'),
            'days' => $days,
        ]);
    }
}
