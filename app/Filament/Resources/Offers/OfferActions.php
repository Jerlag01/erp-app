<?php

namespace App\Filament\Resources\Offers;

use App\Actions\Finance\OfferPdf;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Offer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * The actions of an offer: its PDF, the copy in the project's offers and the invoice made from it.
 */
class OfferActions
{
    public static function pdf(): Action
    {
        return Action::make('pdf')
            ->label('PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (Offer $record): bool => Gate::allows('generatePdf', $record))
            ->url(fn (Offer $record): string => route('finance.offer-pdf', ['offer' => $record]), shouldOpenInNewTab: true);
    }

    public static function saveToProject(): Action
    {
        return Action::make('saveToProject')
            ->label(__('erp.finance.offers.save_to_project'))
            ->icon(Heroicon::OutlinedFolderArrowDown)
            ->color('gray')
            ->visible(fn (Offer $record): bool => $record->project_id !== null && Gate::allows('generatePdf', $record) && Gate::allows('manageFinance', $record->project))
            ->requiresConfirmation()
            ->modalDescription(__('erp.finance.offers.save_to_project_help'))
            ->action(function (Offer $record): void {
                app(OfferPdf::class)->saveToProject($record, auth()->user());
                Notification::make()->title(__('erp.finance.offers.saved_to_project', ['reference' => $record->project->reference]))->success()->send();
            });
    }

    public static function createInvoice(): Action
    {
        return Action::make('createInvoice')
            ->label(__('erp.finance.offers.create_invoice'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('success')
            ->visible(fn (Offer $record): bool => Gate::allows('createInvoice', $record))
            ->requiresConfirmation()
            ->modalDescription(__('erp.finance.offers.create_invoice_help'))
            ->action(function (Offer $record, Action $action): void {
                $invoice = $record->createInvoice(auth()->user());
                Notification::make()->title(__('erp.finance.offers.invoice_created'))->success()->send();
                $action->redirect(InvoiceResource::getUrl('edit', ['record' => $invoice]));
            });
    }
}
