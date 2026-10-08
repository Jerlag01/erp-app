<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Vehicle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        $suggestNextInspection = function (Get $get, Set $set): void {
            $months = AssetCategory::find($get('asset_category_id'))?->inspection_interval_months;

            if ($months && filled($get('inspection_date'))) {
                $set('next_inspection_date', Carbon::parse($get('inspection_date'))->addMonthsNoOverflow($months)->toDateString());
            }
        };

        return $schema
            ->components([
                Grid::make(['lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Section::make(__('erp.sections.asset'))
                            ->columnSpan(['lg' => 2])
                            ->columns(2)
                            ->schema([
                                TextInput::make('asset_tag')
                                    ->label(__('erp.fields.asset_tag'))
                                    ->helperText(__('erp.help.asset_tag'))
                                    ->default(fn (): string => static::nextAssetTag())
                                    ->required()
                                    ->maxLength(40)
                                    ->unique(ignoreRecord: true)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase; font-family: var(--erp-mono);']),
                                TextInput::make('name')
                                    ->label(__('erp.fields.name'))
                                    ->placeholder(__('erp.help.asset_name'))
                                    ->required()
                                    ->maxLength(150),
                                Select::make('asset_category_id')
                                    ->label(__('erp.fields.category'))
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated($suggestNextInspection),
                                Select::make('status')
                                    ->label(__('erp.fields.status'))
                                    ->options(AssetStatus::class)
                                    ->default(AssetStatus::InService)
                                    ->required()
                                    ->selectablePlaceholder(false),
                                TextInput::make('brand')
                                    ->label(__('erp.fields.brand'))
                                    ->maxLength(60)
                                    ->datalist(['Bosch', 'Benning', 'Chauvin Arnoux', 'DeWalt', 'Fluke', 'Gossen Metrawatt', 'Hilti', 'Makita', 'Metabo', 'Milwaukee', 'Wera', 'Knipex']),
                                TextInput::make('model')
                                    ->label(__('erp.fields.model'))
                                    ->maxLength(100),
                                TextInput::make('serial_number')
                                    ->label(__('erp.fields.serial_number'))
                                    ->maxLength(100)
                                    ->extraInputAttributes(['style' => 'font-family: var(--erp-mono);'])
                                    ->columnSpanFull(),
                            ]),
                        Grid::make(1)
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                Section::make(__('erp.sections.inspection'))
                                    ->description(__('erp.help.inspection'))
                                    ->schema([
                                        DatePicker::make('inspection_date')
                                            ->label(__('erp.fields.inspection_date'))
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->maxDate(today())
                                            ->live()
                                            ->afterStateUpdated($suggestNextInspection),
                                        DatePicker::make('next_inspection_date')
                                            ->label(__('erp.fields.next_inspection_date'))
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->afterOrEqual('inspection_date'),
                                    ]),
                                Section::make(__('erp.sections.assignment'))
                                    ->description(__('erp.help.assignment'))
                                    ->schema([
                                        Select::make('location_id')
                                            ->label(__('erp.resources.location.singular'))
                                            ->relationship('location', 'name', fn (Builder $query) => $query->where('is_active', true)->visibleTo(auth()->user()))
                                            ->preload()
                                            ->visible(fn (): bool => modules()->locations()),
                                        Select::make('team_id')
                                            ->label(__('erp.resources.team.singular'))
                                            ->relationship('team', 'name', fn (Builder $query) => $query->where('is_active', true)->visibleTo(auth()->user()))
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (): bool => modules()->teams()),
                                        Select::make('vehicle_id')
                                            ->label(__('erp.resources.vehicle.singular'))
                                            ->relationship('vehicle', 'plate_number', fn (Builder $query) => $query->visibleTo(auth()->user()))
                                            ->getOptionLabelFromRecordUsing(fn (Vehicle $record): string => $record->label())
                                            ->searchable(['plate_number', 'brand', 'type'])
                                            ->preload()
                                            ->visible(fn (): bool => modules()->fleet()),
                                        Select::make('user_id')
                                            ->label(__('erp.fields.employee'))
                                            ->relationship('user', 'name', fn (Builder $query) => $query->where('is_active', true))
                                            ->searchable()
                                            ->preload(),
                                    ]),
                            ]),
                    ]),
                Section::make(__('erp.sections.purchase'))
                    ->columns(3)
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('purchase_date')
                            ->label(__('erp.fields.purchase_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        TextInput::make('purchase_price')
                            ->label(__('erp.fields.purchase_price'))
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€'),
                        DatePicker::make('warranty_until')
                            ->label(__('erp.fields.warranty_until'))
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        FileUpload::make('photo')
                            ->label(__('erp.fields.photo'))
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(4096)
                            ->disk('public')
                            ->directory('assets')
                            ->visibility('public')
                            ->imageEditor(),
                        Textarea::make('notes')
                            ->label(__('erp.fields.notes'))
                            ->rows(6)
                            ->columnSpan(2),
                    ]),
            ]);
    }

    /**
     * Suggest "EQ-0001"-style tags, one higher than the highest numeric tag so far.
     */
    public static function nextAssetTag(): string
    {
        $highest = Asset::query()
            ->where('asset_tag', 'like', 'EQ-%')
            ->pluck('asset_tag')
            ->map(fn (string $tag): int => (int) substr($tag, 3))
            ->max() ?? 0;

        return 'EQ-'.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }
}
