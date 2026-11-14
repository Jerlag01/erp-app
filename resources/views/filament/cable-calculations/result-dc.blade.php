{{-- The live result of a DC calculation (Proximus 48 V calculation note, see CableCalculator::calculateDc()). --}}
@php
    use App\Support\CableCalculator;
    $n = fn ($value, $unit = '') => $value === null ? '—' : trim(CableCalculator::number((float) $value).' '.$unit);
    $num = fn ($value, $decimals = 2) => $value === null ? '—' : number_format((float) $value, $decimals, ',', '.');
    $ok = $result['ok'];
@endphp
<div style="display: grid; gap: .75rem;" data-cable-result>
    @if ($result['section'] !== null)
        <div style="padding: .9rem 1rem; border-radius: .75rem; background: {{ $ok ? 'rgb(22 163 74 / .1)' : 'rgb(220 38 38 / .1)' }}; border: 1px solid {{ $ok ? 'rgb(22 163 74 / .4)' : 'rgb(220 38 38 / .4)' }};">
            <div style="font-size: .8rem; opacity: .75;">{{ __('erp.cable_calculator.result.provided') }}</div>
            <div style="font-size: 1.5rem; font-weight: 700; font-family: var(--erp-mono, monospace);" data-cable-section="{{ $result['section'] }}">
                {{ CableCalculator::dcLabel($result['section'], $result['parallel']) }}
            </div>
            <div style="font-size: .85rem; margin-top: .25rem;">
                {{ $ok ? __('erp.cable_calculator.result.dc_ok', ['ratio' => $num($result['ratio'])]) : __('erp.cable_calculator.errors.section_too_small', ['ratio' => $num($result['ratio'])]) }}
            </div>
        </div>
    @else
        <div style="padding: .9rem 1rem; border-radius: .75rem; background: rgb(148 163 184 / .12);">
            @foreach ($result['errors'] as $error)
                <div>{{ __('erp.cable_calculator.errors.'.$error) }}</div>
            @endforeach
        </div>
    @endif

    @if ($result['s_total'] !== null)
        <div style="font-size: .85rem; padding: .6rem .75rem; border-radius: .5rem; background: rgb(148 163 184 / .1); font-variant-numeric: tabular-nums;">
            S = {{ $n($input['protective_device'] ?? null) }} × {{ $n($input['length'] ?? null) }} × 2 × {{ rtrim(rtrim(number_format((float) ($input['resistivity'] ?? 0), 5, ',', ''), '0'), ',') }} / {{ $n($input['max_drop_volts'] ?? null) }}
            = <strong>{{ $num($result['s_total']) }} mm²</strong>
            @if ($result['parallel'] > 1)
                <br>{{ __('erp.cable_calculator.result.per_cable', ['count' => $result['parallel'], 'section' => $num($result['s_required'])]) }}
            @endif
        </div>
    @endif

    <table style="width: 100%; font-size: .875rem; border-collapse: collapse;">
        @foreach ([
            'imax' => $n($result['in'], 'A'),
            's_required' => $result['s_required'] === null ? '—' : $num($result['s_required']).' mm²',
            'suggested' => $n($result['suggested'], 'mm²'),
            'ratio' => $num($result['ratio']),
            'drop' => $result['drop_v'] === null ? '—' : $num($result['drop_v']).' V'.($result['drop_pct'] !== null ? ' · '.$num($result['drop_pct']).' %' : ''),
        ] as $key => $value)
            <tr style="border-top: 1px solid rgb(148 163 184 / .25);">
                <td style="padding: .35rem 0; opacity: .75;">{{ __('erp.cable_calculator.result.'.$key) }}</td>
                <td style="padding: .35rem 0; text-align: right; font-variant-numeric: tabular-nums; font-weight: 600;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>
</div>
