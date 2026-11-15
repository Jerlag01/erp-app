{{-- One circuit of a cable calculation in the PDF (see pdf.blade.php): its result and its AC or DC details. --}}
@php
    use App\Support\CableCalculator;
    $n = fn ($value, $unit = '') => $value === null || $value === '' ? '—' : trim(CableCalculator::number((float) $value).' '.$unit);
    $t = fn (string $key) => __('erp.cable_calculator.'.$key);
@endphp
<div class="circuit">
    <h2 class="circuit-title">{{ $index }}. {{ $c->route() }} <span class="muted">· {{ $c->isDc() ? 'DC' : 'AC' }}</span></h2>
    <div class="result {{ ($result['ok'] ?? false) ? '' : 'none' }}">
        @if ($c->isDc() && ($result['section'] ?? null) !== null)
            <div class="muted">{{ $t('result.provided') }}</div>
            <div class="big">{{ $c->cableLabel() }}</div>
            <div>{{ ($result['ok'] ?? false) ? __('erp.cable_calculator.result.dc_ok', ['ratio' => number_format((float) $result['ratio'], 2, ',', '.')]) : __('erp.cable_calculator.errors.section_too_small', ['ratio' => number_format((float) $result['ratio'], 2, ',', '.')]) }}</div>
        @elseif ($result['ok'] ?? false)
            <div class="muted">{{ $t('result.cable') }}</div>
            <div class="big">{{ $c->cableLabel() }}</div>
            <div>{{ __('erp.cable_calculator.result.governing.'.$result['governing']) }}</div>
            @if ($result['parallel_auto'] ?? false)
                <div><strong>{{ __('erp.cable_calculator.result.parallel_auto', ['count' => $result['parallel'], 'max' => $n($result['max_section'], 'mm²')]) }}</strong></div>
            @endif
        @else
            {{ __('erp.cable_calculator.errors.'.(($result['errors'] ?? [])[0] ?? 'no_section')) }}
        @endif
    </div>

    @if ($c->isDc())
    @php
        $num = fn ($value, $decimals = 2) => $value === null ? '—' : number_format((float) $value, $decimals, ',', '.');
        $rho = rtrim(rtrim(number_format((float) $c->resistivity, 5, ',', ''), '0'), ',');
    @endphp
    <h3>{{ $t('sections.dc') }}</h3>
    <table class="grid">
        <tr><td class="label">{{ $t('fields.kind') }}</td><td>{{ $t('kinds.dc') }} · {{ $n($c->voltage, 'V') }}</td></tr>
        <tr><td class="label">{{ $t('protection_types.'.($c->protection_type ?: 'breaker')) }}</td><td>{{ $n($c->protective_device, 'A') }}@if ($c->protection_size) · {{ $c->protection_size }}@endif</td></tr>
        <tr><td class="label">{{ $t('fields.length') }}</td><td>{{ $n($c->length, 'm') }}</td></tr>
        <tr><td class="label">{{ $t('fields.resistivity') }}</td><td>{{ $rho }} Ω·mm²/m</td></tr>
        <tr><td class="label">{{ $t('fields.max_drop_volts') }}</td><td>{{ $n($c->max_drop_volts, 'V') }}</td></tr>
        <tr><td class="label">{{ $t('fields.cables_per_pole') }}</td><td>{{ $c->parallel_conductors }}</td></tr>
    </table>

    <h3>{{ $t('result.formula') }}</h3>
    <p>S = Imax × (L × 2) × ρ / {{ $t('result.allowed_drop') }}</p>
    <p><strong>S = {{ $n($c->protective_device) }} × {{ $n($c->length) }} × 2 × {{ $rho }} / {{ $n($c->max_drop_volts) }} = {{ $num($result['s_total'] ?? null) }} mm²</strong>@if (($result['parallel'] ?? 1) > 1) · {{ __('erp.cable_calculator.result.per_cable', ['count' => $result['parallel'], 'section' => $num($result['s_required'] ?? null)]) }}@endif</p>

    <h3>{{ $t('sections.result') }}</h3>
    <table class="grid">
        <tr><td class="label">{{ $t('result.s_required') }}</td><td class="num">{{ $num($result['s_required'] ?? null) }} mm²</td></tr>
        <tr><td class="label">{{ $t('result.suggested') }}</td><td class="num">{{ $n($result['suggested'] ?? null, 'mm²') }}</td></tr>
        <tr><td class="label">{{ $t('result.provided') }}</td><td class="num">{{ $n($result['section'] ?? null, 'mm²') }}</td></tr>
        <tr><td class="label">{{ $t('result.ratio') }}</td><td class="num {{ ($result['ok'] ?? false) ? '' : 'bad' }}">{{ $num($result['ratio'] ?? null) }}</td></tr>
        <tr><td class="label">{{ $t('result.drop') }}</td><td class="num">{{ $num($result['drop_v'] ?? null).' V' }}@if (($result['drop_pct'] ?? null) !== null) · {{ $num($result['drop_pct']).' %' }}@endif</td></tr>
    </table>

    @else
    <h3>{{ $t('sections.circuit') }}</h3>
    <table class="grid">
        <tr><td class="label">{{ $t('fields.system') }}</td><td>{{ $t('systems.'.\App\Support\CableCalculator::acSystem($c->system)) }} · {{ $n($c->voltage, 'V') }} · {{ $n($c->frequency ?: 50, 'Hz') }}</td></tr>
        <tr><td class="label">{{ $t('fields.earthing') }}</td><td>{{ $t('earthings.'.($c->earthing ?: 'undefined')) }}</td></tr>
        <tr><td class="label">{{ $t('fields.load') }}</td><td>{{ match ($c->load_type) { 'power' => $n($c->load_power, 'kW'), 'apparent' => $n($c->load_kva, 'kVA'), default => $n($c->load_current, 'A') } }} · cos φ {{ number_format((float) $c->power_factor, 2, ',', '') }}</td></tr>
        <tr><td class="label">{{ $t('fields.length') }}</td><td>{{ $n($c->length, 'm') }}</td></tr>
        <tr><td class="label">{{ $t('fields.protective_device') }}</td><td>{{ $n($result['in'] ?? null, 'A') }}@if (! $c->protective_device) <span class="muted">({{ $t('result.auto') }})</span>@endif</td></tr>
    </table>

    <h3>{{ $t('sections.cable') }}</h3>
    <table class="grid">
        <tr><td class="label">{{ $t('fields.material') }} / {{ $t('fields.insulation') }}</td><td>{{ $t('materials.'.$c->material) }} · {{ $t('insulations.'.$c->insulation) }}</td></tr>
        <tr><td class="label">{{ $t('fields.installation_method') }}</td><td>{{ $t('methods.'.$c->installation_method) }}</td></tr>
        <tr><td class="label">{{ $c->installation_method === 'D1' ? $t('fields.ground_temperature') : $t('fields.ambient_temperature') }}</td><td>{{ $n($c->ambient_temperature, '°C') }}</td></tr>
        <tr><td class="label">{{ $t('fields.grouped_circuits') }}</td><td>{{ $c->grouped_circuits }}</td></tr>
        <tr><td class="label">{{ $t('fields.parallel_conductors') }}</td><td>{{ $c->parallel_conductors }}</td></tr>
        <tr><td class="label">{{ $t('fields.max_voltage_drop') }}</td><td>{{ $n($c->max_voltage_drop, '%') }}</td></tr>
        <tr><td class="label">{{ $t('fields.max_section') }}</td><td>{{ $n($result['max_section'] ?? ($c->max_section ?: 300), 'mm²') }}</td></tr>
        <tr><td class="label">{{ $t('result.pe') }}</td><td>@if ($result['pen'] ?? false){{ $t('result.pen_in_cable') }}@else{{ $n($result['pe_section'] ?? null, 'mm²') }} · {{ ($result['earth_in_cable'] ?? true) ? $t('result.pe_in_cable') : $t('result.pe_apart') }}@endif</td></tr>
        @if ($c->short_circuit_ka)
            <tr><td class="label">{{ $t('fields.short_circuit_ka') }}</td><td>{{ $n($c->short_circuit_ka, 'kA') }} · {{ $n($c->short_circuit_time, 's') }}</td></tr>
        @endif
    </table>

    <h3>{{ $t('sections.result') }}</h3>
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
        <h3>{{ $t('result.sections') }}</h3>
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

    @endif

</div>
