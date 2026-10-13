@extends('batteries.pdf.layout')

@section('title', 'Verzendnota')

@php
    $s = fn (string $key) => settings()->get('batteries.'.$key);
    $lines = fn (string $key) => \App\Support\BatteryTemplates::lines($s($key));
    $registrations = array_map(fn ($line) => array_map('trim', explode('|', $line)), $lines('shipping_registrations'));
    $client = $s('client_short');
@endphp

{{-- Everything on one page (between the letterhead's logo and footer band): compact type and spacing. --}}
@section('styles')
    body { font-size: 7.4pt; line-height: 1.15; }
    .ship td { padding: 1.5px 4px; vertical-align: top; border-bottom: 1px solid #e2e8f0; }
    .ship td.k { width: 34%; color: #334155; }
    .ship td.k i { color: #64748b; font-style: normal; font-size: 6.8pt; }
    .sign .box { height: 52px; padding: 3px 5px; }
@endsection

@section('content')
    <div style="text-align: center;">
        <h1 style="margin-bottom: 0; font-size: 14pt;">Verzendnota <span class="muted" style="font-size: 10pt; font-weight: normal;">/ Note d'envoi</span></h1>
    </div>

    <table class="w ship" style="margin-top: 4px;">
        <tr><td class="k">Ref PI</td><td class="bold">{{ $dossier->reference() }}</td></tr>
        <tr><td class="k">Ref klant <i>/ client</i></td><td>{{ $client }} PO N°: {{ $dossier->po_number }}</td></tr>
        <tr><td class="k">Datum ophaling <i>/ Prise en charge le</i></td><td>{{ $dossier->execution_date?->format('d/m/Y') }}</td></tr>
        <tr><td class="k">Overbrenger <i>/ Collecteur</i></td><td>{{ $s('shipping_carrier') }}<br>{{ $s('shipping_collector') }}</td></tr>
        <tr><td class="k">Producent <i>/ Producteur</i><br>Verzender <i>/ Expéditeur</i></td><td>{{ $client }}: {{ $dossier->cowCode() }}<br>{!! implode('<br>', array_map('e', $dossier->senderAddressLines())) !!}</td></tr>
        <tr><td class="k">Vervoerder <i>/ Transporteur</i></td><td>{!! implode('<br>', array_map('e', $lines('shipping_transporter'))) !!}</td></tr>
        <tr>
            <td class="k">Registratienr <i>/ Nr Enregistrement</i></td>
            <td>
                <table class="w">
                    @foreach ($registrations as $registration)
                        <tr><td style="border: 0; padding: 0 6px 0 0; width: 18%;">{{ $registration[0] ?? '' }}</td><td style="border: 0; padding: 0 6px 0 0;" class="mono">{{ $registration[1] ?? '' }}</td><td style="border: 0; padding: 0;">{{ $registration[2] ?? '' }}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
        <tr><td class="k">Bestemming <i>/ Destination</i></td><td>{!! implode('<br>', array_map('e', $lines('shipping_destination'))) !!}</td></tr>
        <tr><td class="k">Registratienummer <i>/ Nr Enregistrement</i></td><td>{{ $s('shipping_destination_registration') }}</td></tr>
        <tr><td class="k">Aantal Accubakken <i>/ nombre des bacs</i></td><td>{{ $dossier->acid_boxes ?: '' }}</td></tr>
        <tr><td class="k">Aantal lege accubakken terug meegenomen na aflevering <i>(info voor PI)</i></td><td>{{ $dossier->empty_boxes_returned ?: '' }}</td></tr>
        <tr><td class="k">Omschrijving <i>/ Description</i></td><td>{{ $s('shipping_description') }}</td></tr>
        <tr><td class="k">Fysische toestand <i>/ Etat physique</i></td><td>{{ $s('shipping_physical_state') }}</td></tr>
        <tr><td class="k">Afvalstof bevat <i>/ Déchet contenant</i></td><td>{{ $dossier->waste_type }}</td></tr>
        <tr><td class="k">Verpakking <i>/ Conditionnement</i></td><td>{{ $s('shipping_packaging') }} &nbsp; Aantal: <strong>{{ $dossier->packages ?: '' }}</strong><br><span style="font-size: 6.8pt;">{{ $s('shipping_adr') }}</span></td></tr>
        <tr><td class="k">Gewicht <i>/ Poids</i></td><td>{{ $dossier->weight_kg !== null ? number_format((float) $dossier->weight_kg, 0, ',', '.') : '' }} Kg</td></tr>
        <tr><td class="k">Eural code</td><td>{{ $s('shipping_eural') }}</td></tr>
        <tr><td class="k">R&amp;D Code</td><td>{{ $s('shipping_rd_code') }}</td></tr>
    </table>

    <table class="w nobreak sign" style="margin-top: 6px;">
        <tr>
            <td style="width: 33%; padding-right: 6px;"><span class="label">Verzender / Expéditeur</span><div class="box">{{ $client }}: {{ $dossier->cowCode() }}<br>{!! implode('<br>', array_map('e', $dossier->senderAddressLines())) !!}</div></td>
            <td style="width: 33%; padding-right: 6px;"><span class="label">Vervoerder / Transporteur</span><div class="box">{!! implode('<br>', array_map('e', $lines('shipping_transporter_signature'))) !!}</div></td>
            <td style="width: 34%;"><span class="label">Bestemming / Destination</span><div class="box">{!! implode('<br>', array_map('e', $lines('shipping_destination'))) !!}</div></td>
        </tr>
    </table>
@endsection
