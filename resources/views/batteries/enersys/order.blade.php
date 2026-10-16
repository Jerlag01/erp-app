@extends('batteries.pdf.layout')

@section('title', 'Bestelling PI')

{{-- The order ("Bestelling PI") of an Enersys dossier, like the sheet of "12V enersys". --}}
@php
    $amounts = $dossier->orderAmounts();
    $money = fn (float $amount) => \App\Models\BatteryDossier::money($amount);
    $site = $dossier->project?->workSite;
    $lines = [
        ['pickup', $dossier->deliveryLine()],
        ['removal', $dossier->removalLine()],
        ['placement', $dossier->placementLine()],
        ['disposal', $dossier->removal ? $dossier->destroyLine().' inclusief afleveren verwerkingsattesten' : null],
        ['other', $dossier->orderOtherLabel()],
    ];
    $works = array_sum(array_map(fn (array $line) => $amounts[$line[0]], array_filter($lines, fn (array $line) => $line[1] !== null)));
@endphp

@section('content')
    <h1>Bestelling</h1>

    <table class="w plain" style="margin-top: 6px;">
        <tr><td class="label" style="width: 26%;">Firma:</td><td>{{ settings()->get('batteries.supplier_location') }}</td><td class="label" style="width: 12%;">Datum:</td><td style="width: 16%;">{{ now()->format('d/m/Y') }}</td></tr>
        <tr><td class="label">Refnr. firma:</td><td class="bold">{{ $dossier->reference() }}</td><td></td><td></td></tr>
        <tr><td class="label">Nr bestelbon:</td><td class="mono">{{ $dossier->po_number }}</td><td></td><td></td></tr>
        <tr><td class="label">Cow code:</td><td>{{ $dossier->cowCode() }} : {{ trim(($site?->street ?? '').', '.($site?->house_number ?? ''), ', ') }} - {{ trim(($site?->postal_code ?? '').' '.($site?->city ?? '')) }}</td><td></td><td></td></tr>
        <tr><td class="label">Omschrijving:</td><td colspan="3">{{ $dossier->description() }}</td></tr>
        <tr><td class="label">Contactpersoon {{ settings()->get('batteries.client_short') }}:</td><td colspan="3">{{ $dossier->contact_person }}</td></tr>
    </table>

    <table class="w grid" style="margin-top: 12px;">
        <thead><tr><th>Omschrijving</th><th class="num" style="width: 20%;">Bedrag</th></tr></thead>
        <tbody>
            @foreach ($lines as [$key, $label])
                @if ($label !== null)
                    <tr><td>{{ $label }}</td><td class="num">{{ $money($amounts[$key]) }}</td></tr>
                @endif
            @endforeach
            <tr><td class="bold">Totaal bedrag</td><td class="num bold">{{ $money($works) }}</td></tr>
        </tbody>
    </table>

    <h2>Algemene kosten</h2>
    <table class="w grid">
        <tr><td>Dossierkosten</td><td class="num" style="width: 20%;">{{ $money($amounts['file_costs']) }}</td></tr>
        <tr><td class="bold">Totaal bedrag</td><td class="num bold">{{ $money($amounts['file_costs']) }}</td></tr>
    </table>

    <table class="w grid" style="margin-top: 12px;">
        <tr><td class="bold">Totaal offerte</td><td class="num bold" style="width: 20%;">{{ $money($works + $amounts['file_costs']) }}</td></tr>
    </table>
@endsection
