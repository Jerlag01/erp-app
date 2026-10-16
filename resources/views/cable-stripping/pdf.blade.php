{{-- Overview of cable stripping, laid out like the Excel "Kabel ontmantelen" (A4 landscape). --}}
@php
    $kg = fn ($value) => $value === null ? '' : number_format((float) $value, 2, ',', '.');
    $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d/m/Y') : null;
    $time = fn ($value) => $value ? substr((string) $value, 0, 5) : '';
@endphp
<!doctype html>
<html lang="nl-BE">
<head>
    <meta charset="utf-8">
    <title>Kabel ontmantelen</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7.5pt; color: #111827; }
        h1 { font-size: 16pt; margin: 0; font-style: italic; text-decoration: underline; }
        .meta { color: #475569; font-size: 8pt; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.lines th { background: #bf8f00; color: #ffffff; font-size: 7pt; padding: 4px 3px; border: 1px solid #a57c00; vertical-align: bottom; text-align: center; }
        table.lines td { padding: 3px; border: 1px solid #d6dbe1; vertical-align: top; }
        table.lines tbody tr:nth-child(even) td { background: #fff2cc; }
        table.lines tfoot td { font-weight: bold; border-top: 2px solid #bf8f00; background: #fdf6e3; }
        .num { text-align: right; white-space: nowrap; font-family: DejaVu Sans Mono, monospace; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .strong { font-weight: bold; }
    </style>
</head>
<body>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td><h1>Kabel ontmantelen:</h1></td>
            <td class="meta" style="text-align: right;">
                @if ($from || $until)Periode: {{ $date($from) ?? '…' }} – {{ $date($until) ?? '…' }}<br>@endif
                {{ $lines->count() }} {{ $lines->count() === 1 ? 'lijn' : 'lijnen' }} · afgedrukt op {{ now()->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Datum</th>
                <th>Imputatie - Werf</th>
                <th>Werknemer ('s)</th>
                <th>Begin werken</th>
                <th>Einde werken</th>
                <th>Totaal uren</th>
                <th>Totaal gewicht met bak en kabel VOOR ontmanteling</th>
                <th>Gewicht lege bak voor ontmantelde kabel</th>
                <th>Totaal gewicht van kabel met bak NA ontmanteling</th>
                <th>Totaal gewicht kabel VOOR ontmanteling</th>
                <th>Gewicht afval</th>
                <th>PI bak nummer</th>
                <th>Totaal KG ontmantelde kabel</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>{{ $line->date->format('d/m/Y') }}</td>
                    <td class="mono">{{ $line->imputation }}</td>
                    <td>{{ $line->workerNames() }}</td>
                    <td class="num">{{ $time($line->start_time) }}</td>
                    <td class="num">{{ $time($line->end_time) }}</td>
                    <td class="num">{{ $kg($line->hours()) }}</td>
                    <td class="num">{{ $kg($line->weight_before) }}</td>
                    <td class="num">{{ $kg($line->empty_bin_weight) }}</td>
                    <td class="num">{{ $kg($line->weight_after) }}</td>
                    <td class="num">{{ $kg($line->cableBefore()) }}</td>
                    <td class="num">{{ $kg($line->waste()) }}</td>
                    <td class="mono">{{ $line->bin_number }}</td>
                    <td class="num strong">{{ $kg($line->strippedCable()) }}</td>
                </tr>
            @empty
                <tr><td colspan="13">Geen lijnen</td></tr>
            @endforelse
        </tbody>
        @if ($lines->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="5">Totaal</td>
                    <td class="num">{{ $kg($totals['hours']) }}</td>
                    <td class="num">{{ $kg($totals['weight_before']) }}</td>
                    <td></td>
                    <td class="num">{{ $kg($totals['weight_after']) }}</td>
                    <td class="num">{{ $kg($totals['cable_before']) }}</td>
                    <td class="num">{{ $kg($totals['waste']) }}</td>
                    <td></td>
                    <td class="num">{{ $kg($totals['stripped']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
