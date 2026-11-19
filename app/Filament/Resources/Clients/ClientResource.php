<?php

namespace App\Filament\Resources\Clients;

use App\Enums\InvoiceType;
use App\Enums\NavigationGroup;
use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\InvoicesRelationManager;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\VatCode;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
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
                // Communication points for invoicing: who receives the invoices and/or the reminders.
                Section::make(__('erp.finance.clients.contacts'))
                    ->description(__('erp.finance.clients.contacts_help'))
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('contacts')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort')
                            ->addActionLabel(__('erp.finance.clients.add_contact'))
                            ->itemLabel(fn (array $state): ?string => trim(implode(' · ', array_filter([$state['role'] ?? null, $state['name'] ?? null]))) ?: null)
                            ->collapsible()
                            ->columns(6)
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('name')->label(__('erp.fields.name'))->required()->maxLength(150)->columnSpan(2),
                                TextInput::make('role')->label(__('erp.finance.clients.contact_role'))->placeholder(__('erp.finance.clients.contact_role_placeholder'))->maxLength(100)->columnSpan(2),
                                TextInput::make('phone')->label(__('erp.fields.phone'))->tel()->maxLength(50)->columnSpan(2),
                                TextInput::make('email')->label(__('erp.fields.email'))->email()->required()->maxLength(255)->columnSpan(2),
                                Toggle::make('receives_invoices')->label(__('erp.finance.clients.receives_invoices'))->default(true)->inline(false)->columnSpan(2),
                                Toggle::make('receives_reminders')->label(__('erp.finance.clients.receives_reminders'))->default(true)->inline(false)->columnSpan(2),
                                TextInput::make('notes')->label(__('erp.finance.clients.contact_notes'))->maxLength(500)->columnSpanFull(),
                            ]),
                    ]),
                Section::make(__('erp.finance.clients.invoicing'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('default_vat_rate')
                            ->label(__('erp.finance.clients.default_vat_rate'))
                            ->helperText(__('erp.finance.clients.default_vat_rate_help'))
                            ->options(fn (mixed $state): array => VatCode::options($state))
                            ->default(VatCode::STANDARD)
                            ->required(),
                        TextInput::make('payment_term_days')
                            ->label(__('erp.finance.clients.payment_term'))
                            ->helperText(__('erp.finance.clients.payment_term_help'))
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
            ->recordUrl(fn (Client $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClients::route('/'),
            'view' => ViewClient::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            InvoicesRelationManager::class,
        ];
    }

    /**
     * The client's card: details, communication points and where its invoices stand.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(['lg' => 2])
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        TextEntry::make('company_name')
                            ->label(__('erp.finance.clients.company_name'))
                            ->helperText(fn (Client $record): string => implode(', ', $record->addressLines())),
                        TextEntry::make('vat_number')
                            ->label(__('erp.finance.clients.vat_number'))
                            ->fontFamily(FontFamily::Mono)
                            ->placeholder(fn (Client $record): string => $record->is_private ? __('erp.finance.clients.private') : '—'),
                        TextEntry::make('payment_term_days')
                            ->label(__('erp.finance.clients.payment_term'))
                            ->suffix(' '.__('erp.vacations.days')),
                        TextEntry::make('default_vat_rate')
                            ->label(__('erp.finance.clients.default_vat_rate'))
                            ->state(fn (Client $record): ?string => $record->default_vat_rate?->label())
                            ->badge()
                            ->color(fn (Client $record): string => $record->default_vat_rate?->isReverseCharge() ? 'warning' : 'gray'),
                        TextEntry::make('peppol')
                            ->label('Peppol')
                            ->state(fn (Client $record): ?string => $record->peppolParticipant())
                            ->fontFamily(FontFamily::Mono)
                            ->placeholder('—'),
                        IconEntry::make('send_reminders')
                            ->label(__('erp.finance.clients.send_reminders'))
                            ->boolean(),
                        RepeatableEntry::make('contacts')
                            ->label(__('erp.finance.clients.contacts'))
                            ->placeholder(fn (Client $record): string => __('erp.finance.clients.no_contacts', ['email' => $record->invoiceEmail() ?? '—']))
                            ->columns(['default' => 1, 'sm' => 3])
                            ->columnSpanFull()
                            ->schema([
                                TextEntry::make('name')
                                    ->hiddenLabel()
                                    ->weight('bold')
                                    ->helperText(fn (ClientContact $record): ?string => $record->role),
                                TextEntry::make('email')
                                    ->hiddenLabel()
                                    ->helperText(fn (ClientContact $record): ?string => $record->phone),
                                TextEntry::make('receives')
                                    ->hiddenLabel()
                                    ->state(fn (ClientContact $record): array => array_values(array_filter([
                                        $record->receives_invoices ? __('erp.finance.clients.receives_invoices') : null,
                                        $record->receives_reminders ? __('erp.finance.clients.receives_reminders') : null,
                                    ])))
                                    ->badge()
                                    ->color('gray'),
                            ]),
                    ]),
                Section::make(__('erp.finance.clients.balance'))
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        TextEntry::make('outstanding')
                            ->label(__('erp.finance.clients.outstanding'))
                            ->state(fn (Client $record): string => Invoice::money($record->invoices()->outstanding()->sum('total')))
                            ->weight('bold'),
                        TextEntry::make('overdue')
                            ->label(__('erp.finance.clients.overdue'))
                            ->state(fn (Client $record): string => Invoice::money($record->invoices()->outstanding()->whereDate('due_date', '<', today())->sum('total')))
                            ->helperText(fn (Client $record): string => trans_choice('erp.finance.clients.overdue_count', $count = $record->invoices()->outstanding()->whereDate('due_date', '<', today())->count(), ['count' => $count]))
                            ->color(fn (Client $record): ?string => $record->invoices()->outstanding()->whereDate('due_date', '<', today())->exists() ? 'danger' : null),
                        TextEntry::make('invoiced_year')
                            ->label(fn (): string => __('erp.finance.clients.invoiced_year', ['year' => today()->year]))
                            ->state(fn (Client $record): string => Invoice::money(
                                (float) $record->invoices()->booked()->where('type', InvoiceType::Invoice)->whereYear('issue_date', today()->year)->sum('subtotal')
                                - (float) $record->invoices()->booked()->where('type', InvoiceType::CreditNote)->whereYear('issue_date', today()->year)->sum('subtotal')
                            ))
                            ->helperText(__('erp.finance.amounts_excl')),
                    ]),
                TextEntry::make('internal_memo')
                    ->label(__('erp.finance.clients.internal_memo'))
                    ->visible(fn (Client $record): bool => filled($record->internal_memo) && (bool) auth()->user()?->isAdmin())
                    ->columnSpanFull(),
            ]);
    }
}
