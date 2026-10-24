{{-- The BAE order, printed like the Excel "calc.xlsx", sheet "Order" (always in English). --}}
@php
    $money = fn (float $value): string => number_format($value, 2, ',', '.');
    $lines = \App\Models\BaeOrder::LINES;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Order {{ $order->order_number }}</title>
    <style>
        @page { margin: 14mm 14mm 14mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111827; }
        table { border-collapse: collapse; }
        .w { width: 100%; }
        h1 { font-size: 20pt; margin: 0; color: #0b4a9b; }
        .head td { padding: 2px 4px; vertical-align: top; }
        .head .label { width: 28mm; font-weight: bold; }
        .grid td, .grid th { border: 1px solid #94a3b8; padding: 3px 5px; vertical-align: top; }
        .grid th { background: #e2e8f0; text-align: left; font-size: 7.5pt; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .muted { color: #64748b; }
        .total td { font-weight: bold; background: #f1f5f9; }
        .block td { padding: 2px 4px; vertical-align: top; }
        .block .label { width: 28mm; font-weight: bold; }
    </style>
</head>
<body>
    <table class="w">
        <tr>
            <td style="vertical-align: top;">
                @if ($logo)
                    <img src="{{ $logo }}" style="max-height: 48px; max-width: 220px;">
                @endif
            </td>
            <td style="text-align: right; vertical-align: top;"><img src="{{ $seal }}" style="height: 70px;"></td>
        </tr>
    </table>

    <h1>Order</h1>

    <table class="w head" style="margin-top: 8px;">
        <tr><td class="label">Order nr</td><td>{{ $order->order_number }}</td></tr>
        <tr><td class="label">Order date</td><td>{{ $order->order_date?->format('d/m/Y') }}</td></tr>
        <tr><td class="label">Contract</td><td>{{ $order->contract }}</td></tr>
        <tr><td colspan="2" style="height: 6px;"></td></tr>
        <tr><td class="label">Subject :</td><td>{{ $order->subject() }}</td></tr>
        <tr><td class="label">COW Code:</td><td>{{ $order->cowLine() }}</td></tr>
        <tr><td class="label">Our reference:</td><td>{{ $order->ourReference() }}</td></tr>
    </table>

    <table class="w grid" style="margin-top: 14px;">
        <thead>
            <tr>
                <th>Battery</th>
                <th>Description</th>
                <th class="num">Battery<br>units</th>
                <th class="num">Unit per<br>battery</th>
                <th class="num">Units<br>total</th>
                <th class="num">Price per<br>2V</th>
                <th class="num">Price per<br>battery</th>
                <th class="num">Price<br>total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="bold">{{ $order->batteryTitle() }}</td>
                <td>{{ $order->batteryDescription() }}</td>
                <td class="num">{{ $order->batteryCount() }}</td>
                <td class="num">{{ $order->cells_per_battery }}</td>
                <td class="num">{{ $order->totalCells() }}</td>
                <td class="num">{{ $money((float) $order->cell_price) }}</td>
                <td class="num">{{ $money($order->batteryPrice()) }}</td>
                <td class="num">{{ $money($order->batteriesTotal()) }}</td>
            </tr>
            @foreach ($lines as $key => $line)
                <tr>
                    <td>{{ $line['name'] }}</td>
                    <td>{{ $order->line($key)['code'] }}</td>
                    <td class="num">{{ $order->batteryCount() }}</td>
                    <td class="num">{{ $order->line($key)['per_battery'] }}</td>
                    <td class="num">{{ $order->lineTotal($key) }}</td>
                    <td class="num muted">incl.</td>
                    <td></td>
                    <td></td>
                </tr>
            @endforeach
            <tr>
                <td>Rack</td>
                <td>{{ $order->rackDescription() }}</td>
                <td></td>
                <td></td>
                <td class="num">{{ $order->batteryCount() }}</td>
                <td></td>
                <td class="num">{{ $money($order->rackPrice()) }}</td>
                <td class="num">{{ $money($order->rackTotal()) }}</td>
            </tr>
            <tr class="total">
                <td colspan="7" class="num">Total price without VAT (Euro):</td>
                <td class="num">{{ $money($order->total()) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="w block" style="margin-top: 16px;">
        <tr><td class="label">Delivery date:</td><td>{{ $order->delivery_date }}</td></tr>
        <tr><td class="label">Delivery adres :</td><td>{{ $order->delivery_address }}</td></tr>
    </table>

    <table class="w block" style="margin-top: 12px;">
        <tr>
            <td class="label">Contact:</td>
            <td style="width: 60mm;">{{ $order->contact_name }}</td>
            <td style="width: 18mm;">Phone</td><td>{{ $order->contact_phone }}</td>
        </tr>
        @if (filled($order->contact_fax))
            <tr><td></td><td></td><td>Fax</td><td>{{ $order->contact_fax }}</td></tr>
        @endif
        <tr><td></td><td></td><td>Mobile</td><td>{{ $order->contact_mobile }}</td></tr>
        <tr><td></td><td></td><td>e-mail</td><td>{{ $order->contact_email }}</td></tr>
        <tr><td colspan="4" style="height: 6px;"></td></tr>
        <tr><td class="label">Facturation:</td><td colspan="3">{!! nl2br(e($order->billing_block)) !!}</td></tr>
        <tr><td colspan="4" style="height: 6px;"></td></tr>
        <tr><td class="label">Remarques:</td><td colspan="3">{!! nl2br(e($order->remarks)) !!}</td></tr>
    </table>
</body>
</html>
