<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('erp.finance.all')),
            'draft' => Tab::make(__('erp.enums.invoice_status.draft'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', InvoiceStatus::Draft)),
            'open' => Tab::make(__('erp.finance.invoices.open'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [InvoiceStatus::Final, InvoiceStatus::Sent])),
            'overdue' => Tab::make(__('erp.finance.invoices.overdue'))
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', InvoiceStatus::Sent)->whereDate('due_date', '<', today())),
            'paid' => Tab::make(__('erp.enums.invoice_status.paid'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', InvoiceStatus::Paid)),
        ];
    }
}
