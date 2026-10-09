@extends('batteries.pdf.layout', ['landscape' => true])

@section('title', 'Eendraadschema')

@php
    $rows = $dossier->batteries->map(fn ($battery) => $battery->only(['position', 'id_number', 'cable_type', 'cable_length_m', 'cables_positive', 'cables_negative', 'cable_section', 'fuse_amperage', 'fuse_type']))->all();
    $svg = \App\Support\BatterySingleLine::svg($rows);
@endphp

@section('content')
    <table class="w plain">
        <tr>
            <td><h1>Eendraadschema</h1></td>
            <td class="num">Dossier <strong>{{ $dossier->reference() }}</strong> · Cow code <strong>{{ $dossier->cowCode() }}</strong> · lokaal {{ $dossier->new_room }}<br>{{ $dossier->batteryModel?->name }} · {{ $dossier->new_count }} × {{ $dossier->cells_per_battery }} cellen</td>
        </tr>
    </table>

    <div class="center" style="margin: 6px 0 10px;">
        <img src="{{ \App\Support\BatterySingleLine::dataUri($svg) }}" style="max-width: 100%; max-height: 105mm;" alt="">
    </div>

    <table class="w grid">
        <thead>
            <tr>
                <th></th>
                @foreach ($dossier->batteries as $battery)<th class="center">BATT {{ $battery->position }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            <tr><td class="label">Type kabel</td>@foreach ($dossier->batteries as $battery)<td class="center">{{ $battery->cable_type }}</td>@endforeach</tr>
            <tr><td class="label">Lengte (m)</td>@foreach ($dossier->batteries as $battery)<td class="center">{{ $battery->cable_length_m !== null ? rtrim(rtrim(number_format((float) $battery->cable_length_m, 2, ',', ''), '0'), ',') : '' }}</td>@endforeach</tr>
            <tr><td class="label">Aantal per pool (+ / −)</td>@foreach ($dossier->batteries as $battery)<td class="center">{{ $battery->cables_positive }} / {{ $battery->cables_negative }}</td>@endforeach</tr>
            <tr><td class="label">Sectie (mm²)</td>@foreach ($dossier->batteries as $battery)<td class="center">{{ $battery->cable_section }}</td>@endforeach</tr>
            <tr><td class="label">Zekeringswaarde (A)</td>@foreach ($dossier->batteries as $battery)<td class="center">{{ $battery->fuse_amperage }}</td>@endforeach</tr>
            <tr><td class="label">Zekeringstype</td>@foreach ($dossier->batteries as $battery)<td class="center">{{ $battery->fuse_type }}</td>@endforeach</tr>
        </tbody>
    </table>
@endsection
