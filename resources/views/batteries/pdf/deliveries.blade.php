{{-- The delivery overview, printed like the Excel "Overzicht Levering Batterijen". --}}
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Overzicht levering batterijen</title>
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
            <td style="border: 0; padding: 0;"><h1>Übersicht Aufträge {{ settings()->get('batteries.supplier_location') }}</h1></td>
            <td style="border: 0; padding: 0; text-align: right;">{{ today()->format('d/m/Y') }}</td>
        </tr>
    </table>
    <table>
        <thead>
            <tr>
                <th>Location:</th>
                <th>Order n°:</th>
                <th>N° order confirmation:</th>
                <th>battery type:</th>
                <th class="num">pcs.</th>
                <th>Delivery date planned from BAE Berlin:</th>
                <th>Actual delivery date</th>
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
                <tr><td colspan="7">—</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
