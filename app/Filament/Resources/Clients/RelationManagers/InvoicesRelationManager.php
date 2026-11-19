<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Filament\Resources\CreditNotes\CreditNoteResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Policies\InvoicePolicy;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The client's invoices and credit notes in one list, each only for who may see that kind. Amounts of
 * credit notes count negative, so the totals are what the client owes or was invoiced.
 */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user !== null && (InvoicePolicy::allows($user, InvoiceType::Invoice, 'view') || InvoicePolicy::allows($user, InvoiceType::CreditNote, 'view'));
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.finance.clients.documents');
    }

    /**
     * @return list<string>
     */
    protected static function visibleTypes(): array
    {
        $user = auth()->user();

        return array_values(array_map(fn (InvoiceType $type): string => $type->value, array_filter(InvoiceType::cases(), fn (InvoiceType $type): bool => $user !== null && InvoicePolicy::allows($user, $type, 'view'))));
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('project')->whereIn('type', self::visibleTypes()))
            ->defaultSort('issue_date', 'desc')
            ->emptyStateHeading(__('erp.finance.clients.no_documents'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->recordUrl(fn (Invoice $record): string => $record->isCreditNote()
                ? CreditNoteResource::getUrl('view', ['record' => $record])
                : InvoiceResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('type')
                    ->label(__('erp.finance.clients.document_type'))
                    ->badge()
                    ->color(fn (Invoice $record): string => $record->isCreditNote() ? 'warning' : 'primary'),
                TextColumn::make('number')
                    ->label(__('erp.finance.pdf.number'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight('bold')
                    ->placeholder(__('erp.finance.pdf.draft'))
                    ->description(fn (Invoice $record): ?string => $record->subject)
                    ->searchable(['number', 'subject', 'client_reference'])
                    ->sortable(),
                TextColumn::make('project.reference')
                    ->label(__('erp.resources.project.singular'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('issue_date')
                    ->label(__('erp.finance.invoices.issue_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label(__('erp.finance.invoices.due_date'))
                    ->date('d/m/Y')
                    ->color(fn (Invoice $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->description(fn (Invoice $record): ?string => $record->isOverdue() ? __('erp.finance.invoices.days_overdue', ['days' => $record->daysOverdue()]) : null)
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('subtotal')
                    ->label(__('erp.finance.excl_vat'))
                    ->state(fn (Invoice $record): float => $record->type->sign() * (float) $record->subtotal)
                    ->money('EUR', locale: 'nl_BE')
                    ->alignEnd(),
                TextColumn::make('total')
                    ->label(__('erp.finance.incl_vat'))
                    ->state(fn (Invoice $record): float => $record->type->sign() * (float) $record->total)
                    ->money('EUR', locale: 'nl_BE')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->icon(fn (Invoice $record): ?Heroicon => $record->reminder_level > 0 && ! $record->isPaid() ? Heroicon::OutlinedBellAlert : null)
                    ->tooltip(fn (Invoice $record): ?string => $record->reminder_level > 0 ? __('erp.finance.invoices.reminders_sent', ['count' => $record->reminder_level]) : null),
                TextColumn::make('sent_to')
                    ->label(__('erp.finance.invoices.send_to'))
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('erp.finance.clients.document_type'))
                    ->options(InvoiceType::class),
                SelectFilter::make('status')
                    ->label(__('erp.fields.status'))
                    ->options(InvoiceStatus::class)
                    ->multiple(),
                SelectFilter::make('year')
                    ->label(__('erp.fields.year'))
                    ->options(fn (): array => collect(range((int) date('Y'), (int) date('Y') - 6))->mapWithKeys(fn (int $year): array => [$year => $year])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $year) => $query->whereYear('issue_date', $year))),
                Filter::make('overdue')
                    ->label(__('erp.finance.invoices.overdue'))
                    ->query(fn (Builder $query) => $query->outstanding()->whereDate('due_date', '<', today())),
            ]);
    }
}
