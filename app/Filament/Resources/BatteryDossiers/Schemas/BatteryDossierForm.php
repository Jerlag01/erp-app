<?php

namespace App\Filament\Resources\BatteryDossiers\Schemas;

use App\Enums\BatteryDeliveryMode;
use App\Enums\BatteryInvoiceKind;
use App\Enums\BatteryMounting;
use App\Enums\BatteryTechnology;
use App\Filament\Forms\FloorPlanEditor;
use App\Filament\Forms\SignaturePad;
use App\Filament\Resources\BatteryDossiers\Pages\EditBatteryDossier;
use App\Models\BatteryCable;
use App\Models\BatteryDossier;
use App\Models\BatteryDossierBattery;
use App\Models\BatteryModel;
use App\Models\Project;
use App\Support\BatteryInspection;
use App\Support\BatterySingleLine;
use App\Support\PdfAccess;
use App\Support\PrivatePhotos;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/**
 * The dossier form, one tab per Excel sheet: Input, Leveringsnota, Meetrapport, Visueel
 * keuringsverslag, Plattegrond, Eendraadschema, Verzendnota and Factuuraanvraag. Per new battery the
 * form keeps a row under "strings.{position}"; it grows and shrinks with the number of batteries.
 */
class BatteryDossierForm
{
    /**
     * The dossier class of the form: its permissions, documents and project category (EnersysDossierForm
     * uses the same helpers for the Enersys dossiers).
     *
     * @var class-string<BatteryDossier>
     */
    protected static string $model = BatteryDossier::class;

