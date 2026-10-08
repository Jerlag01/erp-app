{{-- Filament's precompiled CSS only ships its own classes, so layout here uses inline styles. --}}
<x-filament-panels::page>
    <style>
        .erp-myit { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); }
        .erp-myit-card { border: 1px solid rgb(148 163 184 / .35); border-radius: .75rem; padding: 1rem; display: flex; flex-direction: column; gap: .25rem; }
        .dark .erp-myit-card { border-color: rgb(255 255 255 / .1); }
        .erp-myit-tag { font-family: var(--erp-mono); font-weight: 700; color: var(--erp-accent); }
        .erp-myit-muted { font-size: .8125rem; opacity: .7; }
        .erp-myit h3 { font-weight: 700; }
        .erp-myit-section { font-family: var(--erp-mono); font-size: .75rem; letter-spacing: .12em; text-transform: uppercase; opacity: .7; margin: 1.25rem 0 .5rem; }
    </style>

    <div class="erp-myit-section">{{ __('erp.resources.it_asset.plural') }} ({{ $assets->count() }})</div>
    @if ($assets->isEmpty())
        <p class="erp-myit-muted">{{ __('erp.it.nothing_assigned') }}</p>
    @else
        <div class="erp-myit">
            @foreach ($assets as $asset)
                <div class="erp-myit-card">
                    <span class="erp-myit-tag">{{ $asset->asset_tag }}</span>
                    <h3>{{ $asset->name ?: $asset->model->fullName() }}</h3>
                    @if ($asset->name)
                        <span class="erp-myit-muted">{{ $asset->model->fullName() }}</span>
                    @endif
                    @if ($asset->serial)
                        <span class="erp-myit-muted" style="font-family: var(--erp-mono);">S/N {{ $asset->serial }}</span>
                    @endif
                    <span class="erp-myit-muted">{{ __('erp.it.since', ['date' => $asset->assigned_at?->format('d/m/Y')]) }}@if ($asset->expected_checkin) · {{ __('erp.it.expected_back', ['date' => $asset->expected_checkin->format('d/m/Y')]) }}@endif</span>
                    @foreach ($asset->children as $child)
                        <span class="erp-myit-muted">+ {{ $child->label() }}</span>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    <div class="erp-myit-section">{{ __('erp.resources.it_license.plural') }} ({{ $seats->count() }})</div>
    @if ($seats->isEmpty())
        <p class="erp-myit-muted">{{ __('erp.it.nothing_assigned') }}</p>
    @else
        <div class="erp-myit">
            @foreach ($seats as $seat)
                <div class="erp-myit-card">
                    <h3>{{ $seat->license->name }}</h3>
                    @if ($seat->license->expiration_date)
                        <span class="erp-myit-muted">{{ __('erp.fields.expiration_date') }}: {{ $seat->license->expiration_date->format('d/m/Y') }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="erp-myit-section">{{ __('erp.resources.it_accessory.plural') }} ({{ $items->count() }})</div>
    @if ($items->isEmpty())
        <p class="erp-myit-muted">{{ __('erp.it.nothing_assigned') }}</p>
    @else
        <div class="erp-myit">
            @foreach ($items as $assignment)
                <div class="erp-myit-card">
                    <h3>{{ $assignment->quantity > 1 ? $assignment->quantity.' × ' : '' }}{{ $assignment->item->name }}</h3>
                    <span class="erp-myit-muted">{{ __('erp.it.since', ['date' => $assignment->assigned_at->format('d/m/Y')]) }}</span>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
