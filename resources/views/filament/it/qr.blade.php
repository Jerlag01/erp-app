@php
    /** @var \App\Models\ItAsset $asset */
    $asset = $getRecord();
@endphp

{{-- Filament's precompiled CSS only ships its own classes, so sizing here uses inline styles. --}}
<div style="display: flex; flex-direction: column; align-items: center; gap: .5rem; padding: .75rem; border: 1px solid rgb(148 163 184 / .35); border-radius: .75rem; background: #fff; color: #002b45;">
    <div style="width: 9rem; height: 9rem;" aria-label="QR">{!! \App\Support\StockQr::svgFor(route('it.qr', $asset)) !!}</div>
    <span style="font-family: 'JetBrains Mono', monospace; font-weight: 700;">{{ $asset->asset_tag }}</span>
    <a href="{{ route('it.labels', ['assets' => $asset->id]) }}" target="_blank" style="font-size: .75rem; font-weight: 600; text-decoration: underline;">{{ __('erp.stock.print_label') }}</a>
</div>
