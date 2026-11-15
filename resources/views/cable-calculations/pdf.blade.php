{{-- A cable calculation on the letterhead of the battery documents (the 604 delivery note). --}}
@php
    use App\Support\CableCalculator;
    $margins = $background ? ['top' => 46, 'right' => 16, 'bottom' => 34, 'left' => 18] : ['top' => 14, 'right' => 14, 'bottom' => 14, 'left' => 14];
    $n = fn ($value, $unit = '') => $value === null || $value === '' ? '—' : trim(CableCalculator::number((float) $value).' '.$unit);
    $c = $calculation;
    $t = fn (string $key) => __('erp.cable_calculator.'.$key);
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $t('singular') }} {{ $c->name }}</title>
    <style>
        @page { margin: {{ $margins['top'] }}mm {{ $margins['right'] }}mm {{ $margins['bottom'] }}mm {{ $margins['left'] }}mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111827; }
        .background { position: fixed; top: -{{ $margins['top'] }}mm; left: -{{ $margins['left'] }}mm; width: 210mm; height: 297mm; z-index: -1000; }
        h1 { font-size: 16pt; margin: 0 0 2px; color: #0b4a9b; }
        h2 { font-size: 10pt; margin: 12px 0 4px; color: #0b4a9b; }
        table { border-collapse: collapse; width: 100%; }
        .grid td, .grid th { border: 1px solid #94a3b8; padding: 3px 5px; }
        .grid th { background: #e2e8f0; text-align: left; font-size: 8pt; }
        .label { color: #475569; width: 45%; }
        .num { text-align: right; white-space: nowrap; }
        .muted { color: #64748b; }
        .result { border: 2px solid #16a34a; background: #f0fdf4; padding: 8px 10px; margin-top: 8px; }
        .result.none { border-color: #dc2626; background: #fef2f2; }
        .big { font-size: 15pt; font-weight: bold; font-family: DejaVu Sans Mono, monospace; }
        .chosen td { font-weight: bold; background: #f0fdf4; }
        .bad { color: #b91c1c; }
        h3 { font-size: 9pt; margin: 8px 0 3px; color: #334155; }
        .circuit { margin-top: 14px; }
        .circuit-title { page-break-after: avoid; }
        .circuit-title { border-bottom: 2px solid #0b4a9b; padding-bottom: 2px; }
    </style>
</head>
<body>
    @if ($background)
        <img class="background" src="{{ $background }}" alt="">
    @endif

    <h1>{{ $t('title') }}</h1>
    <div class="muted">
        {{ $c->name }}@if ($c->project) · {{ $c->project->reference }}@endif · {{ ($c->updated_at ?? now())->format('d/m/Y') }}@if ($c->creator) · {{ $c->creator->name }}@endif
    </div>

    @php $calculation = $c; @endphp
    <h2>{{ $t('circuits') }}</h2>
    <table class="grid">
        <tr><th>#</th><th>{{ $t('fields.from_point') }} → {{ $t('fields.to_point') }}</th><th>{{ $t('fields.kind') }}</th><th>{{ $t('result.cable') }}</th><th class="num">{{ $t('result.drop') }}</th></tr>
        @foreach ($calculation->circuits as $circuit)
            @php $r = (array) $circuit->result; @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $circuit->route() }}</td>
                <td>{{ $circuit->isDc() ? 'DC' : 'AC' }}</td>
                <td class="{{ ($r['ok'] ?? false) ? '' : 'bad' }}"><strong>{{ $circuit->cableLabel() ?? $t('result.none') }}</strong></td>
                <td class="num">{{ $circuit->isDc() ? $n($r['drop_v'] ?? null, 'V') : $n($r['drop_pct'] ?? null, '%') }}</td>
            </tr>
        @endforeach
    </table>

    @foreach ($calculation->circuits as $circuit)
        @include('cable-calculations.circuit', ['c' => $circuit, 'result' => (array) $circuit->result, 'index' => $loop->iteration])
    @endforeach

    @if (filled($c->notes))
        <h2>{{ $t('fields.notes') }}</h2>
        <div>{!! nl2br(e($c->notes)) !!}</div>
    @endif

    <p class="muted" style="margin-top: 10px; font-size: 7.5pt;">
        @if ($calculation->circuits->contains(fn ($circuit) => ! $circuit->isDc())){{ $t('disclaimer') }} {{ $t('method_note') }}@endif
        @if ($calculation->circuits->contains(fn ($circuit) => $circuit->isDc())) {{ $t('dc_note') }}@endif
    </p>
</body>
</html>
