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
                <a href="{{ $tile['url'] }}" class="erp-cp-tile">
                    <x-filament::section>
                        <div class="erp-cp-count">{{ $tile['count'] }}</div>
                        <div class="erp-cp-label">{{ $tile['label'] }}</div>
                    </x-filament::section>
                </a>
            @endforeach
        </div>

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
