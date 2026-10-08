<?php

namespace App\Filament\Resources\DistributorContacts;

use App\Enums\NavigationGroup;
use App\Filament\Resources\DistributorContacts\Pages\ManageDistributorContacts;
use App\Filament\Resources\DistributorContacts\Schemas\DistributorContactForm;
use App\Models\DistributorContact;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DistributorContactResource extends Resource
{
    protected static ?string $model = DistributorContact::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Stock;

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('erp.resources.distributor_contact.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.distributor_contact.plural');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof DistributorContact ? $record->fullName() : static::getModelLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return DistributorContactForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return static::contactsTable($table, withDistributor: true);
    }

    public static function contactsTable(Table $table, bool $withDistributor): Table
    {
        return $table
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('last_name')
                    ->label(__('erp.fields.name'))
                    ->formatStateUsing(fn (DistributorContact $record): string => $record->fullName())
                    ->description(fn (DistributorContact $record): ?string => $record->job_title)
                    ->weight('bold')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('distributor.name')
                    ->label(__('erp.resources.distributor.singular'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->visible($withDistributor),
                TextColumn::make('email')
                    ->label(__('erp.fields.email'))
                    ->url(fn (DistributorContact $record): ?string => $record->email ? "mailto:{$record->email}" : null)
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label(__('erp.fields.phone'))
                    ->url(fn (DistributorContact $record): ?string => $record->phone ? 'tel:'.preg_replace('/[^\d+]/', '', $record->phone) : null)
                    ->placeholder('—'),
            ])
            ->filters($withDistributor ? [
                SelectFilter::make('distributor')
                    ->label(__('erp.resources.distributor.singular'))
                    ->relationship('distributor', 'name')
                    ->preload(),
            ] : [])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDistributorContacts::route('/'),
        ];
    }
}
