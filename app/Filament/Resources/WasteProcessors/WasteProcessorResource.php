<?php

namespace App\Filament\Resources\WasteProcessors;

use App\Enums\NavigationGroup;
use App\Filament\Resources\WasteProcessors\Pages\ManageWasteProcessors;
use App\Models\Country;
use App\Models\WasteProcessor;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Waste processors (collectors / treatment companies), added freely.
 */
class WasteProcessorResource extends Resource
{
    protected static ?string $model = WasteProcessor::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'waste-processors';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Waste;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('erp.resources.waste_processor.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.waste_processor.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                TextInput::make('name')
                    ->label(__('erp.fields.name'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(4),
                TextInput::make('vat_number')
                    ->label(__('erp.waste.vat_number'))
                    ->placeholder('BE0123.456.789')
                    ->maxLength(30)
                    ->columnSpan(2),
                TextInput::make('street')->label(__('erp.fields.street_only'))->maxLength(150)->columnSpan(4),
                TextInput::make('house_number')->label(__('erp.fields.house_number'))->maxLength(20)->columnSpan(2),
                TextInput::make('postal_code')->label(__('erp.fields.postal_code'))->maxLength(20)->columnSpan(2),
                TextInput::make('city')->label(__('erp.fields.city'))->maxLength(100)->columnSpan(2),
                Select::make('country_id')
                    ->label(__('erp.fields.country'))
                    ->options(fn (): array => Country::options())
                    ->default(fn (): ?int => Country::belgiumId())
                    ->searchable()
                    ->columnSpan(2),
                TextInput::make('permit_number')
                    ->label(__('erp.waste.permit_number'))
                    ->helperText(__('erp.waste.permit_number_help'))
                    ->maxLength(100)
                    ->columnSpan(3),
                TextInput::make('contact_name')->label(__('erp.fields.contact_person'))->maxLength(255)->columnSpan(3),
                TextInput::make('phone')->label(__('erp.fields.phone'))->tel()->maxLength(50)->columnSpan(3),
                TextInput::make('email')->label(__('erp.fields.email'))->email()->maxLength(255)->columnSpan(3),
                Textarea::make('notes')->label(__('erp.fields.notes'))->rows(2)->maxLength(2000)->columnSpanFull(),
                Toggle::make('is_active')
                    ->label(__('erp.fields.active'))
                    ->helperText(__('erp.waste.processor_active_help'))
                    ->default(true)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->withCount('entries'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->weight('medium')
                    ->description(fn (WasteProcessor $record): ?string => $record->addressLine())
                    ->searchable(['name', 'city']),
                TextColumn::make('permit_number')
                    ->label(__('erp.waste.permit_number'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('contact_name')
                    ->label(__('erp.fields.contact_person'))
                    ->description(fn (WasteProcessor $record): ?string => $record->phone)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('entries_count')
                    ->label(__('erp.waste.entries'))
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label(__('erp.fields.active'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWasteProcessors::route('/'),
        ];
    }
}
