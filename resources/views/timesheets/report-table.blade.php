{{--
    One month of the prestation overview, like the Excel "prestatiefiche power": per employee the
    hours (or absence code) per day, the kilometres and the imputation (project category).
    Shared by the admin page and the PDF, so it only uses inline-able table styles.
--}}
@php
    $month = $block['month'];
    $daysInMonth = $month->daysInMonth;
    $initials = ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];
    $dec = fn (float $value) => \App\Support\TimesheetReport::number($value);
    $money = fn (float $value) => number_format($value, 2, ',', '.');
    $line = '1px solid #475569';
    $thin = '1px solid #cbd5e1';
    $weekendBg = '#e2e8f0';
@endphp
<table class="erp-report" style="border-collapse: collapse; width: 100%; font-size: {{ $fontSize ?? '6.5pt' }}; line-height: 1.15;">
    <thead>
        <tr>
            <th rowspan="2" style="border: {{ $line }}; padding: 2px 4px; text-align: left; font-size: 1.25em;">{{ mb_strtoupper($month->locale('nl')->translatedFormat('F Y')) }}</th>
            <th style="border: {{ $line }}; padding: 2px; text-align: center;">Verlof<br>{{ $month->year }}</th>
            @foreach (\App\Support\TimesheetReport::COUNTED as $code)
                <th style="border: {{ $line }}; padding: 2px; text-align: center;">{{ $code }}</th>
            @endforeach
            @for ($day = 1; $day <= $daysInMonth; $day++)
                @php
                    $date = $month->setDay($day);
                @endphp
                <th style="border: {{ $line }}; padding: 1px; text-align: center; {{ $date->isWeekend() ? 'background: '.$weekendBg.';' : '' }}">{{ $day }}</th>
            @endfor
            <th style="border: {{ $line }}; padding: 2px; text-align: center;">Uren</th>
            <th colspan="2" style="border: {{ $line }}; padding: 2px; text-align: center;">Totaal</th>
        </tr>
        <tr>
            <th style="border: {{ $line }}; padding: 1px; text-align: center; font-weight: normal;">Totaal</th>
            <th colspan="{{ count(\App\Support\TimesheetReport::COUNTED) }}" style="border: {{ $line }}; padding: 1px; text-align: center; font-weight: normal;">dit jaar</th>
            @for ($day = 1; $day <= $daysInMonth; $day++)
                @php
                    $date = $month->setDay($day);
                @endphp
                <th style="border: {{ $line }}; padding: 1px; text-align: center; font-weight: normal; {{ $date->isWeekend() ? 'background: '.$weekendBg.';' : '' }}">{{ $initials[$date->dayOfWeekIso - 1] }}</th>
            @endfor
            <th style="border: {{ $line }};"></th>
            <th colspan="2" style="border: {{ $line }};"></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($block['employees'] as $row)
            {{-- Hours or absence code per day. --}}
            <tr style="page-break-inside: avoid;">
                <td style="border-left: {{ $line }}; border-right: {{ $line }}; border-top: {{ $line }}; padding: 2px 4px; font-weight: bold; white-space: nowrap;">{{ $row['user']->name }}</td>
                <td style="border: {{ $thin }}; border-top: {{ $line }}; text-align: center;">{{ $dec($row['allowance']) }}</td>
                @foreach (\App\Support\TimesheetReport::COUNTED as $code)
                    <td style="border: {{ $thin }}; border-top: {{ $line }}; text-align: center;">{{ $dec($row['year'][$code]) }}</td>
                @endforeach
                @foreach ($row['days'] as $day)
                    <td style="border: {{ $thin }}; border-top: {{ $line }}; text-align: center; {{ $day['weekend'] ? 'background: '.$weekendBg.';' : '' }} {{ $day['code'] && $day['hours'] == 0 ? 'font-weight: bold;' : '' }}" @if ($day['note']) title="{{ $day['note'] }}" @endif>{{ $day['value'] }}</td>
                @endforeach
                <td style="border: {{ $thin }}; border-top: {{ $line }}; text-align: right; padding: 0 3px; font-weight: bold;">{{ $dec($row['hours']) }}</td>
                <td colspan="2" style="border: {{ $thin }}; border-top: {{ $line }}; border-right: {{ $line }};"></td>
            </tr>
            {{-- Kilometres per day, total km of the month. --}}
            <tr style="page-break-inside: avoid;">
                <td style="border-left: {{ $line }}; border-right: {{ $line }}; padding: 1px 4px 1px 10px; color: #334155;">Km</td>
                <td colspan="{{ 1 + count(\App\Support\TimesheetReport::COUNTED) }}" style="border: {{ $thin }};"></td>
                @foreach ($row['days'] as $day)
                    <td style="border: {{ $thin }}; text-align: center; {{ $day['weekend'] ? 'background: '.$weekendBg.';' : '' }}">{{ $day['km'] ?: '' }}</td>
                @endforeach
                <td style="border: {{ $thin }};"></td>
                <td style="border: {{ $thin }}; text-align: right; padding: 0 3px; font-weight: bold;">{{ number_format($row['km'], 0, ',', '.') }}</td>
                <td style="border: {{ $thin }}; border-right: {{ $line }}; padding: 0 3px;">km</td>
            </tr>
            {{-- Imputation (project category) per day, mobility allowance of the month. --}}
            <tr style="page-break-inside: avoid;">
                <td style="border-left: {{ $line }}; border-right: {{ $line }}; border-bottom: {{ $line }}; padding: 1px 4px 1px 10px; color: #334155;">Imputatie</td>
                <td colspan="{{ 1 + count(\App\Support\TimesheetReport::COUNTED) }}" style="border: {{ $thin }}; border-bottom: {{ $line }};"></td>
                @foreach ($row['days'] as $day)
                    <td style="border: {{ $thin }}; border-bottom: {{ $line }}; text-align: center; font-size: 0.9em; {{ $day['weekend'] ? 'background: '.$weekendBg.';' : '' }}">{{ $day['imputation'] }}</td>
                @endforeach
                <td style="border: {{ $thin }}; border-bottom: {{ $line }};"></td>
                <td style="border: {{ $thin }}; border-bottom: {{ $line }}; text-align: right; padding: 0 3px; font-weight: bold;">{{ $money($row['mobility']) }}</td>
                <td style="border: {{ $thin }}; border-bottom: {{ $line }}; border-right: {{ $line }}; padding: 0 3px;">mobiliteit</td>
            </tr>
        @empty
            <tr><td colspan="{{ 9 + $daysInMonth }}" style="padding: 8px; text-align: center;">—</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="{{ 2 + count(\App\Support\TimesheetReport::COUNTED) + $daysInMonth }}" style="padding: 3px 4px; text-align: right; font-weight: bold;">Totaal {{ $month->locale('nl')->translatedFormat('F') }}</td>
            <td style="padding: 3px; text-align: right; font-weight: bold;">{{ $dec($block['hours']) }}</td>
            <td style="padding: 3px; text-align: right; font-weight: bold;">{{ number_format($block['km'], 0, ',', '.') }} km</td>
            <td style="padding: 3px; text-align: right; font-weight: bold;">€ {{ $money($block['mobility']) }}</td>
        </tr>
    </tfoot>
</table>
