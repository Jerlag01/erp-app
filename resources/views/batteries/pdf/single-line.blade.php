@extends('batteries.pdf.layout', ['landscape' => true])

@section('title', __('battery_documents.single_line.title'))

@php
    $rows = $dossier->batteries->map(fn ($battery) => $battery->only(['position', 'id_number', 'cable_type', 'cable_length_m', 'cables_positive', 'cables_negative', 'cable_section', 'fuse_amperage', 'fuse_type']))->all();
    $svg = \App\Support\BatterySingleLine::svg($rows);
@endphp

@section('content')
    <table class="w plain">
        <tr>
            <td><h1>{{ __('battery_documents.single_line.title') }}</h1></td>
            <td class="num">{{ __('battery_documents.common.dossier') }} <strong>{{ $dossier->reference() }}</strong> · {{ __('battery_documents.common.cow_code') }} <strong>{{ $dossier->cowCode() }}</strong> · {{ __('battery_documents.common.room') }} {{ $dossier->new_room }}<br>{{ $dossier->batteryModel?->name }} · {{ $dossier->new_count }} × {{ $dossier->cells_per_battery }} {{ __('battery_documents.single_line.cells') }}</td>
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
            <tr><td class="label">{{ __('battery_documents.single_line.cable_type') }}</td>@foreach ($dossier->batteries as $battery)<td class="center">{{ $battery->cable_type }}</td>@endforeach</tr>
            <tr><td class="label">{{ __('battery_documents.single_line.length') }}</td>@foreach ($dossier->batteries as $battery)<td class="center">{{ $battery->cable_length_m !== null ? rtrim(rtrim(number_format((float) $battery->cable_length_m, 2, ',', ''), '0'), ',') : '' }}</td>@endforeach</tr>
            <tr><td class="label">{{ __('battery_documents.single_line.per_pole') }}</td>@foreach ($dossier->batteries as $battery)<td class="center">{{ $battery->cables_positive }} / {{ $battery->cables_negative }}</td>@endforeach</tr>
            <tr><td class="label">{{ __('battery_documents.single_line.section') }}</td>@foreach ($dossier->batteries as $battery)<td class="center">{{ $battery->cable_section }}</td>@endforeach</tr>
            <tr><td class="label">{{ __('battery_documents.single_line.fuse_value') }}</td>@foreach ($dossier->batteries as $battery)<td class="center">{{ $battery->fuse_amperage }}</td>@endforeach</tr>
            <tr><td class="label">{{ __('battery_documents.single_line.fuse_type') }}</td>@foreach ($dossier->batteries as $battery)<td class="center">{{ $battery->fuse_type }}</td>@endforeach</tr>
        </tbody>
    </table>
@endsection
