{{-- Preview of the invoice request lines of a battery dossier (saved values). --}}
@php
    $money = fn (float $amount) => \App\Models\BatteryDossier::money($amount);
    $quantity = fn (float $value) => rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
@endphp
<div style="overflow-x: auto;">
    <table style="width: 100%; font-size: .8125rem; border-collapse: collapse;">
        <thead>
            <tr style="text-align: left; border-bottom: 1px solid rgb(148 163 184 / .4);">
                <th style="padding: .375rem;">Nr.</th>
                <th style="padding: .375rem;">{{ __('erp.fields.description') }}</th>
                <th style="padding: .375rem; text-align: right;">{{ __('erp.batteries.quantity') }}</th>
                <th style="padding: .375rem; text-align: right;">{{ __('erp.batteries.unit_price') }}</th>
                <th style="padding: .375rem; text-align: right;">{{ __('erp.batteries.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dossier->invoiceLines() as $line)
                <tr style="border-bottom: 1px solid rgb(148 163 184 / .2); {{ $line['total'] == 0 ? 'opacity: .55;' : '' }}">
                    <td style="padding: .375rem; white-space: nowrap;">{{ $line['post'] }}</td>
                    <td style="padding: .375rem;">{{ $line['description'] }}@if ($line['detail'])<br><span style="opacity: .7;">{{ $line['detail'] }}</span>@endif</td>
                    <td style="padding: .375rem; text-align: right; font-family: var(--erp-mono);">{{ $quantity($line['quantity']) }}</td>
                    <td style="padding: .375rem; text-align: right; font-family: var(--erp-mono); white-space: nowrap;">{{ $money($line['unit_price']) }}</td>
                    <td style="padding: .375rem; text-align: right; font-family: var(--erp-mono); white-space: nowrap;">{{ $money($line['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight: 700;">
                <td colspan="4" style="padding: .375rem; text-align: right;">{{ __('erp.batteries.total') }}</td>
                <td style="padding: .375rem; text-align: right; font-family: var(--erp-mono); white-space: nowrap;">{{ $money($dossier->invoiceTotal()) }}</td>
            </tr>
            <tr style="font-weight: 700;">
                <td colspan="4" style="padding: .375rem; text-align: right;">{{ $dossier->invoice_kind?->getLabel() }} ({{ $quantity((float) $dossier->invoice_percentage) }} %)</td>
                <td style="padding: .375rem; text-align: right; font-family: var(--erp-mono); white-space: nowrap;">{{ $money($dossier->invoiceAmount()) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
