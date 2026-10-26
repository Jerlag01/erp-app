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

    <div class="result {{ ($result['ok'] ?? false) ? '' : 'none' }}">
        @if ($result['ok'] ?? false)
            <div class="muted">{{ $t('result.cable') }}</div>
            <div class="big">{{ $c->cableLabel() }}</div>
            <div>{{ __('erp.cable_calculator.result.governing.'.$result['governing']) }}</div>
        @else
            {{ __('erp.cable_calculator.errors.'.(($result['errors'] ?? [])[0] ?? 'no_section')) }}
        @endif
    </div>

    <h2>{{ $t('sections.circuit') }}</h2>
    <table class="grid">
        <tr><td class="label">{{ $t('fields.system') }}</td><td>{{ $t('systems.'.$c->system) }} · {{ $n($c->voltage, 'V') }}</td></tr>
        <tr><td class="label">{{ $t('fields.load') }}</td><td>{{ $c->load_type === 'power' ? $n($c->load_power, 'kW') : $n($c->load_current, 'A') }}@if ($c->system !== 'dc') · cos φ {{ $n($c->power_factor) }}@endif</td></tr>
        <tr><td class="label">{{ $t('fields.length') }}</td><td>{{ $n($c->length, 'm') }}</td></tr>
        <tr><td class="label">{{ $t('fields.protective_device') }}</td><td>{{ $n($result['in'] ?? null, 'A') }}@if (! $c->protective_device) <span class="muted">({{ $t('result.auto') }})</span>@endif</td></tr>
    </table>

    <h2>{{ $t('sections.cable') }}</h2>
    <table class="grid">
        <tr><td class="label">{{ $t('fields.material') }} / {{ $t('fields.insulation') }}</td><td>{{ $t('materials.'.$c->material) }} · {{ $t('insulations.'.$c->insulation) }}</td></tr>
        <tr><td class="label">{{ $t('fields.installation_method') }}</td><td>{{ $t('methods.'.$c->installation_method) }}</td></tr>
        <tr><td class="label">{{ $c->installation_method === 'D1' ? $t('fields.ground_temperature') : $t('fields.ambient_temperature') }}</td><td>{{ $n($c->ambient_temperature, '°C') }}</td></tr>
        <tr><td class="label">{{ $t('fields.grouped_circuits') }}</td><td>{{ $c->grouped_circuits }}</td></tr>
        <tr><td class="label">{{ $t('fields.parallel_conductors') }}</td><td>{{ $c->parallel_conductors }}</td></tr>
        <tr><td class="label">{{ $t('fields.max_voltage_drop') }}</td><td>{{ $n($c->max_voltage_drop, '%') }}</td></tr>
        @if ($c->short_circuit_ka)
            <tr><td class="label">{{ $t('fields.short_circuit_ka') }}</td><td>{{ $n($c->short_circuit_ka, 'kA') }} · {{ $n($c->short_circuit_time, 's') }}</td></tr>
        @endif
    </table>

    <h2>{{ $t('sections.result') }}</h2>
    <table class="grid">
        <tr><td class="label">{{ $t('result.ib') }}</td><td class="num">{{ $n($result['ib'] ?? null, 'A') }}</td></tr>
        <tr><td class="label">{{ $t('result.in') }}</td><td class="num">{{ $n($result['in'] ?? null, 'A') }}</td></tr>
        <tr><td class="label">{{ $t('result.k1') }} · {{ $t('result.k2') }}</td><td class="num">{{ $n($result['k1'] ?? null) }} · {{ $n($result['k2'] ?? null) }}</td></tr>
        <tr><td class="label">{{ $t('result.iz') }}</td><td class="num">{{ $n($result['iz'] ?? null, 'A') }}</td></tr>
        <tr><td class="label">{{ $t('result.drop') }}</td><td class="num">{{ $n($result['drop_v'] ?? null, 'V') }} · {{ $n($result['drop_pct'] ?? null, '%') }}</td></tr>
        <tr><td class="label">{{ $t('result.by_current') }}</td><td class="num">{{ $n($result['by_current'] ?? null, 'mm²') }}</td></tr>
        <tr><td class="label">{{ $t('result.by_drop') }}</td><td class="num">{{ $n($result['by_drop'] ?? null, 'mm²') }}</td></tr>
        @if (($result['s_min_short_circuit'] ?? null) !== null)
            <tr><td class="label">{{ $t('result.by_short_circuit') }}</td><td class="num">{{ $n($result['by_short_circuit'] ?? null, 'mm²') }} (≥ {{ $n($result['s_min_short_circuit'], 'mm²') }})</td></tr>
        @endif
    </table>

    @if (! empty($result['rows']))
        <h2>{{ $t('result.sections') }}</h2>
        <table class="grid">
            <tr><th>mm²</th><th class="num">Iz0 (A)</th><th class="num">Iz (A)</th><th class="num">ΔU (V)</th><th class="num">ΔU (%)</th></tr>
            @foreach ($result['rows'] as $row)
                <tr @class(['chosen' => ($result['section'] ?? null) == $row['section']])>
                    <td>{{ $n($row['section']) }}</td>
                    <td class="num">{{ $n($row['iz0'] * $result['parallel']) }}</td>
                    <td class="num {{ $row['current_ok'] ? '' : 'bad' }}">{{ $n($row['iz'] * $result['parallel']) }}</td>
                    <td class="num">{{ $n($row['drop_v']) }}</td>
                    <td class="num {{ $row['drop_ok'] ? '' : 'bad' }}">{{ $n($row['drop_pct']) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (filled($c->notes))
        <h2>{{ $t('fields.notes') }}</h2>
        <div>{!! nl2br(e($c->notes)) !!}</div>
    @endif

    <p class="muted" style="margin-top: 10px; font-size: 7.5pt;">{{ $t('disclaimer') }} {{ $t('method_note') }}</p>
</body>
</html>
