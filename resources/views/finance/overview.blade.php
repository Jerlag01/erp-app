@php
    $months = $this->months();
    $totals = \App\Support\FinanceFigures::totals($months);
    $money = fn ($amount) => \App\Models\Invoice::money($amount);
    $canInvoices = auth()->user()->hasPermission('invoices.view');
    $canCredit = auth()->user()->hasPermission('credit_notes.view');
    $canExpenses = auth()->user()->hasPermission('expenses.view');
@endphp
<x-filament-panels::page>
    @include('finance.styles')

    <div class="erp-fin-filters">
        <label>{{ __('erp.fields.year') }}
            <select wire:model.live="year" class="erp-fin-select">
                @foreach ($this->years() as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </label>
        <label class="erp-fin-toggle"><input type="checkbox" wire:model.live="vat"> {{ __('erp.finance.vat_included') }}</label>
        <span class="erp-fin-muted">{{ $vat ? __('erp.finance.amounts_incl') : __('erp.finance.amounts_excl') }}</span>
    </div>

    <x-filament::section>
        <div style="overflow-x: auto;">
            <table class="erp-fin-table" data-finance-months>
                <thead>
                    <tr>
                        <th>{{ __('erp.finance.month') }}</th>
                        <th class="num">{{ __('erp.resources.invoice.plural') }}</th>
                        <th class="num">{{ __('erp.resources.credit_note.plural') }}</th>
                        <th class="num">{{ __('erp.finance.income') }}</th>
                        <th class="num">{{ __('erp.finance.expenses_received') }}</th>
                        <th class="num">{{ __('erp.finance.result') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($months as $key => $row)
                        <tr @class(['is-selected' => $key === $month]) wire:click="$set('month', '{{ $key }}')" style="cursor: pointer;">
                            <td style="text-transform: capitalize;">{{ $row['month']->locale(app()->getLocale())->translatedFormat('F') }}</td>
                            <td class="num">{{ $money($row['invoices']) }} <span class="erp-fin-count">{{ $row['invoice_count'] }}</span></td>
                            <td class="num">{{ $money($row['credit_notes']) }} <span class="erp-fin-count">{{ $row['credit_note_count'] }}</span></td>
                            <td class="num">{{ $money($row['invoices'] - $row['credit_notes']) }}</td>
                            <td class="num">{{ $money($row['expenses']) }} <span class="erp-fin-count">{{ $row['expense_count'] }}</span></td>
                            <td class="num" @style(['color: rgb(220 38 38)' => $row['invoices'] - $row['credit_notes'] - $row['expenses'] < 0])>{{ $money($row['invoices'] - $row['credit_notes'] - $row['expenses']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="is-total">
                        <td>{{ __('erp.finance.year_total', ['year' => $year]) }}</td>
                        <td class="num">{{ $money($totals['invoices']) }}</td>
                        <td class="num">{{ $money($totals['credit_notes']) }}</td>
                        <td class="num">{{ $money($totals['income']) }}</td>
                        <td class="num">{{ $money($totals['expenses']) }}</td>
                        <td class="num">{{ $money($totals['result']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="erp-fin-muted" style="margin-top: .5rem;">{{ __('erp.finance.overview.click_month') }}</p>
    </x-filament::section>

    @php($selected = \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->locale(app()->getLocale()))
    <h2 style="font-size: 1.125rem; font-weight: 700; text-transform: capitalize;">{{ $selected->translatedFormat('F Y') }}</h2>

    <div class="erp-fin-grid">
        @foreach ([
            ['title' => __('erp.resources.invoice.plural'), 'items' => $canInvoices ? $this->documents(\App\Enums\InvoiceType::Invoice) : null, 'resource' => \App\Filament\Resources\Invoices\InvoiceResource::class],
            ['title' => __('erp.resources.credit_note.plural'), 'items' => $canCredit ? $this->documents(\App\Enums\InvoiceType::CreditNote) : null, 'resource' => \App\Filament\Resources\CreditNotes\CreditNoteResource::class],
        ] as $list)
            @continue($list['items'] === null)
            <x-filament::section :heading="$list['title']">
                @forelse ($list['items'] as $document)
                    <div class="erp-fin-doc">
                        <a href="{{ $list['resource']::getUrl('view', ['record' => $document]) }}" class="erp-fin-mono">{{ $document->number }}</a>
                        <span class="erp-fin-doc-name">{{ $document->client->short_name }}</span>
                        <span class="num">{{ $money($vat ? $document->total : $document->subtotal) }}</span>
                    </div>
                @empty
                    <p class="erp-fin-muted">{{ __('erp.finance.overview.none') }}</p>
                @endforelse
            </x-filament::section>
        @endforeach

        @if ($canExpenses)
            <x-filament::section :heading="__('erp.finance.expenses_received')">
                @forelse ($this->expenses() as $expense)
                    <div class="erp-fin-doc">
                        <span class="erp-fin-mono">{{ $expense->supplier_invoice_number }}</span>
                        <span class="erp-fin-doc-name">{{ $expense->supplier_name }} @if ($expense->status === \App\Enums\ExpenseStatus::Pending)<x-filament::badge color="warning" size="sm">{{ $expense->status->getLabel() }}</x-filament::badge>@endif</span>
                        <span class="num">{{ $money($expense->signedTotal($vat)) }}</span>
                    </div>
                @empty
                    <p class="erp-fin-muted">{{ __('erp.finance.overview.none') }}</p>
                @endforelse
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
