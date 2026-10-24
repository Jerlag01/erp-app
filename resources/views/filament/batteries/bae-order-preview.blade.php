{{-- The BAE order as it stands in the form, laid out like the Excel sheet "Order" (amounts follow its formulas). --}}
@php
    $money = fn (float $value): string => '€ '.number_format($value, 2, ',', '.');
    $cell = 'padding: .3rem .5rem; border-bottom: 1px solid rgb(148 163 184 / .35); vertical-align: top;';
    $num = $cell.' text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums;';
@endphp
<div style="font-size: .85rem;" data-bae-order-preview>
    <dl style="display: grid; grid-template-columns: max-content 1fr; gap: .2rem 1rem; margin-bottom: .9rem;">
        <dt style="font-weight: 600;">Order nr</dt><dd style="font-family: var(--erp-mono);">{{ $order->order_number ?: '—' }}</dd>
        <dt style="font-weight: 600;">Subject</dt><dd>{{ $order->subject() }}</dd>
        <dt style="font-weight: 600;">COW Code</dt><dd>{{ $order->cowLine() }}</dd>
        <dt style="font-weight: 600;">Our reference</dt><dd>{{ $order->ourReference() }}</dd>
    </dl>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; min-width: 640px;">
            <thead>
                <tr style="text-align: left; font-size: .75rem; opacity: .75;">
                    <th style="{{ $cell }}">Battery</th>
                    <th style="{{ $cell }}">Description</th>
                    <th style="{{ $num }}">Battery units</th>
                    <th style="{{ $num }}">Unit per battery</th>
                    <th style="{{ $num }}">Units total</th>
                    <th style="{{ $num }}">Price per 2V</th>
                    <th style="{{ $num }}">Price per battery</th>
                    <th style="{{ $num }}">Price total</th>
                </tr>
            </thead>
            <tbody>
                <tr style="font-weight: 600;">
                    <td style="{{ $cell }}">{{ $order->batteryTitle() ?: '—' }}</td>
                    <td style="{{ $cell }}">{{ $order->batteryDescription() }}</td>
                    <td style="{{ $num }}">{{ $order->batteryCount() }}</td>
                    <td style="{{ $num }}">{{ (int) $order->cells_per_battery }}</td>
                    <td style="{{ $num }}">{{ $order->totalCells() }}</td>
                    <td style="{{ $num }}">{{ $money((float) $order->cell_price) }}</td>
                    <td style="{{ $num }}">{{ $money($order->batteryPrice()) }}</td>
                    <td style="{{ $num }}">{{ $money($order->batteriesTotal()) }}</td>
                </tr>
                @foreach (\App\Models\BaeOrder::LINES as $key => $line)
                    <tr>
                        <td style="{{ $cell }}">{{ $line['name'] }}</td>
                        <td style="{{ $cell }}">{{ $order->line($key)['code'] }}</td>
                        <td style="{{ $num }}">{{ $order->batteryCount() }}</td>
                        <td style="{{ $num }}">{{ $order->line($key)['per_battery'] }}</td>
                        <td style="{{ $num }}" data-line-total="{{ $key }}">{{ $order->lineTotal($key) }}</td>
                        <td style="{{ $num }} opacity: .6;">incl.</td>
                        <td style="{{ $cell }}"></td>
                        <td style="{{ $cell }}"></td>
                    </tr>
                @endforeach
                <tr>
                    <td style="{{ $cell }}">Rack</td>
                    <td style="{{ $cell }}">{{ $order->rackDescription() }}</td>
                    <td style="{{ $cell }}"></td>
                    <td style="{{ $cell }}"></td>
                    <td style="{{ $num }}">{{ $order->batteryCount() }}</td>
                    <td style="{{ $cell }}"></td>
                    <td style="{{ $num }}">{{ $money($order->rackPrice()) }}</td>
                    <td style="{{ $num }}">{{ $money($order->rackTotal()) }}</td>
                </tr>
                <tr style="font-weight: 700;">
                    <td colspan="7" style="{{ $num }}">{{ __('erp.batteries.bae_order.fields.total') }}</td>
                    <td style="{{ $num }}" data-order-total>{{ $money($order->total()) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
