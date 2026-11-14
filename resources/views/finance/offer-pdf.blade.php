@php
    $f = fn (string $key) => settings()->get('finance.'.$key);
    $money = fn ($amount) => \App\Models\Invoice::money($amount);
    $qty = fn ($quantity) => $quantity === null ? '' : rtrim(rtrim(number_format((float) $quantity, 3, ',', '.'), '0'), ',');
    $cell = fn ($amount) => $amount === null || (float) $amount == 0.0 ? '' : $money($amount);
    $company = $f('company_name') ?: settings()->siteName();
    $works = $offer->chapters();
    $general = $offer->chapters(true);
    $cow = $offer->project?->workSite?->cow_code;
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $offer->number }}</title>
    <style>
        @page { margin: 14mm 12mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111; }
        .company { text-align: center; font-size: 9pt; margin-bottom: 10px; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #555; }
        .footer .page:after { content: counter(page); }
        table { border-collapse: collapse; width: 100%; }
        .head td { padding: 2px 4px; }
        .boxed { border: 1px solid #111; margin-bottom: 6px; }
        .boxed td { padding: 2px 4px; }
        .label { font-weight: bold; font-style: italic; width: 22%; }
        .strong { font-weight: bold; }
        table.grid { margin-top: 6px; }
        table.grid th { background: #d9d9d9; border: 1px solid #111; padding: 2px 3px; font-size: 7.5pt; }
        table.grid td { border-left: 1px solid #111; border-right: 1px solid #111; padding: 1px 3px; vertical-align: top; }
        table.grid td.shade { background: #f2f2f2; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .neg { color: #c00000; }
        .chapter td { padding-top: 8px; }
        .chapter-title { font-weight: bold; font-style: italic; text-decoration: underline; }
        .total td { border-top: 2px solid #111; border-bottom: 2px solid #111; font-weight: bold; color: {{ settings()->color('primary') }}; }
        .sum td { border-top: 1px solid #111; font-weight: bold; }
        .grand td { border-top: 2px solid #111; border-bottom: 2px solid #111; font-weight: bold; }
        .address { border: 1px solid #111; padding: 3px 5px; font-weight: bold; }
        .closing td { border-bottom: 1px solid #111; }
        .summary { border: 1px solid #111; margin-top: 10px; width: 75%; }
        .summary td { padding: 1px 5px; }
        .summary tr.last td { background: #f2f2f2; font-weight: bold; }
        .remarks { margin-top: 14px; }
        .remarks h4 { margin: 0 0 6px; text-decoration: underline; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="footer"><table><tr><td>{{ \App\Actions\Finance\OfferPdf::filename($offer) }}</td><td class="num"><span class="page"></span></td></tr></table></div>

    <div class="company">{{ $company }}</div>

    <div class="strong" style="font-style: italic;">{{ __('erp.finance.offers.pdf_title') }}</div>
    <table class="boxed"><tr><td class="label">{{ __('erp.finance.offers.case') }}:</td><td class="strong">{{ $offer->client_reference ?: $offer->project?->reference }}</td><td class="num">{{ $offer->number }}</td></tr></table>
    <table class="boxed"><tr><td class="label">{{ __('erp.finance.offers.subject') }}:</td><td class="strong">{{ $offer->subject }}</td></tr></table>

    <table class="head">
        <tr>
            <td class="label">{{ __('erp.finance.offers.client_reference') }}:</td><td class="strong">{{ $offer->client_reference }}</td>
            <td class="num" style="font-style: italic;">{{ __('erp.finance.offers.date') }}:</td><td class="num strong" style="width: 16%;">{{ $offer->offer_date->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('erp.finance.offers.our_reference') }}:</td><td class="strong">{{ $offer->our_reference }}</td>
            @if ($offer->valid_until)
                <td class="num" style="font-style: italic;">{{ __('erp.finance.offers.valid_until') }}:</td><td class="num">{{ $offer->valid_until->format('d/m/Y') }}</td>
            @else
                <td></td><td></td>
            @endif
        </tr>
        @if ($offer->client)
            <tr><td class="label">{{ __('erp.resources.client.singular') }}:</td><td colspan="3">{{ $offer->client->company_name }}@if ($offer->client->vat_number) · {{ __('erp.finance.pdf.vat') }} {{ $offer->client->vat_number }}@endif</td></tr>
        @endif
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 5%;">{{ __('erp.finance.offers.item') }}</th>
                <th style="width: 43%; text-align: left;">{{ __('erp.finance.offers.supply_heading') }}<br>{{ __('erp.finance.offers.install_heading') }}</th>
                <th style="width: 7%;">{{ __('erp.finance.pdf.quantity') }}<br>st/m</th>
                <th style="width: 11%;">{{ __('erp.finance.offers.unit_price') }}<br>{{ __('erp.finance.offers.supply') }}</th>
                <th style="width: 11%;">{{ __('erp.finance.offers.unit_price') }}<br>{{ __('erp.finance.offers.install') }}</th>
                <th style="width: 11.5%;">{{ __('erp.finance.offers.total_price') }}<br>{{ __('erp.finance.offers.supply') }}</th>
                <th style="width: 11.5%;">{{ __('erp.finance.offers.total_price') }}<br>{{ __('erp.finance.offers.install') }}</th>
            </tr>
        </thead>
        <tbody>
            @if ($offer->site_address)
                <tr class="chapter">
                    <td></td>
                    <td><div class="address"><table><tr><td style="border: 0; width: 22%; padding: 0;">{{ __('erp.finance.offers.address') }}:</td><td style="border: 0; padding: 0;">{!! nl2br(e($offer->site_address)) !!}</td></tr></table></div></td>
                    <td></td><td class="shade"></td><td></td><td class="shade"></td><td></td>
                </tr>
            @endif

            @foreach ([$works, $general] as $index => $chapters)
                @foreach ($chapters as $chapter)
                    <tr class="chapter">
                        <td class="strong" style="font-style: italic;">{{ $chapter['category']?->code }}</td>
                        <td class="chapter-title">{{ $chapter['category']?->name ?? __('erp.finance.offers.other') }}</td>
                        <td></td><td class="shade"></td><td></td><td class="shade"></td><td></td>
                    </tr>
                    @foreach ($chapter['lines'] as $line)
                        <tr>
                            <td></td>
                            <td>{{ $line->description }}</td>
                            <td class="num">{{ $qty($line->quantity) }}</td>
                            <td class="num shade {{ (float) $line->supply_price < 0 ? 'neg' : '' }}">{{ $line->isText() ? '' : $cell($line->supply_price) }}</td>
                            <td class="num {{ (float) $line->install_price < 0 ? 'neg' : '' }}">{{ $line->isText() ? '' : $cell($line->install_price) }}</td>
                            <td class="num shade {{ (float) $line->supply_total < 0 ? 'neg' : '' }}">{{ $line->isText() ? '' : $cell($line->supply_total) }}</td>
                            <td class="num {{ (float) $line->install_total < 0 ? 'neg' : '' }}">{{ $line->isText() ? '' : $cell($line->install_total) }}</td>
                        </tr>
                    @endforeach
                    @if ($index === 0)
                        <tr class="total">
                            <td></td>
                            <td>{{ __('erp.finance.offers.total') }} <span style="float: right;">{{ $money($chapter['total']) }}</span></td>
                            <td></td><td class="shade"></td><td></td><td class="shade"></td><td></td>
                        </tr>
                    @endif
                @endforeach

                @if ($index === 0)
                    <tr class="sum">
                        <td></td>
                        <td>{{ __('erp.finance.offers.works_price') }}</td>
                        <td></td><td></td><td></td>
                        <td class="num">{{ $money($offer->supply_total) }}</td>
                        <td class="num">{{ $money($offer->install_total) }}</td>
                    </tr>
                    <tr class="grand">
                        <td></td>
                        <td colspan="5">{{ __('erp.finance.offers.works_total') }}</td>
                        <td class="num">{{ $money($offer->worksTotal()) }}</td>
                    </tr>
                @endif
            @endforeach
            <tr class="closing"><td colspan="7"></td></tr>
        </tbody>
    </table>

    <table class="boxed" style="margin-top: 10px;">
        <tr class="strong"><td>{{ __('erp.finance.offers.grand_total') }}</td><td class="num">{{ $money($offer->total) }}</td></tr>
    </table>

    <div class="page-break"></div>

    <table class="head" style="width: 60%;">
        <tr><td class="label" style="text-decoration: underline;">{{ __('erp.finance.offers.company') }}:</td><td>{{ $company }}</td></tr>
        @if ($f('vat_number'))<tr><td class="label" style="text-decoration: underline;">{{ __('erp.finance.offers.vat_number') }}:</td><td>{{ $f('vat_number') }}</td></tr>@endif
        @if ($cow)<tr><td class="label" style="text-decoration: underline;">{{ __('erp.finance.offers.cow_code') }}:</td><td>{{ $cow }}</td></tr>@endif
        @if ($offer->project)<tr><td class="label" style="text-decoration: underline;">{{ __('erp.resources.project.singular') }}:</td><td>{{ $offer->project->reference }}</td></tr>@endif
        <tr><td class="label" style="text-decoration: underline;">{{ __('erp.finance.offers.description') }}:</td><td>{{ $offer->subject }}</td></tr>
    </table>

    <table class="summary">
        <tr><td class="strong" style="text-decoration: underline;" colspan="3">{{ __('erp.finance.offers.pdf_title') }}</td></tr>
        <tr><td>{{ __('erp.finance.offers.sum_supply') }}:</td><td class="num">{{ number_format((float) $offer->supply_total, 2, ',', '.') }}</td><td>EUR</td></tr>
        <tr><td>{{ __('erp.finance.offers.sum_install') }}:</td><td class="num">{{ number_format((float) $offer->install_total, 2, ',', '.') }}</td><td>EUR</td></tr>
        <tr><td>{{ __('erp.finance.offers.sum_general') }}:</td><td class="num">{{ number_format((float) $offer->general_total, 2, ',', '.') }}</td><td>EUR</td></tr>
        <tr class="last"><td>{{ __('erp.finance.offers.sum_total') }}:</td><td class="num" style="text-decoration: underline;">{{ number_format((float) $offer->total, 2, ',', '.') }}</td><td>EUR</td></tr>
    </table>

    @if ((float) $offer->hourly_rate > 0)
        <table class="summary">
            <tr><td class="strong" style="text-decoration: underline;" colspan="2">{{ __('erp.finance.offers.workdays') }}:</td></tr>
            <tr><td style="width: 30%;">{{ __('erp.finance.offers.hourly_rate') }}:</td><td>{{ number_format((float) $offer->hourly_rate, 2, ',', '.') }} EUR</td></tr>
            @foreach (\App\Models\Offer::crewSizes() as $crew)
                <tr><td>{{ __('erp.finance.offers.crew') }}:</td><td>{{ $crew }} {{ __('erp.finance.offers.men') }}</td></tr>
            @endforeach
            <tr><td>{{ __('erp.finance.offers.hours_per_day') }}:</td><td>{{ rtrim(rtrim(number_format((float) $offer->hours_per_day, 2, ',', '.'), '0'), ',') }} {{ __('erp.finance.offers.hours_unit') }}</td></tr>
            <tr><td>{{ __('erp.finance.offers.total_hours') }}:</td><td>{{ number_format($offer->labourHours(), 2, ',', '.') }} {{ __('erp.finance.offers.hours_unit') }}</td></tr>
            @foreach ($offer->workdays() as $crew => $days)
                <tr><td>{{ __('erp.finance.offers.number_of_days') }}:</td><td>{{ number_format($days, 2, ',', '.') }} {{ __('erp.finance.offers.with_men', ['crew' => $crew]) }}</td></tr>
            @endforeach
        </table>
    @endif

    @if (filled($offer->remarks))
        <div class="remarks">
            <h4>** {{ __('erp.finance.offers.remarks') }}:</h4>
            <div style="margin-left: 24px;">{!! nl2br(e($offer->remarks)) !!}</div>
        </div>
    @endif
</body>
</html>
