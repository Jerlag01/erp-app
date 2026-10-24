{{-- The delivery note of a battery room (Excel "Leveringsnota 14EIN"), portrait on the letterhead. --}}
@extends('batteries.pdf.layout')

@section('title', __('battery_rooms.doc.note_title'))

@php
    $site = $dossier->project?->workSite;
    $date = $dossier->note_date?->format('d/m/Y') ?? '';
@endphp

@section('styles')
    .section td { background: #f1f5f9; font-weight: bold; }
    .indent { padding-left: 16px !important; }
    .text { white-space: pre-line; }
@endsection

@section('content')
    <table class="w plain">
        <tr>
            <td style="width: 52%;"></td>
            <td class="label" style="width: 20%;">{{ __('battery_rooms.doc.delivery_address') }}</td>
            <td>
                {{ settings()->get('batteries.client_short') }} &nbsp; <strong>{{ $dossier->cowCode() }}</strong><br>
                {{ trim(($site?->street ?? '').' '.($site?->house_number ?? '')) }}<br>
                {{ trim(($site?->postal_code ?? '').' '.($site?->city ?? '')) }}
            </td>
        </tr>
    </table>

    <h1 style="margin-top: 10px;">{{ __('battery_rooms.doc.note_title') }}</h1>

    <table class="w grid" style="margin-top: 6px;">
        <tr>
            <td class="label" style="width: 15%;">{{ __('battery_rooms.doc.dossier') }}</td><td class="bold">{{ $dossier->dossierNumber() }}</td>
            <td class="label" style="width: 10%;">{{ __('battery_rooms.doc.date') }}</td><td>{{ $date }}</td>
            <td class="label" style="width: 13%;">{{ __('battery_rooms.doc.cow_code') }}</td><td class="bold">{{ $dossier->cowCode() }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('battery_rooms.doc.client') }}</td><td>{{ settings()->get('batteries.client_name') }}</td>
            <td class="label">{{ __('battery_rooms.doc.vat') }}</td><td>{{ settings()->get('batteries.client_vat') }}</td>
            <td class="label">{{ __('battery_rooms.doc.client_reference') }}</td><td class="mono">{{ $dossier->clientReference() ?? __('battery_rooms.doc.pending') }}</td>
        </tr>
    </table>

    <table class="w grid" style="margin-top: 10px;">
        <thead>
            <tr><th>{{ __('battery_rooms.doc.article') }}</th><th class="num" style="width: 12%;">{{ __('battery_rooms.doc.quantity') }}</th><th style="width: 6%;"></th></tr>
        </thead>
        <tbody>
            @foreach (\App\Models\BatteryRoomDossier::SECTIONS as $section => $lines)
                <tr class="section nobreak">
                    <td colspan="3">
                        {{ __("battery_rooms.sections.{$section}.title") }}
                        @if ($text = \App\Models\BatteryRoomDossier::sectionText($section))
                            <br><span style="font-weight: normal;">{{ $text }}</span>
                        @endif
                    </td>
                </tr>
                @foreach ($lines as $line => $unit)
                    <tr>
                        <td class="indent">{{ __("battery_rooms.sections.{$section}.lines.{$line}") }}</td>
                        <td class="num">{{ $dossier->formattedQuantity($section, $line) }}</td>
                        <td>{{ __("battery_rooms.units.{$unit}") }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <table class="w nobreak" style="margin-top: 10px;">
        <tr><td class="label">{{ __('battery_rooms.doc.remarks') }}</td></tr>
        <tr><td><div class="box text" style="height: auto; min-height: 40px;">{{ $dossier->remarks }}</div></td></tr>
    </table>

    <table class="w nobreak" style="margin-top: 12px;">
        <tr>
            <td style="width: 40%; padding-right: 10px;"><span class="label">{{ __('battery_rooms.doc.date_signature') }}</span><div class="box" style="height: 40px;">{{ $date }}</div></td>
            <td style="width: 60%;"><span class="label">{{ __('battery_rooms.doc.signature') }}</span><div class="box" style="height: 40px;">{{ $dossier->signed_by }}</div></td>
        </tr>
    </table>
@endsection
