{{-- The monthly advances of workers and servants (A4 landscape), in the reader's language. --}}
@php
    $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',');
    $money = fn ($value) => '€ '.number_format((float) $value, 2, ',', '.');
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111827; }
        h1 { font-size: 15pt; margin: 0 0 2mm; }
        h2 { font-size: 11pt; margin: 6mm 0 2mm; }
        .meta { color: #475569; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e2e8f0; padding: 4px; text-align: right; }
        td { padding: 4px; border-bottom: 1px solid #e2e8f0; text-align: right; }
        th:first-child, td:first-child { text-align: left; }
        tfoot td { font-weight: bold; border-top: 2px solid #94a3b8; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">{{ __('erp.pdf.period', ['from' => $from->format('d/m/Y'), 'until' => $until->format('d/m/Y')]) }} · {{ __('erp.pdf.printed_on', ['date' => now()->format('d/m/Y H:i')]) }}</div>
    <div class="meta">{{ __('erp.hr.advances.formula') }}</div>

    @foreach ($groups as $group => $rows)
        <h2>{{ __('erp.hr.advances.'.$group) }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('erp.hr.advances.employee') }}</th>
                    <th>{{ __('erp.hr.advances.start_value') }}</th>
                    <th>{{ __('erp.hr.advances.workdays') }}</th>
                    <th>{{ __('erp.hr.advances.vacation') }}</th>
                    <th>{{ __('erp.hr.advances.family') }}</th>
                    <th>{{ __('erp.hr.advances.unpaid') }}</th>
                    <th>{{ __('erp.hr.advances.rva') }}</th>
                    <th>{{ __('erp.hr.advances.worked') }}</th>
                    <th>{{ __('erp.hr.advances.advance') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php($result = $row['result'])
                    <tr>
                        <td>{{ $row['employee']->fullName() }}</td>
                        <td>{{ $money($result['base']) }}</td>
                        <td>{{ $number($result['total']) }}</td>
                        <td>{{ $number($result['vacation']) }}@if ($result['vacation_counted'])*@endif</td>
                        <td>{{ $number($result['family']) }}</td>
                        <td>{{ $number($result['unpaid']) }}</td>
                        <td>{{ $number($result['rva']) }}</td>
                        <td>{{ $number($result['worked']) }}</td>
                        <td>{{ $money($result['advance']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9">{{ __('erp.hr.advances.none') }}</td></tr>
                @endforelse
            </tbody>
            @if ($rows !== [])
                <tfoot>
                    <tr><td colspan="8">{{ __('erp.pdf.total') }}</td><td>{{ $money(array_sum(array_map(fn ($row) => $row['result']['advance'], $rows))) }}</td></tr>
                </tfoot>
            @endif
        </table>
    @endforeach
    <p class="meta">* {{ __('erp.hr.advances.vacation_counted') }}</p>
</body>
</html>
