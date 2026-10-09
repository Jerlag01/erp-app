@extends('batteries.pdf.layout')

@section('title', 'Factuuraanvraag')

@php
    $lines = $dossier->invoiceLines();
    $money = fn (float $amount) => number_format($amount, 2, ',', '.');
    $quantity = fn (float $value) => rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    $block = fn (string $key) => implode('<br>', array_map('e', \App\Support\BatteryTemplates::lines(settings()->get('batteries.'.$key))));
@endphp

@section('content')
    <h1>Factuuraanvraag</h1>
    <div class="bold" style="font-size: 10pt;">{{ $dossier->cowCode() }} &nbsp;&nbsp; Vervangen van batterijen naar {{ $dossier->batteryModel?->name }}</div>

    <table class="w plain" style="margin-top: 10px;">
        <tr>
            <td style="width: 50%;"><span class="label">Firma:</span><br>{!! $block('company_block') !!}</td>
            <td>{!! $block('seat_block') !!}</td>
        </tr>
    </table>

    <table class="w grid" style="margin-top: 10px;">
        <tr>
            <td class="label">Factuuraanvraag:</td><td class="bold">{{ $dossier->invoice_kind?->getLabel() }}</td>
            <td class="label">Raamcontract:</td><td class="mono">{{ settings()->get('batteries.framework_contract') }}</td>
        </tr>
        <tr>
            <td class="label">Datum:</td><td>{{ today()->format('d/m/Y') }}</td>
            <td class="label">Onze ref.:</td><td class="bold">{{ $dossier->reference() }}</td>
        </tr>
        <tr>
            <td class="label">PO n°:</td><td class="mono">{{ $dossier->po_number }}</td>
            <td class="label">Cow code:</td><td class="bold">{{ $dossier->cowCode() }}</td>
        </tr>
    </table>

    <h2>Levering van batterijen:</h2>
    <table class="w grid">
        <thead>
            <tr><th style="width: 9%;">Nr.</th><th>Omschrijving</th><th class="num" style="width: 10%;">Aantal</th><th class="num" style="width: 14%;">Eenh. prijs</th><th class="num" style="width: 15%;">Totaal prijs</th></tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['post'] }}</td>
                    <td>{{ $line['description'] }}@if ($line['detail'])<br><span class="muted">{{ $line['detail'] }}</span>@endif</td>
                    <td class="num">{{ $quantity($line['quantity']) }}</td>
                    <td class="num">{{ $money($line['unit_price']) }}</td>
                    <td class="num">€ {{ $money($line['total']) }}</td>
                </tr>
            @endforeach
            <tr><td colspan="4" class="num bold">Totaal:</td><td class="num bold">€ {{ $money($dossier->invoiceTotal()) }}</td></tr>
            <tr>
                <td colspan="2" class="num bold">Factuur</td>
                <td class="num">{{ $quantity((float) $dossier->invoice_percentage) }} %</td>
                <td class="num">{{ $dossier->invoice_kind?->getLabel() }}</td>
                <td class="num bold">€ {{ $money($dossier->invoiceAmount()) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
