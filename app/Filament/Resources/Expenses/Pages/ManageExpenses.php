<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Actions\Finance\ImportInvoiceXml;
use App\Enums\ExpenseStatus;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Invoices\Actions\ImportInvoiceXmlAction;
use App\Models\Expense;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // UBL files from suppliers (or Doccle / the access point portal), several at once or in a ZIP.
            ImportInvoiceXmlAction::make(ImportInvoiceXml::PURCHASES),
            CreateAction::make()
                ->slideOver()
                ->mutateDataUsing(fn (array $data): array => [...$data, 'created_by' => auth()->id()]),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [];

        foreach (ExpenseStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->badge($status === ExpenseStatus::Pending ? (Expense::query()->where('status', $status)->count() ?: null) : null)
                ->badgeColor($status->getColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        $tabs['all'] = Tab::make(__('erp.finance.all'));

        return $tabs;
    }
}
