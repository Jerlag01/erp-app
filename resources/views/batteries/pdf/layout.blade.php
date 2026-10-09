{{--
    Shared layout of the battery documents. With a background (the letterhead), the page margins
    keep the text between the logo and the footer band, and the image is fixed so dompdf repeats
    it on every page.
--}}
@php
    $margins = $background ? ['top' => 46, 'right' => 16, 'bottom' => 34, 'left' => 18] : ['top' => 14, 'right' => 14, 'bottom' => 14, 'left' => 14];
    $landscape = $landscape ?? false;
@endphp
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>@yield('title') {{ $dossier->reference() }}</title>
    <style>
        @page { margin: {{ $margins['top'] }}mm {{ $margins['right'] }}mm {{ $margins['bottom'] }}mm {{ $margins['left'] }}mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111827; }
        .background { position: fixed; top: -{{ $margins['top'] }}mm; left: -{{ $margins['left'] }}mm; width: {{ $landscape ? 297 : 210 }}mm; height: {{ $landscape ? 210 : 297 }}mm; z-index: -1000; }
        h1 { font-size: 18pt; margin: 0 0 6px; color: #0b4a9b; }
        h2 { font-size: 10pt; margin: 10px 0 4px; }
        table { border-collapse: collapse; }
        .w { width: 100%; }
        .grid td, .grid th { border: 1px solid #94a3b8; padding: 3px 5px; vertical-align: top; }
        .grid th { background: #e2e8f0; text-align: left; font-size: 8pt; }
        .plain td { padding: 2px 4px; vertical-align: top; }
        .label { color: #475569; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .muted { color: #64748b; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .box { border: 1px solid #94a3b8; height: 60px; padding: 4px 6px; }
        .page-break { page-break-after: always; }
        .nobreak { page-break-inside: avoid; }
        @yield('styles')
    </style>
</head>
<body>
    @if ($background)
        <img class="background" src="{{ $background }}" alt="">
    @endif
    @yield('content')
</body>
</html>
