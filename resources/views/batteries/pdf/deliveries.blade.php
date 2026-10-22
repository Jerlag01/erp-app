{{-- The delivery overview, printed like the Excel "Overzicht Levering Batterijen", in the reader's language. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('erp.pdf.deliveries.title') }}</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111827; }
        h1 { font-size: 15pt; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #94a3b8; padding: 3px 5px; text-align: left; vertical-align: top; }
        th { background: #e2e8f0; font-size: 8pt; }
        .num { text-align: right; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        tr.done td { color: #64748b; }
    </style>
</head>
<body>
    <table style="margin: 0; border: 0;">
        <tr>
            <td style="border: 0; padding: 0;"><h1>{{ __('erp.pdf.deliveries.orders', ['location' => settings()->get('batteries.supplier_location')]) }}</h1></td>
            <td style="border: 0; padding: 0; text-align: right;">{{ __('erp.pdf.printed_on', ['date' => today()->format('d/m/Y')]) }}</td>
        </tr>
    </table>
    <table>
        <thead>
            <tr>
                <th>{{ __('erp.pdf.deliveries.location') }}</th>
                <th>{{ __('erp.pdf.deliveries.order') }}</th>
                <th>{{ __('erp.pdf.deliveries.confirmation') }}</th>
                <th>{{ __('erp.pdf.deliveries.type') }}</th>
                <th class="num">{{ __('erp.pdf.deliveries.pieces') }}</th>
                <th>{{ __('erp.pdf.deliveries.planned', ['supplier' => 'BAE Berlin']) }}</th>
                <th>{{ __('erp.pdf.deliveries.actual') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($deliveries as $delivery)
                <tr @class(['done' => $delivery->executed])>
                    <td>{{ $delivery->location() }}</td>
                    <td class="mono">{{ $delivery->orderNumber() }}</td>
                    <td class="mono">{{ $delivery->order_confirmation }}</td>
                    <td>{{ $delivery->batteryModel?->name }}</td>
                    <td class="num">{{ $delivery->pieces }}</td>
                    <td>{{ $delivery->planned_delivery_date?->format('d/m/Y') }}</td>
                    <td>{{ $delivery->delivered_at?->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7">{{ __('erp.pdf.deliveries.none') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