    /**
     * @return array<string, array<string, string>>
     */
    protected static function inspectionSections(): array
    {
        return BatteryInspection::sections();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    // The Input tab needs "batteries.input"; the others see an overview of the project instead.
                    Tab::make(__('erp.batteries.tabs.input'))->icon(Heroicon::OutlinedPencilSquare)
                        ->visible(fn (): bool => Gate::allows('viewInput', static::$model))
                        ->schema(self::inputTab()),
                    Tab::make(__('erp.batteries.tabs.overview'))->icon(Heroicon::OutlinedInformationCircle)->visibleOn('edit')
                        ->visible(fn (): bool => ! Gate::allows('viewInput', static::$model))
                        ->schema(self::overviewTab()),
                    Tab::make(__('erp.batteries.tabs.delivery'))->icon(Heroicon::OutlinedTruck)->visibleOn('edit')->schema(self::deliveryTab()),
                    Tab::make(__('erp.batteries.tabs.measurement'))->icon(Heroicon::OutlinedBolt)->visibleOn('edit')->schema(self::measurementTab()),
                    Tab::make(__('erp.batteries.tabs.inspection'))->icon(Heroicon::OutlinedClipboardDocumentCheck)->visibleOn('edit')->schema(self::inspectionTab()),
                    Tab::make(__('erp.batteries.tabs.floor_plan'))->icon(Heroicon::OutlinedMap)->visibleOn('edit')->schema(self::floorPlanTab()),
                    Tab::make(__('erp.batteries.tabs.single_line'))->icon(Heroicon::OutlinedShare)->visibleOn('edit')->schema(self::singleLineTab()),
                    Tab::make(__('erp.batteries.tabs.shipping'))->icon(Heroicon::OutlinedArchiveBox)->visibleOn('edit')->schema(self::shippingTab()),
                    Tab::make(__('erp.batteries.tabs.invoice_request'))->icon(Heroicon::OutlinedCurrencyEuro)->visibleOn('edit')
                        ->visible(fn (?BatteryDossier $record): bool => $record !== null && Gate::allows('viewPrices', $record))
                        ->schema(self::invoiceTab()),
                    Tab::make(__('erp.batteries.tabs.photos'))->icon(Heroicon::OutlinedCamera)->visibleOn('edit')
                        ->badge(fn (?BatteryDossier $record): ?string => count((array) $record?->photos) ?: null)
                        ->schema(self::photosTab()),
                ]),
        ]);
    }

    /**
     * Positions of the new batteries in the form: 1 … number of batteries.
     *
     * @return list<int>
     */
    public static function positions(Get $get): array
    {
        return range(1, max(1, min(BatteryDossier::MAX_BATTERIES, (int) $get('new_count'))));
    }

    /**
     * A "Download PDF" button in a tab.
     */
    protected static function download(string $document): Actions
    {
        return Actions::make([
            Action::make('download_'.$document)
                ->label(__('erp.batteries.download', ['document' => __('erp.batteries.documents.'.$document)]))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->tooltip(__('erp.batteries.save_first'))
                ->visible(fn (): bool => PdfAccess::canGenerate(auth()->user(), static::$model::AREA))
                ->url(fn (?BatteryDossier $record): ?string => $record ? route(static::$model::documentRoute(), [$record, $document]) : null, shouldOpenInNewTab: true),
        ]);
    }

    /**
     * The fields of a document that has a written signature: locked once signed (except for administrators).
     *
     * @param  array<Component>  $components
     */
    protected static function lockable(string $document, array $components): Group
    {
        return Group::make($components)
            ->disabled(fn (?BatteryDossier $record): bool => (bool) $record?->isLockedFor($document, auth()->user()));
    }

    /**
     * "Signature": draw it to sign the document (the dossier is saved first), after which it is locked.
     * Administrators can remove a signature again.
     */
    protected static function signatureSection(string $document): Section
    {
        return Section::make(__('erp.batteries.signature.title'))
            ->icon(Heroicon::OutlinedPencil)
            ->compact()
            ->description(fn (?BatteryDossier $record): string => $record?->isSigned($document)
                ? ($record->isLockedFor($document, auth()->user()) ? __('erp.batteries.signature.locked') : __('erp.batteries.signature.admin_unlocked'))
                : __('erp.batteries.signature.help'))
            ->schema([
                Text::make(function (?BatteryDossier $record) use ($document): ?HtmlString {
                    $signature = $record?->signature($document);

                    if (! $signature) {
                        return null;
                    }

                    return new HtmlString(sprintf(
                        '<div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;"><img src="%s" alt="" style="max-height:90px;background:#fff;border:1px solid rgb(203 213 225);border-radius:.375rem;padding:.25rem;"><div><strong>%s</strong><br><span style="opacity:.75;">%s</span></div></div>',
                        e((string) $signature->dataUri()),
                        e($signature->signer_name),
                        e(__('erp.batteries.signature.signed_at', ['date' => $signature->signed_at->format('d/m/Y H:i'), 'user' => $signature->signer?->name ?? '—'])),
                    ));
                })->visible(fn (?BatteryDossier $record): bool => (bool) $record?->isSigned($document)),
                Actions::make([
                    Action::make('sign_'.$document)
                        ->label(__('erp.batteries.signature.sign'))
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->color('primary')
                        ->visible(fn (?BatteryDossier $record): bool => $record !== null && ! $record->isSigned($document) && Gate::allows('update', $record) && $record->isEditableBy(auth()->user()))
                        ->modalHeading(__('erp.batteries.signature.sign_heading', ['document' => __('erp.batteries.documents.'.$document)]))
                        ->modalDescription(__('erp.batteries.signature.sign_help'))
                        ->schema([
                            TextInput::make('signer_name')
                                ->label(__('erp.batteries.signature.name'))
                                ->default(fn (): ?string => auth()->user()?->name)
                                ->required()
                                ->maxLength(150),
                            SignaturePad::make('signature')->label(__('erp.batteries.signature.title'))->required(),
                        ])
                        ->action(function (array $data, BatteryDossier $record, EditBatteryDossier $livewire) use ($document): void {
                            abort_unless(Gate::allows('update', $record) && $record->isEditableBy(auth()->user()), 403);

                            // Sign what is on screen: save the dossier first.
                            $livewire->save(shouldRedirect: false, shouldSendSavedNotification: false);
                            $record->sign($document, $data['signer_name'], $data['signature'], auth()->user());

                            Notification::make()->title(__('erp.batteries.signature.signed'))->success()->send();
                        }),
                    Action::make('unsign_'.$document)
                        ->label(__('erp.batteries.signature.remove'))
                        ->icon(Heroicon::OutlinedLockOpen)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(__('erp.batteries.signature.remove_help'))
                        ->visible(fn (?BatteryDossier $record): bool => (bool) $record?->isSigned($document) && (bool) auth()->user()?->isAdmin() && $record->isEditableBy(auth()->user()))
                        ->action(function (BatteryDossier $record) use ($document): void {
                            abort_unless((bool) auth()->user()?->isAdmin(), 403);
                            $record->signature($document)?->delete();
                            $record->unsetRelation('signatures');

                            Notification::make()->title(__('erp.batteries.signature.removed'))->success()->send();
                        }),
                ])->key('signature_actions_'.$document),
            ]);
    }

    /**
     * @return array<Component>
     */
    protected static function inputTab(): array
    {
        $prices = fn (?BatteryDossier $record): bool => Gate::allows('viewPrices', $record ?? static::$model);

        return [
            Section::make(__('erp.batteries.sections.dossier'))
                ->columns(4)
                ->schema([
                    Select::make('project_id')
                        ->label(__('erp.batteries.project'))
                        ->helperText(__('erp.batteries.project_help', ['category' => settings()->get(static::$model::CATEGORY_SETTING)]))
                        ->options(fn (?BatteryDossier $record): array => self::projectOptions($record))
                        ->searchable()
                        ->required()
                        ->live()
                        ->disabledOn('edit')
                        ->columnSpan(2),
                    TextInput::make('po_number')
                        ->label(__('erp.batteries.po_number'))
                        ->datalist(fn (Get $get): array => Project::query()->find($get('project_id'))?->poNumbers->pluck('number')->all() ?? [])
                        ->maxLength(20),
                    TextInput::make('contact_person')
                        ->label(__('erp.batteries.contact_person'))
                        ->datalist(fn (): array => array_values((array) settings()->get('batteries.contacts', [])))
                        ->maxLength(255),
                    DatePicker::make('order_date')->label(__('erp.batteries.order_date')),
                    DatePicker::make('execution_date')->label(__('erp.batteries.execution_date')),
                    TextInput::make('truck_plate')->label(__('erp.batteries.truck_plate'))->maxLength(50),
                    Select::make('waste_type')
                        ->label(__('erp.batteries.waste_type'))
                        ->options(fn (): array => collect((array) settings()->get('batteries.waste_types', []))->mapWithKeys(fn (string $type): array => [$type => $type])->all()),
                ]),
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                Section::make(__('erp.batteries.sections.old'))
                    ->description(__('erp.batteries.sections.old_help'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('old_capacity_ah')->label(__('erp.batteries.capacity'))->integer()->minValue(0)->suffix('Ah'),
                        Select::make('old_technology')->label(__('erp.batteries.technology'))->options(BatteryTechnology::class),
                        TextInput::make('old_count')->label(__('erp.batteries.count'))->integer()->minValue(0)->maxValue(BatteryDossier::MAX_BATTERIES)->default(1),
                        TextInput::make('old_room')->label(__('erp.batteries.room'))->maxLength(50),
                        Toggle::make('removal')->label(__('erp.batteries.removal'))->helperText(__('erp.batteries.removal_help'))->default(true)->columnSpanFull(),
                    ]),
                Section::make(__('erp.batteries.sections.new'))
                    ->description(__('erp.batteries.sections.new_help'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('new_capacity_ah')->label(__('erp.batteries.capacity'))->integer()->minValue(0)->suffix('Ah'),
                        Select::make('new_technology')->label(__('erp.batteries.technology'))->options(BatteryTechnology::class)->live(),
                        TextInput::make('new_count')
                            ->label(__('erp.batteries.new_count'))
                            ->helperText(__('erp.batteries.new_count_help'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(BatteryDossier::MAX_BATTERIES)
                            ->default(1)
                            ->required()
                            ->live(onBlur: true),
                        TextInput::make('new_room')->label(__('erp.batteries.room'))->maxLength(50),
                        Select::make('delivery_mode')->label(__('erp.batteries.delivery_mode'))->options(BatteryDeliveryMode::class)->default(BatteryDeliveryMode::Place->value)->required(),
                        TextInput::make('supplier_location')->label(__('erp.batteries.supplier_location'))->helperText(__('erp.batteries.supplier_location_help'))->maxLength(255),
                    ]),
            ]),
            Section::make(__('erp.batteries.sections.battery'))
                ->columns(4)
                ->schema([
                    TextInput::make('brand')->label(__('erp.batteries.brand'))->maxLength(50),
                    Select::make('battery_model_id')
                        ->label(__('erp.batteries.model'))
                        ->options(fn (): array => BatteryModel::query()->where('is_active', true)->orderBy('technology')->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->live(),
                    Select::make('mounting')
                        ->label(__('erp.batteries.mounting'))
                        ->options(BatteryMounting::class)
                        ->live()
                        ->helperText(function (Get $get) use ($prices): ?string {
                            $rack = BatteryModel::query()->with('racks')->find($get('battery_model_id'))?->rackFor(BatteryMounting::tryFrom((string) ($get('mounting') instanceof BatteryMounting ? $get('mounting')->value : $get('mounting'))));

                            return $rack ? __('erp.batteries.rack_type', ['code' => $rack->code ?? '—']).($prices(null) && $rack->price !== null ? ' · '.BatteryDossier::money((float) $rack->price).' / '.__('erp.batteries.cell') : '') : null;
                        }),
                    TextInput::make('cells_per_battery')->label(__('erp.batteries.cells_per_battery'))->integer()->minValue(1)->maxValue(60)->default(24)->required()->live(onBlur: true),
                    TextInput::make('volts_per_cell')
                        ->label(__('erp.batteries.volts_per_cell'))
                        ->numeric()
                        ->step('any')
                        ->default(2)
                        ->suffix('V')
                        ->live(onBlur: true)
                        ->helperText(fn (Get $get): string => __('erp.batteries.volts_total', ['volts' => rtrim(rtrim(number_format((float) $get('volts_per_cell') * (int) $get('cells_per_battery'), 2, ',', ''), '0'), ',')])),
                    TextInput::make('production_date')->label(__('erp.batteries.production_date'))->maxLength(30),
                    TextInput::make('first_id_number')->label(__('erp.batteries.first_id_number'))->helperText(__('erp.batteries.first_id_number_help'))->maxLength(30),
                ]),
            Section::make(__('erp.batteries.sections.ids'))
                ->visibleOn('edit')
                ->columns(['default' => 2, 'md' => 4])
                ->schema(fn (Get $get): array => array_map(
                    fn (int $position): TextInput => TextInput::make("strings.{$position}.id_number")->label('ID Batt '.$position)->maxLength(30),
                    self::positions($get),
                )),
            Section::make(__('erp.batteries.sections.prices'))
                ->visible($prices)
                ->columns(4)
                ->schema([
                    TextInput::make('battery_total_price')->label(__('erp.batteries.battery_total_price'))->numeric()->step('any')->prefix('€')->default(0)
                        ->helperText(fn (Get $get): ?string => (int) $get('new_count') * (int) $get('cells_per_battery') > 0
                            ? __('erp.batteries.price_per_cell', ['price' => BatteryDossier::money((float) $get('battery_total_price') / ((int) $get('new_count') * (int) $get('cells_per_battery')))])
                            : null)
                        ->live(onBlur: true),
                    TextInput::make('hourly_rate')->label(__('erp.batteries.hourly_rate'))->numeric()->step('any')->prefix('€')->default(fn (): float => (float) settings()->get('batteries.hourly_rate')),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    protected static function deliveryTab(): array
    {
        $number = fn (string $name, string $suffix): TextInput => TextInput::make($name)->label(__("erp.batteries.fields.{$name}"))->numeric()->minValue(0)->step('any')->default(0)->suffix($suffix);

        return [
            self::lockable('delivery', [
                Section::make(__('erp.batteries.sections.delivery'))
                    ->description(__('erp.batteries.sections.delivery_help'))
                    ->columns(4)
                    ->schema([
                        $number('vertical_install_m', 'm'),
                        $number('vertical_removal_m', 'm'),
                        $number('ppe_sets', 'st'),
                        $number('maintenance_sets', 'st'),
                        $number('load_plates', 'st'),
                        $number('man_hours', 'u'),
                        $number('picto_plates', 'st'),
                        $number('doc_holders', 'st'),
                    ]),
                Section::make(__('erp.batteries.sections.cabling'))
                    ->columns(4)
                    ->schema([
                        $number('powerflex_length_m', 'm'),
                        TextInput::make('powerflex_section')->label(__('erp.batteries.fields.powerflex_section'))->suffix('mm²')->maxLength(20),
                        Select::make('battery_cable_id')
                            ->label(__('erp.batteries.fields.battery_cable_id'))
                            ->options(fn (): array => BatteryCable::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()),
                        $number('frotek_count', 'st'),
                        $number('lug_count', 'st'),
                        TextInput::make('lug_section')->label(__('erp.batteries.fields.lug_section'))->suffix('mm²')->maxLength(20),
                        $number('tray_length_m', 'm'),
                        TextInput::make('tray_type')->label(__('erp.batteries.fields.tray_type'))->maxLength(50),
                        $number('fuse_count', 'st'),
                        TextInput::make('fuse_amperage')->label(__('erp.batteries.fields.fuse_amperage'))->suffix('A')->maxLength(20),
                        TextInput::make('fuse_type')->label(__('erp.batteries.fields.fuse_type'))->placeholder('gG')->maxLength(30),
                    ]),
                Section::make(__('erp.batteries.sections.material_prices'))
                    ->visible(fn (?BatteryDossier $record): bool => Gate::allows('viewPrices', $record ?? static::$model))
                    ->columns(4)
                    ->schema(array_map(fn (string $field): TextInput => TextInput::make($field)->label(__("erp.batteries.fields.{$field}"))->numeric()->minValue(0)->step('any')->prefix('€'),
                        ['fuse_price', 'powerflex_price', 'lug_price', 'tray_price', 'doc_holder_price', 'picto_price'])),
            ]),
            self::signatureSection('delivery'),
            self::download('delivery'),
        ];
    }

    /**
     * @return array<Component>
     */
    protected static function measurementTab(): array
    {
        $density = fn (Get $get): bool => (bool) BatteryTechnology::tryFrom((string) ($get('new_technology') instanceof BatteryTechnology ? $get('new_technology')->value : $get('new_technology')))?->hasDensity();

        return [
            self::lockable('measurement', [
                Section::make()
                    ->columns(3)
                    ->schema([
                        DatePicker::make('measured_on')->label(__('erp.batteries.measured_on'))->helperText(__('erp.batteries.measured_on_help')),
                        TextInput::make('average_temperature')->label(__('erp.batteries.average_temperature'))->numeric()->step('any')->suffix('°C'),
                        Text::make(fn (Get $get): string => __('erp.batteries.measurement_help', ['cells' => (int) $get('cells_per_battery'), 'count' => (int) $get('new_count')])),
                    ]),
                Grid::make(['default' => 1, 'xl' => 2])
                    ->schema(fn (Get $get): array => array_map(fn (int $position): Section => Section::make('Batt '.$position)
                        ->description(function (Get $get) use ($position): string {
                            $values = array_filter((array) $get("strings.{$position}.cell_voltages"), 'is_numeric');

                            return __('erp.batteries.end_voltage', ['volts' => $values ? number_format(array_sum($values), 2, ',', '') : '—']);
                        })
                        ->compact()
                        ->columns(['default' => 4, 'md' => 6])
                        ->schema(array_merge(...array_map(fn (int $cell): array => array_values(array_filter([
                            TextInput::make("strings.{$position}.cell_voltages.".($cell - 1))
                                ->hiddenLabel()
                                ->prefix((string) $cell)
                                ->placeholder('V')
                                ->numeric()
                                ->step('any')
                                ->live(onBlur: true),
                            $density($get) ? TextInput::make("strings.{$position}.cell_densities.".($cell - 1))->hiddenLabel()->prefix('d')->placeholder('kg/l')->numeric()->step('any') : null,
                        ])), range(1, max(1, min(60, (int) $get('cells_per_battery'))))))), self::positions($get))),
            ]),
            self::signatureSection('measurement'),
            self::download('measurement'),
        ];
    }

    /**
     * @return array<Component>
     */
    protected static function inspectionTab(): array
    {
        $sections = [];

        foreach (static::inspectionSections() as $title => $questions) {
            $sections[] = Section::make($title)
                ->compact()
                ->collapsible()
                ->columns(['default' => 1, 'lg' => 2])
                ->schema(array_map(fn (string $key, string $question): ToggleButtons => ToggleButtons::make("inspection.{$key}")
                    ->label($question)
                    ->options(['yes' => __('erp.batteries.yes'), 'no' => __('erp.batteries.no')])
                    ->colors(['yes' => 'success', 'no' => 'danger'])
                    ->inline()
                    ->grouped(), array_keys($questions), $questions));
        }

        return [
            self::lockable('inspection', [
                ...$sections,
                Textarea::make('inspection_remarks')->label(__('erp.batteries.inspection_remarks'))->rows(3)->maxLength(5000),
            ]),
            self::signatureSection('inspection'),
            self::download('inspection'),
        ];
    }

    /**
     * For users without "batteries.input": what they need to know about the job, read-only.
     *
     * @return array<Component>
     */
    protected static function overviewTab(): array
    {
        $entry = fn (string $name, string $label, \Closure $state): TextEntry => TextEntry::make('overview_'.$name)
            ->label($label)
            ->state($state)
            ->placeholder('—');
        $batteries = fn (?int $count, ?int $capacity, mixed $technology, ?string $extra = null): ?string => $count
            ? trim($count.' × '.($capacity ? $capacity.' Ah ' : '').($technology?->getLabel() ?? '').($extra ? ' · '.$extra : ''))
            : null;

        return [
            Section::make(__('erp.batteries.overview.title'))
                ->description(__('erp.batteries.overview.help'))
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    $entry('cow', __('erp.batteries.overview.cow_code'), fn (?BatteryDossier $record): ?string => $record?->cowCode()),
                    $entry('address', __('erp.batteries.overview.address'), fn (?BatteryDossier $record): ?string => $record ? (implode(', ', $record->senderAddressLines()) ?: null) : null),
                    $entry('contact', __('erp.batteries.contact_person'), fn (?BatteryDossier $record): ?string => $record?->contact_person),
                    $entry('rack', __('erp.batteries.overview.rack'), fn (?BatteryDossier $record): ?string => $record ? (trim(implode(' · ', array_filter([
                        $record->mounting?->getLabel(),
                        $record->rack()?->code ? __('erp.batteries.rack_type', ['code' => $record->rack()->code]) : null,
                    ]))) ?: null) : null),
                    $entry('remove', __('erp.batteries.overview.to_remove'), fn (?BatteryDossier $record): ?string => $record && $record->removal
                        ? $batteries($record->old_count, $record->old_capacity_ah, $record->old_technology, $record->old_room ? __('erp.batteries.room').' '.$record->old_room : null)
                        : ($record ? __('erp.batteries.overview.no_removal') : null)),
                    $entry('install', __('erp.batteries.overview.to_install'), fn (?BatteryDossier $record): ?string => $record
                        ? $batteries($record->new_count, $record->new_capacity_ah, $record->new_technology, trim(implode(' ', array_filter([$record->brand, $record->typeName()]))) ?: null)
                        : null),
                    $entry('ids', __('erp.batteries.overview.id_numbers'), fn (?BatteryDossier $record): ?string => $record
                        ? ($record->batteries->filter(fn (BatteryDossierBattery $battery): bool => filled($battery->id_number))->map(fn (BatteryDossierBattery $battery): string => $battery->label().': '.$battery->id_number)->implode(' · ') ?: null)
                        : null)
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * The last step: photos of the completed works.
     *
     * @return array<Component>
     */
    protected static function photosTab(): array
    {
        return [
            Section::make(__('erp.batteries.photos.title'))
                ->description(__('erp.batteries.photos.help'))
                ->schema([
                    PrivatePhotos::upload('photos', 'batteries')
                        ->directory(fn (?BatteryDossier $record): string => 'batteries/'.$record?->id.'/photos')
                        ->maxFiles(BatteryDossier::MAX_PHOTOS)
                        ->appendFiles()
                        ->reorderable()
                        ->openable()
                        ->hiddenLabel(),
                    Textarea::make('photos_remark')->label(__('erp.batteries.photos.remark'))->rows(3)->maxLength(5000),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    protected static function floorPlanTab(): array
    {
        return [
            FloorPlanEditor::make('floor_plan')->hiddenLabel()->pngStatePath('floor_plan_png'),
            Hidden::make('floor_plan_png'),
            self::download('floor_plan'),
        ];
    }

    /**
     * @return array<Component>
     */
    protected static function singleLineTab(): array
    {
        return [
            Grid::make(['default' => 1, 'lg' => 2, '2xl' => 3])
                ->schema(fn (Get $get): array => array_map(fn (int $position): Section => Section::make('BATT '.$position)
                    ->compact()
                    ->columns(2)
                    ->schema([
                        TextInput::make("strings.{$position}.cable_type")->label(__('erp.batteries.fields.cable_type'))->datalist(['XVB', 'XGB', 'XVB-F2', 'H07RN-F'])->placeholder('XVB/XGB')->maxLength(30)->live(onBlur: true),
                        TextInput::make("strings.{$position}.cable_length_m")->label(__('erp.batteries.fields.cable_length_m'))->numeric()->minValue(0)->step('any')->suffix('m')->live(onBlur: true),
                        TextInput::make("strings.{$position}.cables_positive")->label(__('erp.batteries.fields.cables_positive'))->integer()->minValue(0)->maxValue(20)->live(onBlur: true),
                        TextInput::make("strings.{$position}.cables_negative")->label(__('erp.batteries.fields.cables_negative'))->integer()->minValue(0)->maxValue(20)->live(onBlur: true),
                        TextInput::make("strings.{$position}.cable_section")->label(__('erp.batteries.fields.cable_section'))->suffix('mm²')->maxLength(20)->live(onBlur: true),
                        TextInput::make("strings.{$position}.fuse_amperage")->label(__('erp.batteries.fields.fuse_amperage'))->suffix('A')->maxLength(20)->live(onBlur: true),
                        TextInput::make("strings.{$position}.fuse_type")->label(__('erp.batteries.fields.fuse_type'))->placeholder(BatteryDossierBattery::DEFAULT_FUSE_TYPE)->maxLength(30)->live(onBlur: true)->columnSpanFull(),
                    ]), self::positions($get))),
            Section::make(__('erp.batteries.tabs.single_line'))
                ->compact()
                ->schema([
                    Text::make(fn (Get $get): HtmlString => new HtmlString('<div style="overflow-x: auto; background: #fff; border-radius: .5rem; padding: .5rem;">'.BatterySingleLine::svg(array_map(
                        fn (int $position): array => ['position' => $position, ...array_intersect_key((array) $get("strings.{$position}"), array_flip(['id_number', 'cable_type', 'cable_length_m', 'cables_positive', 'cables_negative', 'cable_section', 'fuse_amperage', 'fuse_type']))],
                        self::positions($get),
                    )).'</div>')),
                ]),
            self::download('single_line'),
        ];
    }

    /**
     * @return array<Component>
     */
    protected static function shippingTab(): array
    {
        return [
            Section::make(__('erp.batteries.sections.shipping'))
                ->description(__('erp.batteries.sections.shipping_help'))
                ->columns(4)
                ->schema([
                    TextInput::make('acid_boxes')->label(__('erp.batteries.fields.acid_boxes'))->integer()->minValue(0)->default(0),
                    TextInput::make('empty_boxes_returned')->label(__('erp.batteries.fields.empty_boxes_returned'))->integer()->minValue(0)->default(0),
                    TextInput::make('packages')->label(__('erp.batteries.fields.packages'))->integer()->minValue(0)->default(0),
                    TextInput::make('weight_kg')->label(__('erp.batteries.fields.weight_kg'))->numeric()->minValue(0)->step('any')->suffix('kg'),
                ]),
            self::download('shipping'),
        ];
    }

    /**
     * @return array<Component>
     */
    protected static function invoiceTab(): array
    {
        return [
            Section::make()
                ->columns(2)
                ->schema([
                    Select::make('invoice_kind')->label(__('erp.batteries.invoice_kind'))->options(BatteryInvoiceKind::class)->default(BatteryInvoiceKind::Final->value)->required(),
                    TextInput::make('invoice_percentage')->label(__('erp.batteries.invoice_percentage'))->numeric()->minValue(0)->maxValue(100)->step('any')->suffix('%')->default(100)->required(),
                ]),
            Section::make(__('erp.batteries.sections.invoice_lines'))
                ->description(__('erp.batteries.sections.invoice_lines_help'))
                ->schema([
                    Text::make(fn (?BatteryDossier $record): HtmlString => new HtmlString($record ? view('batteries.invoice-lines', ['dossier' => $record->fresh(['batteryModel.racks', 'cable', 'project'])])->render() : '')),
                ]),
            self::download('invoice_request'),
        ];
    }

    /**
     * Projects of the battery category (604) the user can see, plus the dossier's own project.
     *
     * @return array<int, string>
     */
    public static function projectOptions(?BatteryDossier $record = null): array
    {
        return Project::query()
            ->visibleTo(auth()->user())
            ->whereHas('category', fn (Builder $query) => $query->where('code', settings()->get(static::$model::CATEGORY_SETTING)))
            ->when($record, fn (Builder $query) => $query->orWhere('projects.id', $record->project_id))
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (Project $project): array => [$project->id => trim($project->reference.' '.($project->subtitle() ? '— '.$project->subtitle() : ''))])
            ->all();
    }
}
