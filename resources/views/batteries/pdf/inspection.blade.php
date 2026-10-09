@extends('batteries.pdf.layout')

@section('title', 'Visueel keuringsverslag')

@php
    $project = $dossier->project;
    $site = $project->workSite;
    $header = \App\Support\BatteryTemplates::inspectionHeaderDataUri();
    $mark = fn (?string $answer, string $value) => $answer === $value ? '✔' : '';
    $date = fn ($date) => $date?->locale('nl')->translatedFormat('d F Y') ?? '';
@endphp

@section('styles')
    .check td { border: 1px solid #94a3b8; padding: 2px 5px; font-size: 7.8pt; }
    .check tr.section td { background: #e2e8f0; font-weight: bold; }
    .check td.yn { width: 9%; text-align: center; font-family: DejaVu Sans, sans-serif; }
@endsection

@section('content')
    @if ($header)
        <img src="{{ $header }}" style="width: 100%; margin-bottom: 6px;" alt="">
    @endif
    <h1>Visueel keuringsverslag</h1>
    <div><strong>Batterijen</strong> — Visueel keuringsverslag voor de installatie van batterijen</div>

    <table class="w grid" style="margin-top: 8px;">
        <tr><th style="width: 50%;">Administratieve inlichtingen <span class="muted">(door de aannemer te vervolledigen)</span></th><th>Technische inlichtingen <span class="muted">(door de aannemer te vervolledigen)</span></th></tr>
        <tr>
            <td>
                Adres: {{ trim(($site?->street ?? '').' '.($site?->house_number ?? '')) }}<br>
                {{ trim(($site?->postal_code ?? '').' '.($site?->city ?? '')) }}<br>
                Cow code: <strong>{{ $dossier->cowCode() }}</strong><br>
                ID Nrs : {{ $dossier->idNumbers() }}<br>
                Ref. {{ settings()->get('batteries.client_short') }} (PO nr): {{ $dossier->po_number }}<br>
                Ref. Leverancier : {{ $dossier->reference() }}<br>
                Besteldatum : {{ $date($dossier->order_date) }}<br>
                Leveringsnota : {{ $date($dossier->execution_date) }}
            </td>
            <td>
                Merk: {{ $dossier->brand }}<br>
                Type: {{ $dossier->batteryModel?->name }}<br>
                Capaciteit: {{ $dossier->new_capacity_ah }} Ah<br>
                Aantal batterijen: {{ $dossier->new_count }}<br>
                Gestel: {{ $dossier->mounting?->getLabel() }} ({{ $dossier->rack()?->code }})<br>
                Aantal elementen per batterij: {{ $dossier->cells_per_battery }}<br>
                Productiedatum batterij: {{ $dossier->production_date }}
            </td>
        </tr>
    </table>

    <table class="w check" style="margin-top: 8px;">
        @foreach (\App\Support\BatteryInspection::sections() as $section => $questions)
            <tr class="section"><td>{{ $section }}</td><td class="yn">Ja</td><td class="yn">Nee</td></tr>
            @foreach ($questions as $key => $question)
                <tr>
                    <td>{{ $question }}</td>
                    <td class="yn">{{ $mark($dossier->inspectionAnswer($key), 'yes') }}</td>
                    <td class="yn">{{ $mark($dossier->inspectionAnswer($key), 'no') }}</td>
                </tr>
            @endforeach
        @endforeach
    </table>

    <div class="nobreak">
        <h2>Opmerkingen en commentaar:</h2>
        <div class="box" style="height: auto; min-height: 50px; white-space: pre-line;">{{ $dossier->inspection_remarks ?: 'Geen' }}</div>

        <table class="w" style="margin-top: 10px;">
            <tr>
                <td style="width: 50%; padding-right: 10px;">Voor de aannemer,<br><span class="label">Datum:</span> {{ $dossier->execution_date?->format('d/m/Y') }}<br><span class="label">Naam + handtekening:</span><div class="box"></div></td>
                <td>Voor {{ settings()->get('batteries.client_short') }},<br><span class="label">Datum:</span><br><span class="label">Naam + handtekening:</span><div class="box"></div></td>
            </tr>
        </table>
    </div>
@endsection
