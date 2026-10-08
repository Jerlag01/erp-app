@php
    $figures = $this->figures();
    $totals = $figures['totals'];
    $previousTotals = $figures['previousTotals'];
    $money = fn ($amount) => \App\Models\Invoice::money($amount);
    // One scale for both charts, so this year and last year can be compared bar for bar.
    $max = max(1, collect([$figures['current'], $figures['previous'] ?? []])->flatten(1)->flatMap(fn ($row) => [$row['invoices'], $row['credit_notes'], $row['expenses']])->max());
    $tiles = [
        ['key' => 'income', 'label' => __('erp.finance.income'), 'help' => __('erp.finance.statistics.income_help')],
        ['key' => 'expenses', 'label' => __('erp.finance.expenses_received'), 'help' => __('erp.finance.statistics.expenses_help')],
        ['key' => 'result', 'label' => __('erp.finance.result'), 'help' => __('erp.finance.statistics.result_help')],
        ['key' => 'invoices', 'label' => __('erp.resources.invoice.plural'), 'help' => null],
        ['key' => 'credit_notes', 'label' => __('erp.resources.credit_note.plural'), 'help' => null],
    ];
@endphp
<x-filament-panels::page>
    @include('finance.styles')
    <style>
        .erp-fin-kpis { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr)); }
        .erp-fin-kpi { border: 1px solid rgb(148 163 184 / .3); border-radius: .75rem; padding: .875rem 1rem; }
        .erp-fin-kpi .label { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; opacity: .7; }
        .erp-fin-kpi .value { font-size: 1.5rem; font-weight: 800; margin-top: .25rem; font-variant-numeric: tabular-nums; }
        .erp-fin-kpi .prev { font-size: .8125rem; opacity: .75; margin-top: .25rem; }
        .erp-fin-kpi.is-main .value { font-size: 1.75rem; }
    </style>

    <div class="erp-fin-filters" data-finance-filters>
        <label>{{ __('erp.incidents.from') }} <input type="date" class="erp-fin-date" wire:model.live="from"></label>
        <label>{{ __('erp.incidents.until') }} <input type="date" class="erp-fin-date" wire:model.live="until"></label>
        <div style="display: flex; flex-wrap: wrap; gap: .5rem;">
            @foreach (['year', 'quarter', 'last_12', 'last_year'] as $preset)
                <x-filament::button size="sm" color="gray" wire:click="preset('{{ $preset }}')">{{ __("erp.finance.statistics.presets.{$preset}") }}</x-filament::button>
            @endforeach
        </div>
        <label class="erp-fin-toggle"><input type="checkbox" wire:model.live="vat"> {{ __('erp.finance.vat_included') }}</label>
        <label class="erp-fin-toggle"><input type="checkbox" wire:model.live="compare"> {{ __('erp.finance.statistics.compare') }}</label>
        <div wire:loading.delay class="erp-fin-muted">{{ __('erp.calendar.loading') }}</div>
    </div>

    <div class="erp-fin-kpis" data-finance-kpis>
        @foreach ($tiles as $index => $tile)
            @php($value = $totals[$tile['key']])
            <div @class(['erp-fin-kpi', 'is-main' => $index < 3]) title="{{ $tile['help'] }}">
                <div class="label">{{ $tile['label'] }}</div>
                <div class="value">{{ $money($value) }}</div>
                @if ($previousTotals)
                    @php($change = \App\Support\FinanceFigures::change($value, $previousTotals[$tile['key']]))
                    <div class="prev">
                        @if ($change !== null)
                            <strong>{{ $change > 0 ? '▲' : ($change < 0 ? '▼' : '■') }} {{ number_format(abs($change), 1, ',', '.') }}%</strong>
                        @endif
                        {{ __('erp.finance.statistics.previous', ['amount' => $money($previousTotals[$tile['key']])]) }}
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <x-filament::section :heading="__('erp.finance.statistics.per_month')" :description="$vat ? __('erp.finance.amounts_incl') : __('erp.finance.amounts_excl')">
        @include('finance.chart', ['months' => $figures['current'], 'max' => $max, 'id' => 'current'])
    </x-filament::section>

    @if ($figures['previous'])
        <x-filament::section :heading="__('erp.finance.statistics.previous_year_chart')" :description="__('erp.finance.statistics.same_scale')">
            @include('finance.chart', ['months' => $figures['previous'], 'max' => $max, 'id' => 'previous'])
        </x-filament::section>
    @endif

    {{-- The same numbers as a table (also for screen readers and print). --}}
    <x-filament::section :heading="__('erp.finance.statistics.table')" collapsible collapsed>
        <div style="overflow-x: auto;">
            <table class="erp-fin-table">
                <thead>
                    <tr>
                        <th>{{ __('erp.finance.month') }}</th>
                        <th class="num">{{ __('erp.resources.invoice.plural') }}</th>
                        <th class="num">{{ __('erp.resources.credit_note.plural') }}</th>
                        <th class="num">{{ __('erp.finance.expenses_received') }}</th>
                        @if ($figures['previous'])<th class="num">{{ __('erp.finance.statistics.income_previous') }}</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($figures['current'] as $key => $row)
                        @php($previousRow = $figures['previous'] ? array_values($figures['previous'])[$loop->index] ?? null : null)
                        <tr>
                            <td style="text-transform: capitalize;">{{ $row['month']->locale(app()->getLocale())->translatedFormat('M Y') }}</td>
                            <td class="num">{{ $money($row['invoices']) }}</td>
                            <td class="num">{{ $money($row['credit_notes']) }}</td>
                            <td class="num">{{ $money($row['expenses']) }}</td>
                            @if ($figures['previous'])<td class="num">{{ $previousRow ? $money($previousRow['invoices'] - $previousRow['credit_notes']) : '—' }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
