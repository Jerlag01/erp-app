{{-- The start page of the client portal. Inline styles: Filament ships precompiled CSS. --}}
<x-filament-panels::page>
    <style>
        .erp-cp-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); }
        .erp-cp-tile { display: block; text-decoration: none; }
        .erp-cp-count { font-size: 2rem; font-weight: 800; line-height: 1.1; }
        .erp-cp-label { font-size: .875rem; opacity: .75; }
        .erp-cp-statuses { display: flex; flex-wrap: wrap; gap: .5rem; }
    </style>

    @php($tiles = $this->tiles())
    @if ($tiles === [])
        <x-filament::section>{{ __('erp.client_portal.nothing') }}</x-filament::section>
    @else
        <div class="erp-cp-grid">
            @foreach ($tiles as $tile)
                <a href="{{ $tile['url'] }}" class="erp-cp-tile" @if ($tile['highlight']) data-highlight @endif>
                    <x-filament::section>
                        <div class="erp-cp-count" @if ($tile['highlight']) style="color: var(--warning-600, #d97706);" @endif>{{ $tile['count'] }}</div>
                        <div class="erp-cp-label">{{ $tile['label'] }}</div>
                    </x-filament::section>
                </a>
            @endforeach
        </div>

        @if ($areas = $this->areas())
            {{-- Per area code: the share of open and of closed projects (open + closed = 100 %). --}}
            <x-filament::section :heading="__('erp.client_portal.areas.title')" :description="__('erp.client_portal.areas.help')">
                <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));" data-client-areas>
                    @foreach ($areas as $area)
                        <div style="padding: .85rem 1rem; border-radius: .75rem; border: 1px solid rgb(148 163 184 / .35);">
                            <div style="display: flex; justify-content: space-between; align-items: baseline; gap: .5rem;">
                                <strong style="font-family: var(--erp-mono, ui-monospace, monospace);">{{ $area['area'] }}</strong>
                                <span style="font-size: .8rem; opacity: .7;">{{ trans_choice('erp.client_portal.areas.projects', $area['total'], ['count' => $area['total']]) }}</span>
                            </div>
                            <div style="display: flex; height: .6rem; border-radius: 999px; overflow: hidden; margin: .6rem 0 .5rem; background: rgb(148 163 184 / .2);" role="img"
                                aria-label="{{ __('erp.client_portal.areas.open') }} {{ number_format($area['open_percent'], 1, ',', '') }} %, {{ __('erp.client_portal.areas.closed') }} {{ number_format($area['closed_percent'], 1, ',', '') }} %">
                                <div style="width: {{ $area['open_percent'] }}%; background: var(--warning-500, #f59e0b);"></div>
                                <div style="width: {{ $area['closed_percent'] }}%; background: var(--success-500, #22c55e);"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; gap: .5rem; font-size: .85rem;">
                                <span><span style="display: inline-block; width: .6rem; height: .6rem; border-radius: 2px; background: var(--warning-500, #f59e0b);"></span>
                                    {{ __('erp.client_portal.areas.open') }} <strong>{{ number_format($area['open_percent'], 1, ',', '') }} %</strong> <span style="opacity: .65;">({{ $area['open'] }})</span></span>
                                <span><span style="display: inline-block; width: .6rem; height: .6rem; border-radius: 2px; background: var(--success-500, #22c55e);"></span>
                                    {{ __('erp.client_portal.areas.closed') }} <strong>{{ number_format($area['closed_percent'], 1, ',', '') }} %</strong> <span style="opacity: .65;">({{ $area['closed'] }})</span></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @if ($statuses = $this->statuses())
            <x-filament::section :heading="__('erp.client_portal.projects_per_status')">
                <div class="erp-cp-statuses">
                    @foreach ($statuses as $status)
                        <a href="{{ $status['url'] }}" style="text-decoration: none;">
                            <x-filament::badge :color="$status['color']">{{ $status['label'] }} · {{ $status['count'] }}</x-filament::badge>
                        </a>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
