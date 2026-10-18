{{-- Who signed which toolbox meeting. Filament's precompiled CSS only ships its own classes, so layout here uses inline styles. --}}
<x-filament-panels::page>
    <style>
        .erp-tm-bar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: flex-end; }
        .erp-tm-wrap { overflow: auto; max-height: calc(100vh - 14rem); border: 1px solid rgb(148 163 184 / .3); border-radius: .75rem; }
        .erp-tm { border-collapse: separate; border-spacing: 0; font-size: .75rem; min-width: 100%; }
        .erp-tm th { position: sticky; top: 0; z-index: 2; background: var(--erp-surface, rgb(241 245 249)); font-size: .7rem; font-weight: 600; padding: .5rem .4rem; text-align: center; border-bottom: 1px solid rgb(148 163 184 / .4); vertical-align: bottom; min-width: 7rem; max-width: 10rem; white-space: normal; }
        .dark .erp-tm th { background: rgb(15 23 42); }
        .erp-tm th span { display: block; font-family: var(--erp-mono); font-size: .625rem; letter-spacing: .06em; text-transform: uppercase; opacity: .7; font-weight: 400; }
        .erp-tm td { padding: .45rem .4rem; text-align: center; border-bottom: 1px solid rgb(148 163 184 / .2); white-space: nowrap; }
        .erp-tm .sticky { position: sticky; left: 0; z-index: 1; background: var(--erp-surface, white); text-align: left; font-weight: 600; min-width: 11rem; white-space: normal; }
        .dark .erp-tm .sticky { background: rgb(17 24 39); }
        .erp-tm th.sticky { z-index: 3; }
        .erp-tm .yes { background: rgb(22 163 74 / .14); color: rgb(22 163 74); font-weight: 700; }
        .erp-tm .no { background: rgb(220 38 38 / .08); color: rgb(220 38 38); }
    </style>

    <div class="erp-tm-bar">
        <div style="width: 9rem;">
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="year">
                    @foreach ($matrix['years'] as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
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
                    @foreach ($matrix['columns'] as $column)
                        <th>
                            <a href="{{ \App\Filament\Resources\ToolboxMeetings\ToolboxMeetingResource::getUrl('view', ['record' => $column['id']]) }}" style="text-decoration: none;">
                                <span>{{ $column['month'] }}</span>{{ $column['title'] }}
                            </a>
                        </th>
                    @endforeach
                    <th style="min-width: 4rem;">{{ __('erp.toolbox.overview.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($matrix['rows'] as $row)
                    <tr wire:key="tb-{{ $row['user']->id }}">
                        <td class="sticky">{{ $row['user']->name }}</td>
                        @foreach ($matrix['columns'] as $column)
                            @php($signedOn = $row['cells'][$column['id']] ?? null)
                            <td class="{{ $signedOn ? 'yes' : 'no' }}" title="{{ $signedOn ? __('erp.toolbox.signed_on', ['date' => $signedOn]) : __('erp.toolbox.to_sign') }}">{{ $signedOn ? '✓' : '—' }}</td>
                        @endforeach
                        <td>{{ $row['signed'] }}/{{ count($matrix['columns']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 2 + count($matrix['columns']) }}" style="padding: 2rem; opacity: .7;">{{ __('erp.training_matrix.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($matrix['columns'] === [])
        <p style="opacity: .7;">{{ __('erp.toolbox.overview.no_meetings') }}</p>
    @endif
</x-filament-panels::page>
