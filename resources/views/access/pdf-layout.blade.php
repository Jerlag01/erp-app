{{-- Shared layout of the access and onboarding exports: landscape, in the reader's language. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111827; }
        h1 { font-size: 15pt; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #94a3b8; padding: 3px 5px; text-align: left; vertical-align: top; }
        th { background: #e2e8f0; font-size: 8pt; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .center { text-align: center; }
        .muted { color: #64748b; }
        tr.off td { color: #64748b; }
    </style>
</head>
<body>
    <table style="margin: 0; border: 0;">
        <tr>
            <td style="border: 0; padding: 0;"><h1>@yield('title')</h1></td>
            <td style="border: 0; padding: 0; text-align: right;">{{ __('erp.access.printed_on', ['date' => now()->format('d/m/Y H:i')]) }}</td>
        </tr>
    </table>
    @yield('content')
</body>
</html>
