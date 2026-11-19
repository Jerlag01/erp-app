<?php

namespace App\Filament\Resources\BankTransactions;

use App\Actions\Banking\MatchBankTransactions;
use App\Enums\BankTransactionStatus;
use App\Enums\NavigationGroup;
use App\Filament\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Filament\Resources\CreditNotes\CreditNoteResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Invoice;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use UnitEnum;

/**
 * The bank movements of all read accounts, to check which invoices were paid: money in that names an
 * open invoice's structured communication (or number) with its exact amount marks it paid; the rest
 * is linked by hand from suggestions, or ignored. Incoming invoices work the same way for money out.
 */
class BankTransactionResource extends Resource
{
    protected static ?string $model = BankTransaction::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'bank-transactions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 7;

    public static function getModelLabel(): string
    {
        return __('erp.resources.bank_transaction.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.bank_transaction.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = BankTransaction::query()->where('status', BankTransactionStatus::Unmatched)->where('amount', '>', 0)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('erp.banking.unmatched_incoming');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['account', 'matchable', 'matcher']))
            ->defaultSort('booking_date', 'desc')
            ->columns([
                TextColumn::make('booking_date')->label(__('erp.banking.booking_date'))->date('d/m/Y')->sortable(),
                TextColumn::make('counterparty_name')
                    ->label(__('erp.banking.counterparty'))
                    ->description(fn (BankTransaction $record): ?string => $record->counterparty_iban ? trim(chunk_split($record->counterparty_iban, 4, ' ')) : null)
                    ->placeholder('—')
                    ->searchable(['counterparty_name', 'counterparty_iban', 'remittance']),
                TextColumn::make('remittance')
                    ->label(__('erp.banking.remittance'))
                    ->limit(60)
                    ->tooltip(fn (BankTransaction $record): ?string => $record->remittance)
                    ->fontFamily(fn (BankTransaction $record): ?FontFamily => $record->structured_reference ? FontFamily::Mono : null)
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('amount')
                    ->label(__('erp.banking.amount'))
                    ->money(fn (BankTransaction $record): string => $record->currency, locale: 'nl_BE')
                    ->color(fn (BankTransaction $record): string => $record->isIncoming() ? 'success' : 'danger')
                    ->weight('bold')
                    ->fontFamily(FontFamily::Mono)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->description(fn (BankTransaction $record): ?string => self::documentLabel($record)),
                TextColumn::make('account.name')
                    ->label(__('erp.resources.bank_account.singular'))
                    ->description(fn (BankTransaction $record): ?string => $record->account?->formattedIban())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(fn (BankTransaction $record): ?string => self::documentUrl($record))
            ->filters([
                SelectFilter::make('bank_account_id')->label(__('erp.resources.bank_account.singular'))->relationship('account', 'name'),
                SelectFilter::make('direction')
                    ->label(__('erp.banking.direction'))
                    ->options(['in' => __('erp.banking.incoming'), 'out' => __('erp.banking.outgoing')])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'in' => $query->where('amount', '>', 0),
                        'out' => $query->where('amount', '<', 0),
                        default => $query,
                    }),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('erp.banking.from'))->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label(__('erp.banking.until'))->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('booking_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('booking_date', '<=', $date))),
            ])
            ->recordActions([
                Action::make('link')
                    ->label(__('erp.banking.link'))
                    ->icon(Heroicon::OutlinedLink)
                    ->color('primary')
                    ->visible(fn (BankTransaction $record): bool => $record->status === BankTransactionStatus::Unmatched && Gate::allows('match', $record))
                    ->authorize(fn (BankTransaction $record): bool => Gate::allows('match', $record))
                    ->modalHeading(fn (BankTransaction $record): string => __('erp.banking.link_heading', ['amount' => Invoice::money($record->amount)]))
                    ->modalDescription(fn (BankTransaction $record): string => $record->isIncoming() ? __('erp.banking.link_help_in') : __('erp.banking.link_help_out'))
                    ->schema([
                        Select::make('document')
                            ->label(__('erp.banking.document'))
                            ->options(fn (BankTransaction $record): array => app(MatchBankTransactions::class)->options($record))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (BankTransaction $record, array $data, MatchBankTransactions $matcher): void {
                        try {
                            $matcher->linkByKey($record, (string) $data['document'], auth()->user());
                            Notification::make()->title(__('erp.banking.linked'))->success()->send();
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
                Action::make('ignore')
                    ->label(__('erp.banking.ignore'))
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('gray')
                    ->visible(fn (BankTransaction $record): bool => $record->status === BankTransactionStatus::Unmatched && Gate::allows('match', $record))
                    ->authorize(fn (BankTransaction $record): bool => Gate::allows('match', $record))
                    ->action(fn (BankTransaction $record, MatchBankTransactions $matcher) => $matcher->ignore($record, auth()->user())),
                Action::make('unlink')
                    ->label(fn (BankTransaction $record): string => $record->status === BankTransactionStatus::Ignored ? __('erp.banking.restore') : __('erp.banking.unlink'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->requiresConfirmation(fn (BankTransaction $record): bool => $record->status === BankTransactionStatus::Matched)
                    ->modalDescription(__('erp.banking.unlink_help'))
                    ->visible(fn (BankTransaction $record): bool => $record->status !== BankTransactionStatus::Unmatched && Gate::allows('match', $record))
                    ->authorize(fn (BankTransaction $record): bool => Gate::allows('match', $record))
                    ->action(fn (BankTransaction $record, MatchBankTransactions $matcher) => $matcher->unlink($record)),
            ]);
    }

    /**
     * "Invoice 2026-0012" / "Incoming: Supplier 123" for a linked movement; who linked it otherwise.
     */
    public static function documentLabel(BankTransaction $record): ?string
    {
        $document = $record->matchable;

        $label = match (true) {
            $document instanceof Invoice => $document->number,
            $document instanceof Expense => $document->supplier_name.' '.$document->supplier_invoice_number,
            default => null,
        };

        if ($record->status === BankTransactionStatus::Unmatched) {
            return null;
        }

        return trim(($label ?? '').' · '.($record->matcher?->name ?? __('erp.banking.automatic')), ' ·');
    }

    public static function documentUrl(BankTransaction $record): ?string
    {
        $document = $record->matchable;

        return match (true) {
            $document instanceof Invoice && Gate::allows('view', $document) => ($document->isCreditNote() ? CreditNoteResource::class : InvoiceResource::class)::getUrl('view', ['record' => $document]),
            $document instanceof Expense && Gate::allows('view', $document) => ExpenseResource::getUrl('index', ['search' => $document->supplier_invoice_number ?: $document->supplier_name]),
            default => null,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankTransactions::route('/'),
        ];
    }
}
