<?php

namespace App\Filament\Resources\VatCodes;

use App\Enums\NavigationGroup;
use App\Filament\Resources\VatCodes\Pages\ManageVatCodes;
use App\Models\VatCode;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The VAT codes (BTW-codes) invoice lines can have: rate, Peppol category, reverse charge
 * (autoliquidatie / medecontractant) and the legal mention printed on the invoice. The code itself
 * is fixed once created (lines store it); a code in use can be switched off but not removed.
 */
class VatCodeResource extends Resource
{
    protected static ?string $model = VatCode::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'vat-codes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 8;

    public static function getModelLabel(): string
    {
        return __('erp.resources.vat_code.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.vat_code.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextInput::make('code')
                    ->label(__('erp.finance.vat_codes.code'))
                    ->helperText(__('erp.finance.vat_codes.code_help'))
                    ->required()
                    ->maxLength(20)
                    ->regex('/^[A-Za-z0-9_.\-]+$/')
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (?VatCode $record): bool => $record !== null)
                    ->extraInputAttributes(['style' => 'font-family: var(--erp-mono);']),
                TextInput::make('percentage')
                    ->label(__('erp.finance.vat_codes.percentage'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01)
                    ->suffix('%')
                    ->default(21)
                    ->required()
                    ->disabled(fn (Get $get): bool => (bool) $get('is_reverse_charge')),
                Select::make('category')
                    ->label(__('erp.finance.vat_codes.category'))
                    ->helperText(__('erp.finance.vat_codes.category_help'))
                    ->options(fn (): array => collect(VatCode::CATEGORIES)->mapWithKeys(fn (string $category): array => [$category => $category.' – '.__('erp.finance.vat_codes.categories.'.$category)])->all())
                    ->default('S')
                    ->required(),
                Toggle::make('is_reverse_charge')
                    ->label(__('erp.finance.vat_codes.reverse_charge'))
                    ->helperText(__('erp.finance.vat_codes.reverse_charge_help'))
                    ->live()
                    ->afterStateUpdated(function (bool $state, Set $set, Get $get): void {
                        if ($state) {
                            $set('percentage', 0);

                            if (! in_array($get('category'), ['AE', 'K'], true)) {
                                $set('category', 'AE');
                            }
                        }
                    })
                    ->columnSpanFull(),
                Section::make(__('erp.finance.vat_codes.name'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')->label('NL')->placeholder('21%')->required()->maxLength(100),
                        TextInput::make('name_fr')->label('FR')->maxLength(100),
                        TextInput::make('name_en')->label('EN')->maxLength(100),
                    ]),
                Section::make(__('erp.finance.vat_codes.mention'))
                    ->description(__('erp.finance.vat_codes.mention_help'))
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('mention')->label('NL')->rows(2)->maxLength(1000),
                        Textarea::make('mention_fr')->label('FR')->rows(2)->maxLength(1000),
                        Textarea::make('mention_en')->label('EN')->rows(2)->maxLength(1000),
                    ]),
                TextInput::make('exemption_reason_code')
                    ->label(__('erp.finance.vat_codes.exemption_reason_code'))
                    ->helperText(__('erp.finance.vat_codes.exemption_reason_code_help'))
                    ->placeholder('VATEX-EU-AE')
                    ->maxLength(30),
                TextInput::make('sort')->label(__('erp.kb.sort_order'))->integer()->default(0),
                Toggle::make('is_active')->label(__('erp.fields.active'))->helperText(__('erp.finance.vat_codes.active_help'))->default(true)->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('code')->label(__('erp.finance.vat_codes.code'))->fontFamily(FontFamily::Mono)->weight('bold')->searchable(),
                TextColumn::make('name')->label(__('erp.finance.vat_codes.name'))->state(fn (VatCode $record): string => $record->label())->searchable(['name', 'name_fr', 'name_en']),
                TextColumn::make('percentage')->label(__('erp.finance.vat_codes.percentage'))->state(fn (VatCode $record): string => rtrim(rtrim(number_format($record->percentage(), 2, ',', ''), '0'), ',').'%')->alignEnd(),
                TextColumn::make('category')->label(__('erp.finance.vat_codes.category'))->state(fn (VatCode $record): string => $record->ublCategory())->badge()->color('gray')
                    ->tooltip(fn (VatCode $record): string => __('erp.finance.vat_codes.categories.'.$record->ublCategory())),
                IconColumn::make('is_reverse_charge')->label(__('erp.finance.vat_codes.reverse_charge_short'))->boolean()->trueColor('warning')->falseIcon(Heroicon::OutlinedMinus)->falseColor('gray'),
                TextColumn::make('mention')->label(__('erp.finance.vat_codes.mention'))->state(fn (VatCode $record): ?string => $record->mention())->limit(60)->placeholder('—')->wrap(),
                IconColumn::make('is_active')->label(__('erp.fields.active'))->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('erp.fields.active')),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVatCodes::route('/'),
        ];
    }
}
