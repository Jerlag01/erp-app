{{-- A list of charging stations or 4G routers as PDF. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 10mm 8mm 12mm; }
        body { font-family: Helvetica, Arial, sans-serif; color: #0f172a; }
        h1 { font-size: 12pt; margin: 0 0 2px; }
        .meta { font-size: 7pt; color: #475569; margin-bottom: 6px; }
        table { border-collapse: collapse; width: 100%; font-size: {{ $fontSize }}pt; }
        th, td { border: 1px solid #94a3b8; padding: 2px 3px; text-align: left; vertical-align: top; }
        th { background: #d9e1f2; }
        tr:nth-child(even) td { background: #f8fafc; }
        thead { display: table-header-group; }
        .footer { position: fixed; bottom: -8mm; right: 0; font-size: 6.5pt; color: #64748b; }
    </style>
</head>
<body>
    <div class="footer">{{ settings()->siteName() }} · {{ now()->format('d/m/Y H:i') }}</div>
    <h1>{{ $title }}</h1>
    <div class="meta">{{ trans_choice('erp.charging.export_count', count($rows), ['count' => count($rows)]) }}</div>
    <table>
        <thead>
            <tr>@foreach ($headers as $header)<th>{{ $header }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $value)<td>{{ $value }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($headers) }}">—</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
