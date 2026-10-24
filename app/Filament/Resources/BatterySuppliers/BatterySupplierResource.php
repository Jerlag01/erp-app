<?php

namespace App\Filament\Resources\BatterySuppliers;

use App\Enums\NavigationGroup;
use App\Filament\Resources\BatterySuppliers\Pages\ManageBatterySuppliers;
use App\Models\BatterySupplier;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Battery suppliers: where the BAE orders are mailed to, and in which language.
 */
class BatterySupplierResource extends Resource
{
    protected static ?string $model = BatterySupplier::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'battery-suppliers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Batteries;

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('erp.batteries.supplier.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.batteries.supplier.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label(__('erp.fields.name'))->placeholder('BAE Batterien GmbH')->required()->maxLength(100),
                TextInput::make('contact_name')->label(__('erp.batteries.supplier.contact_name'))->helperText(__('erp.batteries.supplier.contact_name_help'))->maxLength(100),
                TextInput::make('email')->label(__('erp.batteries.supplier.email'))->helperText(__('erp.batteries.supplier.email_help'))->email()->required()->maxLength(150),
                TagsInput::make('cc_emails')
                    ->label(__('erp.batteries.supplier.cc_emails'))
                    ->helperText(__('erp.batteries.supplier.cc_emails_help'))
                    ->nestedRecursiveRules(['email', 'max:150'])
                    ->placeholder('purchase@…'),
                TextInput::make('phone')->label(__('erp.batteries.supplier.phone'))->tel()->maxLength(50),
                Select::make('locale')
                    ->label(__('erp.batteries.supplier.locale'))
                    ->helperText(__('erp.batteries.supplier.locale_help'))
                    ->options(BatterySupplier::LOCALES)
                    ->default('en')
                    ->required(),
                Textarea::make('address')->label(__('erp.batteries.supplier.address'))->rows(3)->maxLength(500),
                Textarea::make('notes')->label(__('erp.fields.notes'))->rows(3)->maxLength(2000),
                Toggle::make('is_default')->label(__('erp.batteries.supplier.is_default'))->helperText(__('erp.batteries.supplier.is_default_help')),
                Toggle::make('is_active')->label(__('erp.fields.active'))->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('erp.fields.name'))->weight(FontWeight::Bold)->description(fn (BatterySupplier $record): ?string => $record->contact_name)->searchable()->sortable(),
                TextColumn::make('email')
                    ->label(__('erp.batteries.supplier.email'))
                    ->description(fn (BatterySupplier $record): ?string => $record->ccEmails() !== [] ? 'cc: '.implode(', ', $record->ccEmails()) : null)
                    ->searchable(),
                TextColumn::make('locale')->label(__('erp.batteries.supplier.locale'))->formatStateUsing(fn (?string $state): string => BatterySupplier::LOCALES[$state] ?? (string) $state)->badge()->color('gray'),
                TextColumn::make('orders_count')->label(__('erp.batteries.bae_order.plural'))->counts('orders')->alignEnd(),
                IconColumn::make('is_default')->label(__('erp.batteries.supplier.is_default'))->boolean(),
                IconColumn::make('is_active')->label(__('erp.fields.active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBatterySuppliers::route('/'),
        ];
    }
}
