<?php

namespace App\Filament\Resources\ItAssets\Schemas;

use App\Enums\ItStatusType;
use App\Models\ItAsset;
use App\Models\ItModel;
use App\Models\ItStatusLabel;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ItAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        $mono = ['style' => 'font-family: var(--erp-mono);'];

        return $schema
            ->components([
                Grid::make(['lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Section::make(__('erp.sections.it_asset'))
                            ->columnSpan(['lg' => 2])
                            ->columns(2)
                            ->schema([
                                TextInput::make('asset_tag')
                                    ->label(__('erp.fields.asset_tag'))
                                    ->default(fn (): string => static::nextAssetTag())
                                    ->required()
                                    ->maxLength(40)
                                    ->unique(ignoreRecord: true)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase; font-family: var(--erp-mono);']),
                                TextInput::make('name')
                                    ->label(__('erp.fields.name'))
                                    ->helperText(__('erp.help.it_asset_name'))
                                    ->maxLength(150),
                                Select::make('it_model_id')
                                    ->label(__('erp.resources.it_model.singular'))
                                    ->relationship('model', 'name')
                                    ->getOptionLabelFromRecordUsing(fn (ItModel $record): string => $record->fullName())
                                    ->searchable(['name', 'model_number'])
                                    ->preload()
                                    ->required(),
                                Select::make('it_status_label_id')
                                    ->label(__('erp.fields.status'))
                                    ->relationship('status', 'name')
                                    ->default(fn (): ?int => ItStatusLabel::where('type', ItStatusType::Deployable)->value('id'))
                                    ->preload()
                                    ->required(),
                                TextInput::make('serial')
                                    ->label(__('erp.fields.serial_number'))
                                    ->maxLength(100)
                                    ->extraInputAttributes($mono),
                                Select::make('location_id')
                                    ->label(__('erp.resources.location.singular'))
                                    ->relationship('location', 'name', fn (Builder $query) => $query->visibleTo(auth()->user()))
                                    ->preload()
                                    ->visible(fn (): bool => modules()->locations()),
                                Toggle::make('requestable')
                                    ->label(__('erp.fields.requestable'))
                                    ->helperText(__('erp.help.requestable')),
                            ]),
                        Section::make(__('erp.fields.image'))
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                FileUpload::make('image')
                                    ->hiddenLabel()
                                    ->helperText(__('erp.help.it_asset_image'))
                                    ->image()
                                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                    ->maxSize(4096)
                                    ->disk('public')
                                    ->directory('it-assets')
                                    ->visibility('public'),
                            ]),
                    ]),
                Section::make(__('erp.sections.technical'))
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('hostname')
                            ->label(__('erp.fields.hostname'))
                            ->maxLength(100)
                            ->extraInputAttributes($mono),
                        TextInput::make('ip_address')
                            ->label(__('erp.fields.ip_address'))
                            ->ip()
                            ->extraInputAttributes($mono),
                        TextInput::make('mac_address')
                            ->label(__('erp.fields.mac_address'))
                            ->regex('/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/')
                            ->placeholder('AA:BB:CC:DD:EE:FF')
                            ->extraInputAttributes($mono),
                        TextInput::make('operating_system')
                            ->label(__('erp.fields.operating_system'))
                            ->datalist(['Windows 11 Pro', 'Windows 10 Pro', 'macOS', 'Ubuntu', 'iOS', 'Android'])
                            ->maxLength(100),
                        KeyValue::make('specs')
                            ->label(__('erp.fields.specs'))
                            ->helperText(__('erp.help.specs'))
                            ->keyLabel(__('erp.fields.spec'))
                            ->valueLabel(__('erp.fields.value'))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('erp.sections.purchase'))
                    ->columns(['default' => 1, 'md' => 3])
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        Select::make('it_supplier_id')
                            ->label(__('erp.fields.supplier'))
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([TextInput::make('name')->label(__('erp.fields.name'))->required()->maxLength(100)]),
                        TextInput::make('order_number')
                            ->label(__('erp.fields.order_number'))
                            ->maxLength(50),
                        DatePicker::make('purchase_date')
                            ->label(__('erp.fields.purchase_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        TextInput::make('purchase_cost')
                            ->label(__('erp.fields.purchase_price'))
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€'),
                        TextInput::make('warranty_months')
                            ->label(__('erp.fields.warranty_months'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(240)
                            ->suffix(__('erp.fields.months')),
                        DatePicker::make('next_audit_date')
                            ->label(__('erp.fields.next_audit_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(fn () => today()->addMonthsNoOverflow(settings()->itAuditMonths())),
                        Textarea::make('notes')
                            ->label(__('erp.fields.notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Suggest "IT-0001"-style tags, one higher than the highest numeric tag so far.
     */
    public static function nextAssetTag(): string
    {
        $highest = ItAsset::query()
            ->where('asset_tag', 'like', 'IT-%')
            ->pluck('asset_tag')
            ->map(fn (string $tag): int => (int) substr($tag, 3))
            ->max() ?? 0;

        return 'IT-'.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }
}
