@php($invoice = $getRecord())
<div style="overflow-x: auto;">
    <table style="width: 100%; border-collapse: collapse; font-size: .875rem;" data-invoice-lines>
        <thead>
            <tr style="text-align: left; font-size: .75rem; text-transform: uppercase; opacity: .7;">
                <th style="padding: .4rem .5rem;">{{ __('erp.finance.pdf.description') }}</th>
                <th style="padding: .4rem .5rem; text-align: right;">{{ __('erp.finance.pdf.quantity') }}</th>
                <th style="padding: .4rem .5rem; text-align: right;">{{ __('erp.finance.pdf.unit_price') }}</th>
                <th style="padding: .4rem .5rem; text-align: right;">{{ __('erp.finance.pdf.vat_rate') }}</th>
                <th style="padding: .4rem .5rem; text-align: right;">{{ __('erp.finance.pdf.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr style="border-top: 1px solid rgb(148 163 184 / .25);">
                    <td style="padding: .4rem .5rem;">{{ $line->description }}</td>
                    <td style="padding: .4rem .5rem; text-align: right; white-space: nowrap;">{{ rtrim(rtrim(number_format((float) $line->quantity, 3, ',', '.'), '0'), ',') }} {{ $line->unit }}</td>
                    <td style="padding: .4rem .5rem; text-align: right; white-space: nowrap;">{{ \App\Models\Invoice::money($line->unit_price) }}</td>
                    <td style="padding: .4rem .5rem; text-align: right; white-space: nowrap;">{{ $line->vat_rate?->getLabel() }}</td>
                    <td style="padding: .4rem .5rem; text-align: right; white-space: nowrap;">{{ \App\Models\Invoice::money($line->line_total) }}</td>
                </tr>
            @endforeach
            @foreach ($invoice->vatBreakdown() as $group)
                <tr style="border-top: 1px solid rgb(148 163 184 / .25); opacity: .75;">
                    <td colspan="4" style="padding: .3rem .5rem; text-align: right;">{{ __('erp.finance.pdf.vat_amount', ['rate' => $group['rate']->getLabel()]) }} ({{ __('erp.finance.pdf.base', ['rate' => '']) }} {{ \App\Models\Invoice::money($group['base']) }})</td>
                    <td style="padding: .3rem .5rem; text-align: right;">{{ \App\Models\Invoice::money($group['vat']) }}</td>
                </tr>
            @endforeach
            <tr style="border-top: 2px solid var(--primary-500); font-weight: 700;">
                <td colspan="4" style="padding: .5rem; text-align: right;">{{ $invoice->isCreditNote() ? __('erp.finance.pdf.total_credit') : __('erp.finance.pdf.total') }}</td>
                <td style="padding: .5rem; text-align: right;">{{ \App\Models\Invoice::money($invoice->total) }}</td>
            </tr>
        </tbody>
    </table>
    @if ($invoice->hasReverseCharge())
        <p style="margin-top: .5rem; font-weight: 600;">{{ __('erp.finance.pdf.reverse_charge') }}</p>
    @endif
</div>
