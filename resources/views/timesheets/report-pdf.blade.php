{{-- The prestation overview of a month, two months or a year as PDF (A4 landscape, one month per page). --}}
@php
    $dec = fn (float $value) => \App\Support\TimesheetReport::number($value);
    $legend = ['V' => 'Verlof', 'Z' => 'Ziekte', 'R' => 'Recuperatie overuren', 'IR' => 'Inhaalrust', 'FV' => 'Familiaal verlof', 'OP' => 'Opleiding', 'F' => 'Feestdag'];
@endphp
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 10mm 8mm 12mm; }
        body { font-family: Helvetica, Arial, sans-serif; color: #0f172a; }
        h1 { font-size: 11pt; margin: 0 0 4px; }
        .meta { font-size: 7pt; color: #475569; margin-bottom: 6px; }
        .legend { font-size: 6.5pt; color: #334155; margin-top: 5px; }
        .footer { position: fixed; bottom: -8mm; right: 0; font-size: 6.5pt; color: #64748b; }
        .page-break { page-break-after: always; }
        table.summary { border-collapse: collapse; width: 100%; font-size: 8pt; }
        table.summary th, table.summary td { border: 1px solid #94a3b8; padding: 3px 5px; }
        table.summary th { background: #e2e8f0; }
        table.summary td.n { text-align: right; }
    </style>
</head>
<body>
    <div class="footer">{{ settings()->get('general.company_name') ?: settings()->siteName() }} · {{ today()->format('d/m/Y') }}</div>

    @foreach ($report['months'] as $block)
        <h1>{{ $title }}</h1>
        <div class="meta">{{ __('erp.timesheets.report_mobility', ['rate' => number_format($rate, 4, ',', '')]) }}</div>
        @include('timesheets.report-table', ['block' => $block, 'fontSize' => '6pt'])
        <div class="legend">
            @foreach ($legend as $code => $label)<strong>{{ $code }}</strong> = {{ $label }}@if (! $loop->last) · @endif @endforeach
        </div>
        @if (! $loop->last || count($report['months']) > 1)<div class="page-break"></div>@endif
    @endforeach

    @if (count($report['months']) > 1)
        <h1>{{ $title }} — {{ __('erp.timesheets.report_summary') }}</h1>
        <table class="summary">
            <thead>
                <tr>
                    <th style="text-align: left;">{{ __('erp.timesheets.employee') }}</th>
                    <th>{{ __('erp.timesheets.report_hours_worked') }}</th>
                    <th>{{ __('erp.timesheets.report_hours_total') }}</th>
                    @foreach (array_keys($legend) as $code)<th>{{ $code }}</th>@endforeach
                    <th>Km</th>
                    <th>{{ __('erp.timesheets.report_mobility_short') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['summary'] as $row)
                    <tr>
                        <td><strong>{{ $row['user']->name }}</strong></td>
                        <td class="n">{{ $dec($row['hours']) }}</td>
                        <td class="n">{{ $dec($row['total']) }}</td>
                        @foreach (array_keys($legend) as $code)<td class="n">{{ $row['codes'][$code] ? $dec($row['codes'][$code]) : '' }}</td>@endforeach
                        <td class="n">{{ number_format($row['km'], 0, ',', '.') }}</td>
                        <td class="n">€ {{ number_format($row['mobility'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th style="text-align: left;">{{ __('erp.timesheets.report_total') }}</th>
                    <th class="n">{{ $dec(array_sum(array_column($report['summary'], 'hours'))) }}</th>
                    <th class="n">{{ $dec(array_sum(array_column($report['summary'], 'total'))) }}</th>
                    @foreach (array_keys($legend) as $code)<th class="n">{{ $dec(array_sum(array_map(fn ($row) => $row['codes'][$code], $report['summary']))) }}</th>@endforeach
                    <th class="n">{{ number_format(array_sum(array_column($report['summary'], 'km')), 0, ',', '.') }}</th>
                    <th class="n">€ {{ number_format(array_sum(array_column($report['summary'], 'mobility')), 2, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>
    @endif
</body>
</html>
