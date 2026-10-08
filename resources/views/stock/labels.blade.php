<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('erp.stock.labels_title') }} · {{ settings()->siteName() }}</title>
    <style>
        @page { size: A4; margin: 15mm 7mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, -apple-system, 'Segoe UI', Roboto, sans-serif; color: #002b45; background: #f5f7fa; }
        .toolbar { display: flex; gap: .75rem; align-items: center; padding: 1rem; background: #002b45; color: #fff; }
        .toolbar button { padding: .5rem 1rem; border: 0; border-radius: .375rem; background: #f7941d; color: #fff; font-weight: 700; cursor: pointer; }
        .sheet { display: grid; grid-template-columns: repeat(3, 63.5mm); grid-auto-rows: 38.1mm; gap: 0 2.5mm; justify-content: center; padding: 1rem 0; }
        .label { display: flex; gap: 2mm; align-items: center; padding: 2mm 3mm; background: #fff; border: 1px dashed #b0bec5; overflow: hidden; }
        .label svg { width: 30mm; height: 30mm; flex-shrink: 0; }
        .label svg path { fill: #000; }
        .text { min-width: 0; display: flex; flex-direction: column; gap: 1mm; }
        .name { font-size: 8.5pt; font-weight: 800; line-height: 1.15; max-height: 3.5em; overflow: hidden; }
        .ref { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 7pt; word-break: break-all; }
        .meta { font-size: 6.5pt; color: #5d6d7e; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { padding: 0; }
            .label { border-color: transparent; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <strong>{{ trans_choice('erp.stock.labels_count', $items->count(), ['count' => $items->count()]) }}</strong>
        <button type="button" onclick="window.print()">{{ __('erp.stock.print') }}</button>
    </div>

    <main class="sheet">
        @foreach ($items as $item)
            <div class="label">
                {!! \App\Support\StockQr::svg($item) !!}
                <div class="text">
                    <div class="name">{{ $item->name }}</div>
                    @if ($item->manufacturer_reference)
                        <div class="ref">{{ $item->manufacturer_reference }}</div>
                    @endif
                    <div class="meta">{{ $item->manufacturer?->name }} · {{ $item->unit->abbreviation }}</div>
                </div>
            </div>
        @endforeach
    </main>
</body>
</html>
