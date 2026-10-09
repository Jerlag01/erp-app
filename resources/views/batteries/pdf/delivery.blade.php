@extends('batteries.pdf.layout')

@section('title', 'Leveringsnota')

@php
    $project = $dossier->project;
    $site = $project->workSite;
    $quantity = fn ($value) => (float) $value == 0.0 ? '' : rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    $date = fn ($date) => $date?->format('d/m/Y') ?? '';
@endphp

@section('content')
    <table class="w plain">
        <tr>
            <td style="width: 52%;"></td>
            <td class="label" style="width: 18%;">Leveringsadres:</td>
            <td>
                <strong>{{ $dossier->cowCode() }}</strong> &nbsp; {{ settings()->get('batteries.client_short') }}<br>
                {{ trim(($site?->street ?? '').' '.($site?->house_number ?? '')) }}<br>
                {{ trim(($site?->postal_code ?? '').' '.($site?->city ?? '')) }}
            </td>
        </tr>
    </table>

    <h1 style="margin-top: 14px;">Leveringsnota</h1>

    <table class="w grid" style="margin-top: 8px;">
        <tr>
            <td class="label" style="width: 16%;">Dossier N°</td><td class="bold">{{ $dossier->reference() }}</td>
            <td class="label" style="width: 12%;">Datum:</td><td>{{ $date($dossier->execution_date) }}</td>
            <td class="label" style="width: 14%;">Bestel Dat.</td><td>{{ $date($dossier->order_date) }}</td>
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
                    Cow code: <strong>{{ $dossier->cowCode() }}</strong> &nbsp; lokaal: <strong>{{ $dossier->new_room }}</strong><br>
                    ID: {{ $dossier->idNumbers() }}
                </td>
            </tr>
            <tr><td><strong>{{ $dossier->deliveryLine() }}</strong><br>{{ $dossier->batteryDescription() }}</td><td class="num">{{ $dossier->new_count }}</td><td>st</td></tr>
            <tr><td>{{ $dossier->rackLine() }}<br>Stelling type: {{ $dossier->rack()?->code }}</td><td class="num">{{ $dossier->new_count }}</td><td>st</td></tr>
            <tr><td>Verticaal transport: montage</td><td class="num">{{ $quantity($dossier->vertical_install_m) }}</td><td>m</td></tr>
            @if ($dossier->removal)
                <tr><td>{{ $dossier->destroyLine() }}</td><td class="num">{{ $dossier->old_count }}</td><td>st</td></tr>
                <tr><td>Verticaal transport: demontage &nbsp; <span class="label">Lokaal:</span> {{ $dossier->old_room }}</td><td class="num">{{ $quantity($dossier->vertical_removal_m) }}</td><td>m</td></tr>
            @endif
            <tr><td>Set persoonlijke beschermingsmiddelen</td><td class="num">{{ $quantity($dossier->ppe_sets) }}</td><td>st</td></tr>
            <tr><td>Set onderhoudsmateriaal</td><td class="num">{{ $quantity($dossier->maintenance_sets) }}</td><td>st</td></tr>
            <tr><td>Plaatjes voor belastings herverdeling</td><td class="num">{{ $quantity($dossier->load_plates) }}</td><td>st</td></tr>
            <tr><td>Manuren: Aansluiting kabels</td><td class="num">{{ $quantity($dossier->man_hours) }}</td><td>uren</td></tr>
            <tr><td>Soepele kabel: PowerflexPlus 1x {{ $dossier->powerflex_section }} mm²</td><td class="num">{{ $quantity($dossier->powerflex_length_m) }}</td><td>m</td></tr>
            <tr><td>Kabel: Frötek (BAE) {{ $dossier->cable?->name }}</td><td class="num">{{ $quantity($dossier->frotek_count) }}</td><td>st</td></tr>
            <tr><td>Kabelschoenen DIN {{ $dossier->lug_section }} mm²</td><td class="num">{{ $quantity($dossier->lug_count) }}</td><td>st</td></tr>
            <tr><td>Bestaande zekeringen &nbsp; {{ $dossier->fuse_amperage }} A &nbsp; Type {{ $dossier->fuse_type ?: 'gG' }}</td><td class="num">{{ $quantity($dossier->fuse_count) }}</td><td>st</td></tr>
            <tr><td>Verdeelkanaal &nbsp; Type {{ $dossier->tray_type }}</td><td class="num">{{ $quantity($dossier->tray_length_m) }}</td><td>m</td></tr>
            <tr><td>Plaatje pictogrammen</td><td class="num">{{ $quantity($dossier->picto_plates) }}</td><td>st</td></tr>
            <tr><td>Documenthouder</td><td class="num">{{ $quantity($dossier->doc_holders) }}</td><td>st</td></tr>
        </tbody>
    </table>

    <table class="w nobreak" style="margin-top: 16px;">
        <tr>
            <td style="width: 48%; padding-right: 10px;"><span class="label">Datum</span><div class="box">{{ $date($dossier->execution_date) }}</div></td>
            <td style="width: 52%;"><span class="label">Handtekening &amp; Naam</span><div class="box"></div></td>
        </tr>
    </table>
@endsection
