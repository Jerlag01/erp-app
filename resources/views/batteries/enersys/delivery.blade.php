@extends('batteries.pdf.layout')

@section('title', 'Leveringsnota')

{{-- The delivery note of an Enersys dossier, like the sheet "Leveringsnota" of "12V enersys". --}}
@php
    $site = $dossier->project?->workSite;
    $quantity = fn ($value) => (float) $value == 0.0 ? '' : rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    $date = fn ($date) => $date?->format('d/m/Y') ?? '';
@endphp

@section('content')
    <table class="w plain">
        <tr>
            <td style="width: 52%;"></td>
            <td class="label" style="width: 18%;">Leveringsadres:</td>
            <td>
                <strong>{{ $dossier->cowCode() }}</strong><br>
                {{ trim(($site?->street ?? '').', '.($site?->house_number ?? ''), ', ') }}<br>
                {{ trim(($site?->postal_code ?? '').' '.($site?->city ?? '')) }}
            </td>
        </tr>
    </table>

    <h1 style="margin-top: 14px;">Leveringsnota</h1>

    <table class="w grid" style="margin-top: 8px;">
        <tr>
            <td class="label" style="width: 16%;">Dossier N°</td><td class="bold">{{ $dossier->reference() }}</td>
            <td class="label" style="width: 12%;">Datum:</td><td>{{ $date($dossier->execution_date) }}</td>
            <td class="label" style="width: 14%;">Bestel Dat.</td><td>{{ $date($dossier->order_date) ?: 'NVT' }}</td>
        </tr>
        <tr>
            <td class="label">Klant</td><td>{{ settings()->get('batteries.client_name') }}</td>
            <td class="label">BTW</td><td>{{ settings()->get('batteries.client_vat') }}</td>
            <td class="label">Ref. klant</td><td class="mono">{{ $dossier->po_number }}</td>
        </tr>
    </table>

    <table class="w grid" style="margin-top: 12px;">
        <thead>
            <tr><th>Beschrijving van het artikel</th><th class="num" style="width: 14%;">Hoeveelheid</th><th style="width: 7%;"></th></tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="3">
                    Cow code: <strong>{{ $dossier->cowCode() }}</strong>@if ($dossier->new_room) &nbsp; lokaal: <strong>{{ $dossier->new_room }}</strong>@endif<br>
                    ID: {{ $dossier->idNumbers() }}
                </td>
            </tr>
            <tr><td><strong>{{ $dossier->deliveryLine() }}</strong>@if ($dossier->typeName())<br>{{ $dossier->brand }} {{ $dossier->typeName() }}@endif</td><td></td><td></td></tr>
            <tr><td>{{ $dossier->removalLine() }}</td><td class="num">{{ $dossier->old_count }}</td><td>st</td></tr>
            <tr><td>{{ $dossier->placementLine() }}</td><td class="num">{{ $dossier->new_count }}</td><td>st</td></tr>
            @if ($dossier->removal)
                <tr><td>{{ $dossier->destroyLine() }}</td><td></td><td></td></tr>
            @endif
            <tr><td>Verticaal transport: plaatsen</td><td class="num">{{ $quantity($dossier->vertical_install_m) }}</td><td>m</td></tr>
            <tr><td>Verticaal transport: wegnemen</td><td class="num">{{ $quantity($dossier->vertical_removal_m) }}</td><td>m</td></tr>
            <tr><td>Manuren: Aansluitingen soepele kabels</td><td class="num">{{ $quantity($dossier->man_hours) }}</td><td>uren</td></tr>
            <tr><td>Soepele kabel: PowerflexPlus 1x {{ $dossier->powerflex_section }} mm²</td><td class="num">{{ $quantity($dossier->powerflex_length_m) }}</td><td>m</td></tr>
            <tr><td>Kabelschoenen DIN {{ $dossier->lug_section }} mm²</td><td class="num">{{ $quantity($dossier->lug_count) }}</td><td>st</td></tr>
            <tr><td>Bestaande zekeringen &nbsp; {{ $dossier->fuse_amperage }} A &nbsp; Type {{ $dossier->fuse_type ?: 'gG' }}</td><td class="num">{{ $quantity($dossier->fuse_count) }}</td><td>st</td></tr>
            <tr><td>Zuurlekbakken</td><td class="num">{{ $quantity($dossier->leak_trays) }}</td><td>st</td></tr>
        </tbody>
    </table>

    <table class="w nobreak" style="margin-top: 16px;">
        <tr>
            <td style="width: 48%; padding-right: 10px;"><span class="label">Datum</span><div class="box">{{ $date($dossier->execution_date) }}</div></td>
            <td style="width: 52%;"><span class="label">Handtekening &amp; Naam</span><div class="box">@include('batteries.pdf.signature', ['document' => 'delivery'])</div></td>
        </tr>
    </table>
@endsection
