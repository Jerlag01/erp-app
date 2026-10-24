{{-- The battery room checklist (Excel "Leveringsnota 14EIN", sheet Checklist), landscape, no letterhead. --}}
@extends('batteries.pdf.layout')

@section('title', __('battery_rooms.doc.checklist_title'))

@php
    $picture = fn (string $path): string => 'data:image/png;base64,'.base64_encode((string) file_get_contents(resource_path($path)));
@endphp

@section('styles')
    @page { margin: 8mm 10mm; }
    body { font-size: 7.6pt; }
    .grid td, .grid th { padding: 2px 4px; }
    .grid td { vertical-align: middle; }
    .text { white-space: pre-line; }
    .ok { color: #15803d; font-weight: bold; }
    .nok { color: #b91c1c; font-weight: bold; }
@endsection

@section('content')
    <table class="w grid">
        <tr>
            <td class="bold" style="width: 14%;">{{ __('battery_rooms.doc.checklist_owner') }}</td>
            <td class="bold" style="width: 40%;">{{ __('battery_rooms.doc.checklist_title') }}</td>
            <td colspan="2" class="muted">{{ __('battery_rooms.doc.checklist_note') }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('battery_rooms.doc.checklist_cow') }}</td>
            <td class="bold">{{ $dossier->cowAndRoom() }}</td>
            <td class="label" style="width: 8%;">{{ __('battery_rooms.doc.checklist_date') }}</td>
            <td>{{ $dossier->checklist_date?->format('d/m/Y') }}</td>
        </tr>
    </table>

    <table class="w grid" style="margin-top: 8px;">
        <thead>
            <tr>
                <th style="width: 18%;">{{ __('battery_rooms.doc.subject') }}</th>
                <th style="width: 40%;">{{ __('battery_rooms.doc.description') }}</th>
                <th class="center" style="width: 8%;">{{ __('battery_rooms.doc.ok_nok') }}</th>
                <th>{{ __('battery_rooms.doc.action') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach (\App\Models\BatteryRoomDossier::CHECKLIST as $point => $pictogram)
                <tr class="nobreak">
                    <td class="bold">
                        {{ __("battery_rooms.checklist.{$point}.subject") }}
                        @if ($pictogram)
                            <br><img src="{{ $picture($pictogram) }}" style="height: 26px; margin-top: 2px;">
                        @endif
                    </td>
                    <td class="text">{{ __("battery_rooms.checklist.{$point}.description") }}</td>
                    <td class="center">
                        <span class="{{ $dossier->isOk($point) ? 'ok' : 'nok' }}">{{ $dossier->isOk($point) ? __('battery_rooms.doc.ok') : __('battery_rooms.doc.nok') }}</span>
                    </td>
                    <td class="text">{{ $dossier->action($point) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
