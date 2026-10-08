<?php

namespace App\Filament\Resources\Clients;

use App\Enums\NavigationGroup;
use App\Enums\VatRate;
use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Models\Client;
use App\Models\Country;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The client list for invoicing. The internal memo is only shown to administrators.
 */
class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'clients';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'company_name';

    /**
     * @var list<string>
     */
    protected static array $globallySearchableAttributes = ['short_name', 'company_name', 'vat_number'];

    public static function getModelLabel(): string
    {
        return __('erp.resources.client.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.client.plural');
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->label();
    }

    public static function form(Schema $schema): Schema
    {
        $isAdmin = fn (): bool => (bool) auth()->user()?->isAdmin();

        return $schema
            ->columns(6)
            ->components([
                TextInput::make('short_name')
                    ->label(__('erp.finance.clients.short_name'))
                    ->helperText(__('erp.finance.clients.short_name_help'))
                    ->required()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true)
                    ->extraInputAttributes(['style' => 'text-transform: uppercase; font-family: var(--erp-mono);'])
                    ->columnSpan(2),
                TextInput::make('company_name')
                    ->label(__('erp.finance.clients.company_name'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(4),
                Toggle::make('is_private')
                    ->label(__('erp.finance.clients.is_private'))
                    ->helperText(__('erp.finance.clients.is_private_help'))
                    ->live()
                    ->columnSpanFull(),
                TextInput::make('vat_number')
                    ->label(__('erp.finance.clients.vat_number'))
                    ->placeholder('BE0123.456.789')
                    ->maxLength(20)
                    ->required(fn (Get $get): bool => ! $get('is_private'))
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (filled($value) && ! Client::isValidVat((string) $value)) {
                            $fail(__('erp.finance.vat_invalid'));
                        }
                    })
                    ->extraInputAttributes(['style' => 'font-family: var(--erp-mono);'])
                    ->columnSpan(3),
                TextInput::make('peppol_id')
                    ->label(__('erp.finance.clients.peppol_id'))
                    ->helperText(__('erp.finance.clients.peppol_id_help'))
                    ->placeholder('0208:0123456789')
                    ->regex('/^\d{4}:[A-Za-z0-9]+$/')
                    ->maxLength(60)
                    ->columnSpan(3),
                Section::make(__('erp.fields.address'))
                    ->columns(6)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('street')->label(__('erp.fields.street_only'))->maxLength(150)->columnSpan(3),
                        TextInput::make('house_number')->label(__('erp.fields.house_number'))->maxLength(20)->columnSpan(1),
                        TextInput::make('addition')->label(__('erp.finance.clients.addition'))->placeholder(__('erp.finance.clients.addition_placeholder'))->maxLength(20)->columnSpan(2),
                        TextInput::make('postal_code')->label(__('erp.fields.postal_code'))->maxLength(20)->columnSpan(2),
                        TextInput::make('city')->label(__('erp.fields.city'))->maxLength(100)->columnSpan(2),
                        Select::make('country_id')
                            ->label(__('erp.fields.country'))
                            ->options(fn (): array => Country::options())
                            ->default(fn (): ?int => Country::belgiumId())
                            ->searchable()
                            ->columnSpan(2),
                    ]),
                Section::make(__('erp.finance.clients.contact'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TagsInput::make('emails')
                            ->label(__('erp.finance.clients.emails'))
                            ->helperText(__('erp.finance.clients.emails_help'))
                            ->placeholder('info@client.be')
                            ->nestedRecursiveRules(['email:rfc', 'max:255'])
                            ->columnSpanFull(),
                        TextInput::make('accounting_email')
                            ->label(__('erp.finance.clients.accounting_email'))
                            ->helperText(__('erp.finance.clients.accounting_email_help'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')->label(__('erp.fields.phone'))->tel()->maxLength(50),
                    ]),
                Section::make(__('erp.finance.clients.invoicing'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('default_vat_rate')
                            ->label(__('erp.finance.clients.default_vat_rate'))
                            ->options(VatRate::class)
                            ->default(VatRate::TwentyOne)
                            ->required(),
                        TextInput::make('payment_term_days')
                            ->label(__('erp.finance.clients.payment_term'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(365)
                            ->default(fn (): int => (int) settings()->get('finance.payment_term_days', 30))
                            ->suffix(__('erp.vacations.days'))
                            ->required(),
                        Select::make('locale')
                            ->label(__('erp.finance.clients.locale'))
                            ->options(config('app.locales'))
                            ->default(config('app.locale'))
                            ->required(),
                        Toggle::make('send_reminders')
                            ->label(__('erp.finance.clients.send_reminders'))
                            ->default(true),
                        Toggle::make('is_active')
                            ->label(__('erp.fields.active'))
                            ->default(true),
                    ]),
                Textarea::make('internal_memo')
                    ->label(__('erp.finance.clients.internal_memo'))
                    ->helperText(__('erp.finance.clients.internal_memo_help'))
                    ->rows(3)
                    ->maxLength(5000)
                    ->visible($isAdmin)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('short_name')
            ->modifyQueryUsing(fn ($query) => $query->withCount('invoices'))
            ->columns([
                TextColumn::make('short_name')
                    ->label(__('erp.finance.clients.short_name'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('company_name')
                    ->label(__('erp.finance.clients.company_name'))
                    ->description(fn (Client $record): ?string => implode(', ', $record->addressLines()) ?: null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vat_number')
                    ->label(__('erp.finance.clients.vat_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder(fn (Client $record): string => $record->is_private ? __('erp.finance.clients.private') : '—')
                    ->searchable(),
                TextColumn::make('accounting_email')
                    ->label(__('erp.finance.clients.invoice_email'))
                    ->state(fn (Client $record): ?string => $record->invoiceEmail())
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('payment_term_days')
                    ->label(__('erp.finance.clients.payment_term'))
                    ->suffix(' '.__('erp.absences.days_short'))
                    ->toggleable(),
                TextColumn::make('invoices_count')
                    ->label(__('erp.resources.invoice.plural'))
                    ->badge()
                    ->color('gray'),
                IconColumn::make('internal_memo')
                    ->label(__('erp.finance.clients.memo_short'))
                    ->state(fn (Client $record): bool => filled($record->internal_memo))
                    ->boolean()
                    ->falseIcon(null)
                    ->trueIcon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->tooltip(fn (Client $record): ?string => $record->internal_memo)
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
            ])
            ->filters([
                TernaryFilter::make('is_private')->label(__('erp.finance.clients.is_private')),
                TernaryFilter::make('is_active')->label(__('erp.fields.active'))->default(true),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClients::route('/'),
        ];
    }
}
