<?php

namespace App\Filament\Resources\BankAccounts;

use App\Enums\Bank;
use App\Enums\NavigationGroup;
use App\Filament\Resources\BankAccounts\Pages\ManageBankAccounts;
use App\Filament\Resources\BankTransactions\BankTransactionResource;
use App\Models\BankAccount;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * The company's bank accounts that are read: through Ponto (ING, Belfius, KBC…) or from CODA files.
 * Read only: balances and movements, never payments.
 */
class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'bank-accounts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 6;

    public static function getModelLabel(): string
    {
        return __('erp.resources.bank_account.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.bank_account.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label(__('erp.fields.name'))->required()->maxLength(100),
                TextInput::make('iban')
                    ->label('IBAN')
                    ->required()
                    ->maxLength(42)
                    ->dehydrateStateUsing(fn (?string $state): ?string => BankAccount::normaliseIban($state))
                    ->rule(fn (?BankAccount $record) => Rule::unique('bank_accounts', 'iban')->ignore($record?->id))
                    ->disabled(fn (?BankAccount $record): bool => $record?->provider === BankAccount::PONTO)
                    ->extraInputAttributes(['style' => 'font-family: var(--erp-mono);']),
                Select::make('bank')->label(__('erp.banking.bank'))->options(Bank::class)->default(Bank::Other)->required(),
                TextInput::make('bic')->label('BIC')->maxLength(11),
                Toggle::make('is_active')->label(__('erp.banking.is_active'))->helperText(__('erp.banking.is_active_help'))->default(true)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('erp.fields.name'))->description(fn (BankAccount $record): string => $record->formattedIban())->searchable(['name', 'iban']),
                TextColumn::make('bank')->label(__('erp.banking.bank'))->badge()->color('gray'),
                TextColumn::make('provider')
                    ->label(__('erp.banking.provider'))
                    ->formatStateUsing(fn (string $state): string => __("erp.banking.providers.{$state}"))
                    ->description(fn (BankAccount $record): ?string => $record->last_error ? __('erp.banking.last_error').': '.$record->last_error : null),
                TextColumn::make('balance')
                    ->label(__('erp.banking.balance'))
                    ->money(fn (BankAccount $record): string => $record->currency, locale: 'nl_BE')
                    ->description(fn (BankAccount $record): ?string => $record->balance_at?->format('d/m/Y H:i'))
                    ->fontFamily(FontFamily::Mono)
                    ->alignEnd()
                    ->placeholder('—'),
                TextColumn::make('transactions_count')->label(__('erp.banking.transactions'))->counts('transactions')->alignEnd(),
                TextColumn::make('last_synced_at')->label(__('erp.banking.last_synced'))->dateTime('d/m/Y H:i')->placeholder('—'),
                IconColumn::make('is_active')->label(__('erp.banking.is_active'))->boolean(),
            ])
            ->recordActions([
                Action::make('transactions')
                    ->label(__('erp.banking.transactions'))
                    ->icon(Heroicon::OutlinedListBullet)
                    ->color('gray')
                    ->url(fn (BankAccount $record): string => BankTransactionResource::getUrl('index', ['filters' => ['bank_account_id' => ['value' => $record->id]]])),
                EditAction::make()->slideOver(),
                DeleteAction::make()->modalDescription(__('erp.banking.delete_help')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBankAccounts::route('/'),
        ];
    }
}
