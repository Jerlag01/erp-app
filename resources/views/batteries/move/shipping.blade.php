{{-- The shipping note of a battery move (605): the batteries from the source to the destination site. Bilingual like the 604 shipping note; no waste (the old batteries have their own note). --}}
@extends('batteries.pdf.layout')

@section('title', 'Verzendnota verplaatsing')

@php
    $s = fn (string $key) => settings()->get('batteries.'.$key);
    $lines = fn (string $key) => \App\Support\BatteryTemplates::lines($s($key));
    $client = $s('client_short');
    $from = \App\Models\MoveDossier::siteLines($dossier->fromSite);
    $to = \App\Models\MoveDossier::siteLines($dossier->destination());
    $goods = fn (string $locale) => __('battery_documents.move.goods', ['count' => (int) $dossier->new_count, 'type' => trim(implode(' ', array_filter([$dossier->brand, $dossier->typeName()]))) ?: '—', 'ah' => (int) $dossier->new_capacity_ah], $locale);
@endphp

@section('styles')
    body { font-size: 8pt; line-height: 1.2; }
    .ship td { padding: 3px 4px; vertical-align: top; border-bottom: 1px solid #e2e8f0; }
    .ship td.k { width: 34%; color: #334155; }
    .ship td.k i { color: #64748b; font-style: normal; font-size: 7pt; }
    .sign .box { height: 60px; padding: 3px 5px; }
@endsection

@section('content')
    <div style="text-align: center;">
        <h1 style="margin-bottom: 0; font-size: 14pt;">{{ __('battery_documents.move.shipping_title', [], 'nl') }} <span class="muted" style="font-size: 10pt; font-weight: normal;">/ {{ __('battery_documents.move.shipping_title', [], 'fr') }}</span></h1>
    </div>

    <table class="w ship" style="margin-top: 6px;">
        <tr><td class="k">Ref PI</td><td class="bold">{{ $dossier->reference() }}</td></tr>
        <tr><td class="k">Ref klant <i>/ client</i></td><td>{{ $client }} PO N°: {{ $dossier->po_number }}</td></tr>
        <tr><td class="k">Datum <i>/ Date</i></td><td>{{ $dossier->execution_date?->format('d/m/Y') }}</td></tr>
        <tr><td class="k">Verzender (ophaling) <i>/ Expéditeur (enlèvement)</i></td><td>{{ $client }}: <strong>{{ $dossier->sourceCowCode() }}</strong><br>{!! implode('<br>', array_map('e', $from)) !!}</td></tr>
        <tr><td class="k">Vervoerder <i>/ Transporteur</i></td><td>{!! implode('<br>', array_map('e', $lines('shipping_transporter'))) !!}<br>{{ $dossier->truck_plate }}</td></tr>
        <tr><td class="k">Bestemming (levering) <i>/ Destination (livraison)</i></td><td>{{ $client }}: <strong>{{ $dossier->cowCode() }}</strong><br>{!! implode('<br>', array_map('e', $to)) !!}</td></tr>
        <tr><td class="k">Goederen <i>/ Marchandises</i></td><td>{{ $goods('nl') }}<br><span class="muted">{{ $goods('fr') }}</span></td></tr>
        <tr><td class="k">Aantal colli <i>/ Nombre de colis</i></td><td>{{ $dossier->move_packages ?: '' }}</td></tr>
        <tr><td class="k">Gewicht <i>/ Poids</i></td><td>{{ $dossier->move_weight_kg !== null ? number_format((float) $dossier->move_weight_kg, 0, ',', '.') : '' }} Kg</td></tr>
        @if ($dossier->decommission_source)
            <tr><td class="k">Opmerking <i>/ Remarque</i></td><td>{{ __('battery_documents.move.decommissioned', ['from' => $dossier->sourceCowCode()], 'nl') }}<br><span class="muted">{{ __('battery_documents.move.decommissioned', ['from' => $dossier->sourceCowCode()], 'fr') }}</span></td></tr>
        @endif
    </table>

    <table class="w nobreak sign" style="margin-top: 10px;">
        <tr>
            <td style="width: 33%; padding-right: 6px;"><span class="label">Verzender / Expéditeur</span><div class="box">{{ $client }}: {{ $dossier->sourceCowCode() }}<br>{!! implode('<br>', array_map('e', $from)) !!}</div></td>
            <td style="width: 33%; padding-right: 6px;"><span class="label">Vervoerder / Transporteur</span><div class="box">{!! implode('<br>', array_map('e', $lines('shipping_transporter_signature'))) !!}</div></td>
            <td style="width: 34%;"><span class="label">Bestemming / Destination</span><div class="box">{{ $client }}: {{ $dossier->cowCode() }}<br>{!! implode('<br>', array_map('e', $to)) !!}</div></td>
        </tr>
    </table>
@endsection
