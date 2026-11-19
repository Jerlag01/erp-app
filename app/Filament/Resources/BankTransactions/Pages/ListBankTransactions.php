<?php

namespace App\Filament\Resources\BankTransactions\Pages;

use App\Enums\BankTransactionStatus;
use App\Filament\Resources\BankTransactions\Actions\BankActions;
use App\Filament\Resources\BankTransactions\BankTransactionResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBankTransactions extends ListRecords
{
    protected static string $resource = BankTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BankActions::syncPonto(),
            BankActions::importCoda(),
            BankActions::rematch(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [];

        foreach (BankTransactionStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        return [...$tabs, 'all' => Tab::make(__('erp.banking.all'))];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return BankTransactionStatus::Unmatched->value;
    }
}
