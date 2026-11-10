{{-- A plain A4 page for the PDFs that are only written to the NAS (personal details, vehicle, toolbox content). --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 14mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 15pt; margin: 0 0 1mm; color: #1f4e79; }
        h2 { font-size: 10pt; margin: 5mm 0 1.5mm; padding: .8mm 2mm; background: #dbe5f1; color: #1f4e79; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #cbd5e1; padding: 1mm 2mm; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; }
        td.label { width: 34%; color: #475569; }
        .footer { position: fixed; bottom: -9mm; left: 0; right: 0; font-size: 7pt; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="footer">{{ settings()->siteName() }} · {{ now()->format('d/m/Y H:i') }}</div>
    @yield('content')
</body>
</html>
