<?php

namespace App\Filament\Resources\Offers\Tables;

use App\Enums\OfferStatus;
use App\Filament\Resources\Offers\OfferActions;
use App\Models\Offer;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OffersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client', 'project', 'invoice']))
            ->defaultSort('offer_date', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('erp.finance.pdf.number'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight('bold')
                    ->description(fn (Offer $record): ?string => $record->subject)
                    ->searchable(['number', 'subject', 'client_reference', 'our_reference'])
                    ->sortable(),
                TextColumn::make('client.short_name')
                    ->label(__('erp.resources.client.singular'))
                    ->placeholder('—')
                    ->searchable(['short_name', 'company_name']),
                TextColumn::make('project.reference')
                    ->label(__('erp.resources.project.singular'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('offer_date')
                    ->label(__('erp.finance.offers.date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('total')
                    ->label(__('erp.finance.excl_vat'))
                    ->money('EUR', locale: 'nl_BE')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->description(fn (Offer $record): ?string => $record->invoice ? __('erp.finance.offers.invoiced', ['number' => $record->invoice->number ?? __('erp.finance.pdf.draft')]) : null),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('erp.fields.status'))->options(OfferStatus::class),
            ])
            ->recordActions([
                OfferActions::pdf(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
