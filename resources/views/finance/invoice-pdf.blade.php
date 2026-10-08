@php
    $f = fn (string $key) => settings()->get('finance.'.$key);
    $client = $invoice->client;
    $isCredit = $invoice->isCreditNote();
    $money = fn ($amount) => \App\Models\Invoice::money($amount);
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        @page { margin: 16mm 15mm 24mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1e293b; }
        .top { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .top td { vertical-align: top; }
        .brand { font-size: 15pt; font-weight: bold; color: {{ settings()->color('primary') }}; }
        .muted { color: #64748b; }
        .doc-title { font-size: 20pt; font-weight: bold; color: {{ settings()->color('primary') }}; text-align: right; }
        .accent { border-bottom: 3px solid {{ settings()->color('accent') }}; margin: 6px 0 14px; }
        .box { border: 1px solid #e2e8f0; padding: 8px 10px; }
        table.meta { border-collapse: collapse; margin-left: auto; }
        table.meta td { padding: 2px 0 2px 12px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.lines th { background: {{ settings()->color('primary') }}; color: #fff; text-align: left; padding: 5px 6px; font-size: 8pt; }
        table.lines td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        table.totals { border-collapse: collapse; margin-left: auto; margin-top: 10px; min-width: 45%; }
        table.totals td { padding: 3px 6px; }
        table.totals tr.grand td { font-weight: bold; font-size: 11pt; border-top: 2px solid {{ settings()->color('primary') }}; }
        .payment { margin-top: 16px; padding: 8px 10px; background: #f1f5f9; }
        .ogm { font-family: DejaVu Sans Mono, monospace; font-weight: bold; font-size: 11pt; }
        .legal { margin-top: 10px; font-weight: bold; }
        .footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 7pt; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="footer">
        {{ $f('company_name') ?: settings()->siteName() }}@if ($f('vat_number')) · {{ __('erp.finance.pdf.vat') }} {{ $f('vat_number') }}@endif
        @if ($f('iban')) · IBAN {{ trim(chunk_split($f('iban'), 4, ' ')) }}@endif @if ($f('bic')) · BIC {{ $f('bic') }}@endif
        @if ($f('invoice_footer'))<br>{{ $f('invoice_footer') }}@endif
    </div>

    <table class="top">
        <tr>
            <td style="width: 55%;">
                @if (($logo = settings()->get('appearance.logo')) && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo))
                    <img src="data:{{ \Illuminate\Support\Facades\Storage::disk('public')->mimeType($logo) }};base64,{{ base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($logo)) }}" style="max-height: 48px; max-width: 220px;"><br>
                @endif
                <div class="brand">{{ $f('company_name') ?: settings()->siteName() }}</div>
                <div class="muted">
                    {{ trim($f('street').' '.$f('house_number')) }}<br>
                    {{ trim($f('postal_code').' '.$f('city')) }}<br>
                    @if ($f('vat_number')){{ __('erp.finance.pdf.vat') }} {{ $f('vat_number') }}<br>@endif
                    @if ($f('phone')){{ $f('phone') }} @endif @if ($f('email'))· {{ $f('email') }}@endif
                </div>
            </td>
            <td>
                <div class="doc-title">{{ $isCredit ? __('erp.finance.pdf.credit_note') : __('erp.finance.pdf.invoice') }}</div>
                <table class="meta">
                    <tr><td class="muted">{{ __('erp.finance.pdf.number') }}</td><td><strong>{{ $invoice->number ?? __('erp.finance.pdf.draft') }}</strong></td></tr>
                    <tr><td class="muted">{{ __('erp.finance.pdf.date') }}</td><td>{{ $invoice->issue_date->format('d/m/Y') }}</td></tr>
                    @if (! $isCredit && $invoice->due_date)
                        <tr><td class="muted">{{ __('erp.finance.pdf.due_date') }}</td><td>{{ $invoice->due_date->format('d/m/Y') }}</td></tr>
                    @endif
                    @if ($isCredit && $invoice->creditedInvoice)
                        <tr><td class="muted">{{ __('erp.finance.pdf.credits') }}</td><td>{{ $invoice->creditedInvoice->number }} ({{ $invoice->creditedInvoice->issue_date->format('d/m/Y') }})</td></tr>
                    @endif
                    @if ($po = $invoice->poNumberList())
                        <tr><td class="muted">{{ __('erp.finance.pdf.po') }}</td><td>{{ implode(', ', $po) }}</td></tr>
                    @endif
                    @if ($invoice->client_reference)
                        <tr><td class="muted">{{ __('erp.finance.pdf.your_reference') }}</td><td>{{ $invoice->client_reference }}</td></tr>
                    @endif
                    @if ($invoice->project)
                        <tr><td class="muted">{{ __('erp.finance.pdf.project') }}</td><td>{{ $invoice->project->reference }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div class="accent"></div>

    <table class="top">
        <tr>
            <td style="width: 55%;"></td>
            <td class="box">
                <strong>{{ $client->company_name }}</strong><br>
                @foreach ($client->addressLines() as $line){{ $line }}<br>@endforeach
                @if ($client->vat_number){{ __('erp.finance.pdf.vat') }} {{ $client->vat_number }}@endif
            </td>
        </tr>
    </table>

    @if ($invoice->subject)
        <p><strong>{{ $invoice->subject }}</strong></p>
    @endif

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('erp.finance.pdf.description') }}</th>
                <th class="num">{{ __('erp.finance.pdf.quantity') }}</th>
                <th class="num">{{ __('erp.finance.pdf.unit_price') }}</th>
                <th class="num">{{ __('erp.finance.pdf.vat_rate') }}</th>
                <th class="num">{{ __('erp.finance.pdf.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $line->quantity, 3, ',', '.'), '0'), ',') }} {{ $line->unit }}</td>
                    <td class="num">{{ $money($line->unit_price) }}</td>
                    <td class="num">{{ $line->vat_rate?->getLabel() }}</td>
                    <td class="num">{{ $money($line->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @foreach ($invoice->vatBreakdown() as $group)
            <tr><td class="muted">{{ __('erp.finance.pdf.base', ['rate' => $group['rate']->getLabel()]) }}</td><td class="num">{{ $money($group['base']) }}</td></tr>
            @if ($group['rate']->percentage() > 0)
                <tr><td class="muted">{{ __('erp.finance.pdf.vat_amount', ['rate' => $group['rate']->getLabel()]) }}</td><td class="num">{{ $money($group['vat']) }}</td></tr>
            @endif
        @endforeach
        <tr><td>{{ __('erp.finance.pdf.subtotal') }}</td><td class="num">{{ $money($invoice->subtotal) }}</td></tr>
        <tr><td>{{ __('erp.finance.pdf.vat_total') }}</td><td class="num">{{ $money($invoice->vat_total) }}</td></tr>
        <tr class="grand"><td>{{ $isCredit ? __('erp.finance.pdf.total_credit') : __('erp.finance.pdf.total') }}</td><td class="num">{{ $money($invoice->total) }}</td></tr>
    </table>

    @if ($invoice->hasReverseCharge())
        <p class="legal">{{ __('erp.finance.pdf.reverse_charge') }}</p>
    @endif

    @if ($invoice->notes)
        <p>{!! nl2br(e($invoice->notes)) !!}</p>
    @endif

    @if (! $isCredit)
        <div class="payment">
            {{ __('erp.finance.pdf.pay_text', ['amount' => $money($invoice->total), 'date' => $invoice->due_date?->format('d/m/Y') ?? '—']) }}<br>
            @if ($f('iban'))IBAN <strong>{{ trim(chunk_split($f('iban'), 4, ' ')) }}</strong>@if ($f('bic')) · BIC {{ $f('bic') }}@endif<br>@endif
            @if ($invoice->structured_reference){{ __('erp.finance.pdf.structured') }} <span class="ogm">{{ $invoice->structured_reference }}</span>@endif
        </div>
    @endif
</body>
</html>
