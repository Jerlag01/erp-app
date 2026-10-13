<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Actions\Finance\CreateCreditNote;
use App\Actions\Finance\SendInvoice;
use App\Actions\Finance\SendPaymentReminder;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\CreditNotes\CreditNoteResource;
use App\Models\Invoice;
use App\Policies\InvoicePolicy;
use App\Services\Peppol\PeppolGateway;
use App\Support\PdfAccess;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * The header actions of an invoice or credit note: finalise, send, mark as paid, remind, credit,
 * and download as PDF or Peppol UBL.
 */
class InvoiceActions
{
    /**
     * @return list<Action|ActionGroup>
     */
    public static function make(): array
    {
        return [
            EditAction::make(),
            Action::make('finalize')
                ->label(__('erp.finance.invoices.finalize'))
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('primary')
                ->visible(fn (Invoice $record): bool => Gate::allows('finalize', $record))
                ->authorize(fn (Invoice $record): bool => Gate::allows('finalize', $record))
                ->requiresConfirmation()
                ->modalDescription(__('erp.finance.invoices.finalize_help'))
                ->action(function (Invoice $record): void {
                    $record->finalize();
                    Notification::make()->title(__('erp.finance.invoices.finalized', ['number' => $record->number]))->success()->send();
                }),
            Action::make('send')
                ->label(fn (Invoice $record): string => $record->sent_at ? __('erp.finance.invoices.send_again') : __('erp.finance.invoices.send'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('primary')
                ->visible(fn (Invoice $record): bool => Gate::allows('send', $record))
                ->authorize(fn (Invoice $record): bool => Gate::allows('send', $record))
                ->fillForm(fn (Invoice $record): array => ['email' => $record->client->invoiceEmail(), 'peppol' => (bool) $record->client->peppolParticipant()])
                ->schema(fn (Invoice $record): array => [
                    TextInput::make('email')
                        ->label(__('erp.finance.invoices.send_to'))
                        ->email()
                        ->required(fn (): bool => ! settings()->get('finance.peppol_enabled') || ! $record->client->peppolParticipant()),
                    Toggle::make('peppol')
                        ->label(__('erp.finance.invoices.via_peppol', ['id' => $record->client->peppolParticipant() ?? '—']))
                        ->helperText(PeppolGateway::isConnected() ? null : __('erp.finance.invoices.peppol_not_connected'))
                        ->visible(fn (): bool => (bool) settings()->get('finance.peppol_enabled') && $record->client->peppolParticipant() !== null),
                ])
                ->action(function (Invoice $record, array $data, SendInvoice $send): void {
                    try {
                        $result = $send->handle($record, $data['email'] ?? null, (bool) ($data['peppol'] ?? false));
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->title(__('erp.finance.invoices.send_failed'))->body($exception->getMessage())->danger()->persistent()->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('erp.finance.invoices.sent_ok'))
                        ->body(implode(' · ', array_filter([
                            $result['email'] ? __('erp.finance.invoices.sent_mail', ['email' => $result['email']]) : null,
                            $result['peppol'] ? 'Peppol: '.__("erp.finance.peppol.{$result['peppol']}") : null,
                        ])))
                        ->success()
                        ->send();
                }),
            Action::make('markPaid')
                ->label(__('erp.finance.invoices.mark_paid'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->visible(fn (Invoice $record): bool => Gate::allows('markPaid', $record))
                ->authorize(fn (Invoice $record): bool => Gate::allows('markPaid', $record))
                ->schema([
                    DatePicker::make('paid_on')->label(__('erp.finance.invoices.paid_on'))->native(false)->displayFormat('d/m/Y')->default(today())->maxDate(today())->required(),
                ])
                ->action(fn (Invoice $record, array $data) => $record->forceFill(['status' => InvoiceStatus::Paid, 'paid_on' => $data['paid_on']])->save())
                ->successNotificationTitle(__('erp.finance.invoices.marked_paid')),
            Action::make('remind')
                ->label(__('erp.finance.invoices.send_reminder'))
                ->icon(Heroicon::OutlinedBellAlert)
                ->color('warning')
                ->visible(fn (Invoice $record): bool => $record->isOverdue() && $record->reminder_level < SendPaymentReminder::MAX_LEVEL && Gate::allows('send', $record))
                ->authorize(fn (Invoice $record): bool => Gate::allows('send', $record))
                ->requiresConfirmation()
                ->modalDescription(fn (Invoice $record): string => __('erp.finance.invoices.reminder_help', ['level' => $record->reminder_level + 1, 'email' => $record->client->invoiceEmail() ?? '—']))
                ->action(function (Invoice $record, SendPaymentReminder $reminder): void {
                    try {
                        $level = $reminder->handle($record, auth()->user());
                        Notification::make()->title(__('erp.finance.invoices.reminder_sent', ['level' => $level]))->success()->send();
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->title(__('erp.finance.invoices.send_failed'))->body($exception->getMessage())->danger()->send();
                    }
                }),
            Action::make('creditNote')
                ->label(__('erp.finance.invoices.create_credit_note'))
                ->icon(Heroicon::OutlinedReceiptRefund)
                ->color('gray')
                ->visible(fn (Invoice $record): bool => Gate::allows('credit', $record))
                ->authorize(fn (Invoice $record): bool => Gate::allows('credit', $record))
                ->requiresConfirmation()
                ->modalDescription(__('erp.finance.invoices.create_credit_note_help'))
                ->action(fn (Invoice $record, CreateCreditNote $create) => redirect(CreditNoteResource::getUrl('edit', ['record' => $create->handle($record, auth()->user())]))),
            ActionGroup::make([
                Action::make('pdf')
                    ->label('PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->visible(fn (Invoice $record): bool => PdfAccess::canGenerate(auth()->user(), InvoicePolicy::area($record->type)))
                    ->url(fn (Invoice $record): string => route('finance.invoice-document', ['invoice' => $record, 'format' => 'pdf']), shouldOpenInNewTab: true),
                Action::make('ubl')
                    ->label(__('erp.finance.invoices.download_ubl'))
                    ->icon(Heroicon::OutlinedCodeBracket)
                    ->visible(fn (Invoice $record): bool => ! $record->isDraft())
                    ->url(fn (Invoice $record): string => route('finance.invoice-document', ['invoice' => $record, 'format' => 'ubl'])),
                DeleteAction::make(),
            ]),
        ];
    }
}
