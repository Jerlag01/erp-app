{{-- The live result of the cable calculator next to the form (see CableCalculator). --}}
@php
    use App\Support\CableCalculator;
    $n = fn ($value, $unit = '') => $value === null ? '—' : trim(CableCalculator::number((float) $value).' '.$unit);
    $rows = collect($result['rows']);
    $around = $result['section'] !== null
        ? $rows->filter(fn ($row) => abs(array_search($row['section'], CableCalculator::SECTIONS) - array_search($result['section'], CableCalculator::SECTIONS)) <= 2)
        : collect();
@endphp
<div style="display: grid; gap: .75rem;" data-cable-result>
    @if ($result['ok'])
        <div style="padding: .9rem 1rem; border-radius: .75rem; background: rgb(22 163 74 / .1); border: 1px solid rgb(22 163 74 / .4);">
            <div style="font-size: .8rem; opacity: .75;">{{ __('erp.cable_calculator.result.cable') }}</div>
            <div style="font-size: 1.5rem; font-weight: 700; font-family: var(--erp-mono, monospace);" data-cable-section="{{ $result['section'] }}">
                {{ CableCalculator::label($result['section'], (string) ($input['material'] ?? 'cu'), (string) ($input['insulation'] ?? 'xlpe'), $result['loaded'], $result['parallel']) }}
            </div>
            <div style="font-size: .85rem; margin-top: .25rem;">{{ __('erp.cable_calculator.result.governing.'.$result['governing']) }}</div>
        </div>
    @elseif (in_array('no_section', $result['errors'], true))
        <div style="padding: .9rem 1rem; border-radius: .75rem; background: rgb(220 38 38 / .1); border: 1px solid rgb(220 38 38 / .4);">
            {{ __('erp.cable_calculator.errors.no_section') }}
        </div>
    @else
        <div style="padding: .9rem 1rem; border-radius: .75rem; background: rgb(148 163 184 / .12);">
            @foreach ($result['errors'] as $error)
                <div>{{ __('erp.cable_calculator.errors.'.$error) }}</div>
            @endforeach
        </div>
    @endif

    <table style="width: 100%; font-size: .875rem; border-collapse: collapse;">
        @foreach ([
            'ib' => $n($result['ib'], 'A'),
            'in' => $n($result['in'], 'A'),
            'k1' => $n($result['k1']),
            'k2' => $n($result['k2']),
            'iz' => $n($result['iz'], 'A'),
            'drop' => $result['drop_pct'] === null ? '—' : $n($result['drop_v'], 'V').' · '.$n($result['drop_pct'], '%'),
            'by_current' => $n($result['by_current'], 'mm²'),
            'by_drop' => $n($result['by_drop'], 'mm²'),
            'by_short_circuit' => $result['s_min_short_circuit'] === null ? null : $n($result['by_short_circuit'], 'mm²').' (≥ '.$n($result['s_min_short_circuit'], 'mm²').')',
        ] as $key => $value)
            @continue($value === null)
            <tr style="border-top: 1px solid rgb(148 163 184 / .25);">
                <td style="padding: .35rem 0; opacity: .75;">{{ __('erp.cable_calculator.result.'.$key) }}</td>
                <td style="padding: .35rem 0; text-align: right; font-variant-numeric: tabular-nums; font-weight: 600;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    @if ($around->isNotEmpty())
        <table style="width: 100%; font-size: .8rem; border-collapse: collapse;">
            <thead>
                <tr style="opacity: .7; text-align: right;">
                    <th style="text-align: left; padding: .25rem 0;">mm²</th>
                    <th style="padding: .25rem 0;">Iz (A)</th>
                    <th style="padding: .25rem 0;">ΔU (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($around as $row)
                    <tr style="text-align: right; border-top: 1px solid rgb(148 163 184 / .2); {{ $row['section'] == $result['section'] ? 'font-weight: 700;' : '' }}">
                        <td style="text-align: left; padding: .25rem 0;">{{ $n($row['section']) }}</td>
                        <td style="padding: .25rem 0; {{ $row['current_ok'] ? '' : 'color: rgb(220 38 38);' }}">{{ $n($row['iz'] * $result['parallel']) }}</td>
                        <td style="padding: .25rem 0; {{ $row['drop_ok'] ? '' : 'color: rgb(220 38 38);' }}">{{ $n($row['drop_pct']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p style="font-size: .75rem; opacity: .7;">{{ __('erp.cable_calculator.disclaimer') }}</p>
</div>
