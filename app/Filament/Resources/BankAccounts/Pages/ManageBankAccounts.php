<?php

namespace App\Filament\Resources\BankAccounts\Pages;

use App\Filament\Resources\BankAccounts\BankAccountResource;
use App\Filament\Resources\BankTransactions\Actions\BankActions;
use App\Services\Banking\PontoClient;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBankAccounts extends ManageRecords
{
    protected static string $resource = BankAccountResource::class;

    public function getSubheading(): ?string
    {
        if (PontoClient::isConnected()) {
            $state = PontoClient::state();

            return __('erp.banking.ponto.connected_since', ['date' => isset($state['connected_at']) ? now()->parse($state['connected_at'])->format('d/m/Y') : '—']);
        }

        return PontoClient::isConfigured() ? __('erp.banking.ponto.not_connected') : __('erp.banking.ponto.not_configured');
    }

    protected function getHeaderActions(): array
    {
        return [
            BankActions::connectPonto(),
            BankActions::syncPonto(),
            BankActions::importCoda(),
            CreateAction::make()->slideOver()->mutateDataUsing(fn (array $data): array => [...$data, 'provider' => 'coda']),
            BankActions::disconnectPonto(),
        ];
    }
}
