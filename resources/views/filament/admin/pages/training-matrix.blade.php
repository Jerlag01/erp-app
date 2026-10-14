{{-- Filament's precompiled CSS only ships its own classes, so layout here uses inline styles. --}}
@php
    $colors = [
        'valid' => 'background: rgb(22 163 74 / .14); color: rgb(22 163 74);',
        'soon' => 'background: rgb(217 119 6 / .2); color: rgb(217 119 6); font-weight: 700;',
        'expired' => 'background: rgb(220 38 38 / .18); color: rgb(220 38 38); font-weight: 700;',
        'since' => 'font-weight: 600;',
    ];
@endphp
<x-filament-panels::page>
    <style>
        .erp-tm-bar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: flex-end; }
        .erp-tm-pill { font-family: var(--erp-mono); font-size: .75rem; padding: .15rem .6rem; border-radius: 999px; }
        .erp-tm-wrap { overflow: auto; max-height: calc(100vh - 14rem); border: 1px solid rgb(148 163 184 / .3); border-radius: .75rem; }
        .erp-tm { border-collapse: separate; border-spacing: 0; font-size: .75rem; min-width: 100%; }
        .erp-tm th { position: sticky; top: 0; z-index: 2; background: var(--erp-surface, rgb(241 245 249)); font-family: var(--erp-mono); font-size: .625rem; letter-spacing: .06em; text-transform: uppercase; font-weight: 600; padding: .5rem .4rem; text-align: center; border-bottom: 1px solid rgb(148 163 184 / .4); vertical-align: bottom; min-width: 5.5rem; }
        .dark .erp-tm th { background: rgb(15 23 42); }
        .erp-tm th span { display: block; opacity: .7; font-weight: 400; }
        .erp-tm td { padding: .45rem .4rem; text-align: center; border-bottom: 1px solid rgb(148 163 184 / .2); font-family: var(--erp-mono); white-space: nowrap; }
        .erp-tm .sticky { position: sticky; left: 0; z-index: 1; background: var(--erp-surface, white); text-align: left; font-family: inherit; font-weight: 600; min-width: 9rem; white-space: normal; }
        .dark .erp-tm .sticky { background: rgb(17 24 39); }
        .erp-tm th.sticky { z-index: 3; }
        .erp-tm td.text { text-align: left; font-family: inherit; white-space: normal; min-width: 7rem; }
    </style>

    <div class="erp-tm-bar">
        <span class="erp-tm-pill" style="background: rgb(220 38 38 / .15); color: rgb(220 38 38);">{{ trans_choice('erp.training_matrix.expired', $matrix['expired'], ['count' => $matrix['expired']]) }}</span>
        <span class="erp-tm-pill" style="background: rgb(217 119 6 / .18); color: rgb(217 119 6);">{{ trans_choice('erp.training_matrix.soon', $matrix['soon'], ['count' => $matrix['soon'], 'months' => \App\Support\TrainingMatrix::SOON_MONTHS]) }}</span>
        <label style="display: inline-flex; gap: .4rem; align-items: center; font-size: .875rem;">
            <x-filament::input.checkbox wire:model.live="onlyProblems" />
            {{ __('erp.training_matrix.only_problems') }}
        </label>
        <div style="width: 16rem;">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('erp.training_matrix.search')" />
            </x-filament::input.wrapper>
        </div>
    </div>

    <div class="erp-tm-wrap">
        <table class="erp-tm">
            <thead>
                <tr>
                    <th class="sticky" style="text-align: left;">{{ __('erp.training_matrix.name') }}</th>
                    <th style="text-align: left;">{{ __('erp.training_matrix.function') }}</th>
                    <th style="min-width: 3rem;">{{ __('erp.employees.is_mentor') }}</th>
                    @foreach ($matrix['columns'] as $column)
                        <th>{{ $column['label'] }}<span>{{ $column['until'] ? __('erp.training_matrix.valid_until') : __('erp.training_matrix.since') }}</span></th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($matrix['rows'] as $row)
                    <tr wire:key="tm-{{ $row['employee']->id }}">
                        <td class="sticky">
                            <a href="{{ \App\Filament\Admin\Resources\Employees\EmployeeResource::getUrl('view', ['record' => $row['employee']]) }}" style="text-decoration: none;">{{ $row['name'] }}</a>
                        </td>
                        <td class="text">{{ $row['function'] ?: '—' }}</td>
                        <td>{{ $row['mentor'] ? '✓' : '' }}</td>
                        @foreach ($matrix['columns'] as $column)
                            <td style="{{ $colors[$row['cells'][$column['key']]['state']] ?? '' }}">{{ $row['cells'][$column['key']]['text'] }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ 3 + count($matrix['columns']) }}" style="padding: 2rem; font-family: inherit; opacity: .7;">{{ __('erp.training_matrix.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
