<?php

namespace App\Filament\Resources\BankTransactions\Actions;

use App\Actions\Banking\ImportBankTransactions;
use App\Actions\Banking\MatchBankTransactions;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Services\Banking\PontoClient;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * The header actions shared by the bank accounts and bank movements pages: import CODA files,
 * read Ponto now, and check the movements against the invoices again.
 */
class BankActions
{
    public static function importCoda(): Action
    {
        return Action::make('importCoda')
            ->label(__('erp.banking.import_coda'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->visible(fn (): bool => Gate::allows('import', BankTransaction::class))
            ->authorize(fn (): bool => Gate::allows('import', BankTransaction::class))
            ->modalDescription(__('erp.banking.import_coda_help'))
            ->schema([
                FileUpload::make('files')
                    ->hiddenLabel()
                    ->multiple()
                    ->disk('local')
                    ->directory('banking/uploads')
                    ->maxSize(20480)
                    ->required(),
            ])
            ->action(function (array $data, ImportBankTransactions $import): void {
                $totals = ['accounts' => 0, 'added' => 0, 'matched' => 0];
                $problems = [];

                foreach ((array) $data['files'] as $path) {
                    try {
                        $result = $import->fromCoda((string) Storage::disk('local')->get($path));

                        foreach ($totals as $key => $value) {
                            $totals[$key] = $value + $result[$key];
                        }
                    } catch (InvalidArgumentException $exception) {
                        $problems[] = basename($path).': '.__('erp.banking.not_coda');
                    } finally {
                        Storage::disk('local')->delete($path);
                    }
                }

                Notification::make()
                    ->title(__('erp.banking.synced', $totals))
                    ->body($problems ? implode("\n", $problems) : null)
                    ->status($problems ? 'warning' : 'success')
                    ->send();
            });
    }

    public static function syncPonto(): Action
    {
        return Action::make('syncPonto')
            ->label(__('erp.banking.sync_now'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (): bool => PontoClient::isConnected() && Gate::allows('import', BankTransaction::class))
            ->authorize(fn (): bool => PontoClient::isConnected() && Gate::allows('import', BankTransaction::class))
            ->action(function (ImportBankTransactions $import): void {
                try {
                    $result = $import->fromPonto();
                    Notification::make()
                        ->title(__('erp.banking.synced', $result))
                        ->body($result['errors'] ? implode("\n", $result['errors']) : null)
                        ->status($result['errors'] ? 'warning' : 'success')
                        ->send();
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title(__('erp.banking.sync_failed'))->body($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function rematch(): Action
    {
        return Action::make('rematch')
            ->label(__('erp.banking.rematch'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->visible(fn (): bool => Gate::allows('import', BankTransaction::class))
            ->authorize(fn (): bool => Gate::allows('import', BankTransaction::class))
            ->action(function (MatchBankTransactions $matcher): void {
                Notification::make()->title(__('erp.banking.rematched', ['count' => $matcher->run()]))->success()->send();
            });
    }

    public static function connectPonto(): Action
    {
        return Action::make('connectPonto')
            ->label(fn (): string => PontoClient::isConnected() ? __('erp.banking.ponto.reconnect') : __('erp.banking.ponto.connect'))
            ->icon(Heroicon::OutlinedLink)
            ->color(fn (): string => PontoClient::isConnected() ? 'gray' : 'primary')
            ->visible(fn (): bool => PontoClient::isConfigured() && Gate::allows('create', BankAccount::class))
            ->url(fn (): string => route('banking.ponto.connect'));
    }

    public static function disconnectPonto(): Action
    {
        return Action::make('disconnectPonto')
            ->label(__('erp.banking.ponto.disconnect'))
            ->icon(Heroicon::OutlinedLinkSlash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('erp.banking.ponto.disconnect_help'))
            ->visible(fn (): bool => PontoClient::isConnected() && Gate::allows('create', BankAccount::class))
            ->authorize(fn (): bool => Gate::allows('create', BankAccount::class))
            ->action(function (PontoClient $ponto): void {
                $ponto->disconnect();
                Notification::make()->title(__('erp.banking.ponto.disconnected'))->success()->send();
            });
    }
}
