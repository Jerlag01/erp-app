{{--
    One month of the prestation overview, like the Excel "prestatiefiche power": per employee the
    hours (or absence code) per day, the kilometres and the imputation (project category).
    Shared by the admin page and the PDF. The cell styles are short classes (one style block per page)
    instead of inline styles on every cell: a year report has some 40.000 cells, and repeating the
    styles made the PDF need far more memory than PHP allows.
--}}
@php
    $month = $block['month'];
    $daysInMonth = $month->daysInMonth;
    $initials = explode(',', __('erp.pdf.report.initials'));
    $dec = fn (float $value) => \App\Support\TimesheetReport::number($value);
    $money = fn (float $value) => number_format($value, 2, ',', '.');
    $counted = count(\App\Support\TimesheetReport::COUNTED);
@endphp
@once
    <style>
        table.rt { border-collapse: collapse; width: 100%; line-height: 1.15; }
        table.rt th, table.rt td { border: 1px solid #cbd5e1; text-align: center; padding: 0 1px; }
        table.rt th { border: 1px solid #475569; padding: 1px; }
        table.rt .h { text-align: left; padding: 2px 4px; font-size: 1.25em; }
        table.rt .nw { font-weight: normal; }
        table.rt .w { background: #e2e8f0; }
        table.rt .t { border-top: 1px solid #475569; }
        table.rt .bt { border-bottom: 1px solid #475569; }
        table.rt .rl { border-right: 1px solid #475569; }
        table.rt .n { border-left: 1px solid #475569; border-right: 1px solid #475569; text-align: left; white-space: nowrap; }
        table.rt .nm { padding: 2px 4px; font-weight: bold; }
        table.rt .sub { padding: 1px 4px 1px 10px; color: #334155; }
        table.rt .b { font-weight: bold; }
        table.rt .r { text-align: right; padding: 0 3px; }
        table.rt .l { text-align: left; padding: 0 3px; }
        table.rt .s { font-size: 0.9em; }
        table.rt tfoot td { border: 0; padding: 3px; text-align: right; font-weight: bold; }
        table.rt tr { page-break-inside: avoid; }
    </style>
@endonce
<table class="erp-report rt" style="font-size: {{ $fontSize ?? '6.5pt' }};">
    <thead>
        <tr>
            <th rowspan="2" class="h">{{ mb_strtoupper($month->locale(app()->getLocale())->translatedFormat('F Y')) }}</th>
            <th>{{ __('erp.pdf.report.leave') }}<br>{{ $month->year }}</th>
            @foreach (\App\Support\TimesheetReport::COUNTED as $code)
                <th>{{ $code }}</th>
            @endforeach
            @for ($day = 1; $day <= $daysInMonth; $day++)
                <th @class(['w' => $month->setDay($day)->isWeekend()])>{{ $day }}</th>
            @endfor
            <th>{{ __('erp.pdf.report.hours') }}</th>
            <th colspan="2">{{ __('erp.pdf.total') }}</th>
        </tr>
        <tr>
            <th class="nw">{{ __('erp.pdf.total') }}</th>
            <th colspan="{{ $counted }}" class="nw">{{ __('erp.pdf.report.this_year') }}</th>
            @for ($day = 1; $day <= $daysInMonth; $day++)
                <th @class(['nw', 'w' => $month->setDay($day)->isWeekend()])>{{ $initials[$month->setDay($day)->dayOfWeekIso - 1] }}</th>
            @endfor
            <th></th>
            <th colspan="2"></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($block['employees'] as $row)
            {{-- Hours or absence code per day. --}}
            <tr>
                <td class="n nm t">{{ $row['user']->name }}</td>
                <td class="t">{{ $dec($row['allowance']) }}</td>
                @foreach (\App\Support\TimesheetReport::COUNTED as $code)
                    <td class="t">{{ $dec($row['year'][$code]) }}</td>
                @endforeach
                @foreach ($row['days'] as $day)
                    <td @class(['t', 'w' => $day['weekend'], 'b' => $day['code'] && $day['hours'] == 0]) @if ($day['note']) title="{{ $day['note'] }}" @endif>{{ $day['value'] }}</td>
                @endforeach
                <td class="t r b">{{ $dec($row['hours']) }}</td>
                <td colspan="2" class="t rl"></td>
            </tr>
            {{-- Kilometres per day, total km of the month. --}}
            <tr>
                <td class="n sub">{{ __('erp.pdf.report.km') }}</td>
                <td colspan="{{ 1 + $counted }}"></td>
                @foreach ($row['days'] as $day)
                    <td @class(['w' => $day['weekend']])>{{ $day['km'] ?: '' }}</td>
                @endforeach
                <td></td>
                <td class="r b">{{ number_format($row['km'], 0, ',', '.') }}</td>
                <td class="l rl">km</td>
            </tr>
            {{-- Imputation (project category) per day, mobility allowance of the month. --}}
            <tr>
                <td class="n sub bt">{{ __('erp.pdf.report.imputation') }}</td>
                <td colspan="{{ 1 + $counted }}" class="bt"></td>
                @foreach ($row['days'] as $day)
                    <td @class(['bt s', 'w' => $day['weekend']])>{{ $day['imputation'] }}</td>
                @endforeach
                <td class="bt"></td>
                <td class="bt r b">{{ $money($row['mobility']) }}</td>
                <td class="bt l rl">{{ __('erp.pdf.report.mobility') }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ 9 + $daysInMonth }}" style="padding: 8px;">—</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="{{ 2 + $counted + $daysInMonth }}">Totaal {{ $month->locale('nl')->translatedFormat('F') }}</td>
            <td>{{ $dec($block['hours']) }}</td>
            <td>{{ number_format($block['km'], 0, ',', '.') }} km</td>
            <td>€ {{ $money($block['mobility']) }}</td>
        </tr>
    </tfoot>
</table>
