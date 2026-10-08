<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client', 'project']))
            ->defaultSort('issue_date', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('erp.finance.pdf.number'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight('bold')
                    ->placeholder(__('erp.finance.pdf.draft'))
                    ->description(fn (Invoice $record): ?string => $record->subject)
                    ->searchable(['number', 'subject', 'client_reference'])
                    ->sortable(),
                TextColumn::make('client.short_name')
                    ->label(__('erp.resources.client.singular'))
                    ->description(fn (Invoice $record): string => $record->client->company_name)
                    ->searchable(['short_name', 'company_name']),
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
                    ->money('EUR', locale: 'nl_BE')
                    ->alignEnd()
                    ->summarize(Sum::make()->money('EUR', locale: 'nl_BE')),
                TextColumn::make('total')
                    ->label(__('erp.finance.incl_vat'))
                    ->money('EUR', locale: 'nl_BE')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->money('EUR', locale: 'nl_BE')),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->icon(fn (Invoice $record): ?Heroicon => $record->reminder_level > 0 && ! $record->isPaid() ? Heroicon::OutlinedBellAlert : null)
                    ->tooltip(fn (Invoice $record): ?string => $record->reminder_level > 0 ? __('erp.finance.invoices.reminders_sent', ['count' => $record->reminder_level]) : null),
                TextColumn::make('peppol_status')
                    ->label('Peppol')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => $state ? __("erp.finance.peppol.{$state}") : '—')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label(__('erp.resources.client.singular'))
                    ->options(fn (): array => Client::query()->orderBy('short_name')->get()->mapWithKeys(fn (Client $client): array => [$client->id => $client->label()])->all())
                    ->searchable(),
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
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
