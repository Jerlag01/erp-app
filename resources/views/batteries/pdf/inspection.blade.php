@extends('batteries.pdf.layout')

@section('title', __('battery_documents.inspection.title'))

@php
    $project = $dossier->project;
    $site = $project->workSite;
    $header = \App\Support\BatteryTemplates::inspectionHeaderDataUri();
    $mark = fn (?string $answer, string $value) => $answer === $value ? '✔' : '';
    $date = fn ($date) => $date?->locale(app()->getLocale())->translatedFormat('d F Y') ?? '';
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
    <h1>{{ __('battery_documents.inspection.title') }}</h1>
    <div><strong>{{ __('battery_documents.inspection.batteries') }}</strong> — {{ __('battery_documents.inspection.subtitle') }}</div>

    <table class="w grid" style="margin-top: 8px;">
        <tr><th style="width: 50%;">{{ __('battery_documents.inspection.administrative') }} <span class="muted">{{ __('battery_documents.inspection.to_complete') }}</span></th><th>{{ __('battery_documents.inspection.technical') }} <span class="muted">{{ __('battery_documents.inspection.to_complete') }}</span></th></tr>
        <tr>
            <td>
                {{ __('battery_documents.inspection.address') }} {{ trim(($site?->street ?? '').' '.($site?->house_number ?? '')) }}<br>
                {{ trim(($site?->postal_code ?? '').' '.($site?->city ?? '')) }}<br>
                {{ __('battery_documents.common.cow_code') }}: <strong>{{ $dossier->cowCode() }}</strong><br>
                {{ __('battery_documents.inspection.id_numbers') }} {{ $dossier->idNumbers() }}<br>
                {{ __('battery_documents.inspection.client_po', ['client' => settings()->get('batteries.client_short')]) }} {{ $dossier->po_number }}<br>
                {{ __('battery_documents.inspection.supplier_ref') }} {{ $dossier->reference() }}<br>
                {{ __('battery_documents.inspection.order_date') }} {{ $date($dossier->order_date) }}<br>
                {{ __('battery_documents.inspection.delivery_note') }} {{ $date($dossier->execution_date) }}
            </td>
            <td>
                {{ __('battery_documents.inspection.brand') }} {{ $dossier->brand }}<br>
                {{ __('battery_documents.inspection.type') }} {{ $dossier->typeName() }}<br>
                {{ __('battery_documents.inspection.capacity') }} {{ $dossier->new_capacity_label ?: $dossier->new_capacity_ah }} Ah<br>
                {{ __('battery_documents.inspection.battery_count') }} {{ $dossier->new_count }}<br>
                {{ __('battery_documents.inspection.rack') }} {{ $dossier->mounting?->getLabel() }}@if ($dossier->rack()?->code) ({{ $dossier->rack()->code }})@endif<br>
                {{ __('battery_documents.inspection.elements') }} {{ $dossier->cells_per_battery }}<br>
                {{ __('battery_documents.inspection.production_date') }} {{ $dossier->production_date }}
            </td>
        </tr>
    </table>

    <table class="w check" style="margin-top: 8px;">
        @foreach ($dossier->inspectionSections() as $section => $questions)
            <tr class="section"><td>{{ $section }}</td><td class="yn">{{ __('battery_documents.inspection.yes') }}</td><td class="yn">{{ __('battery_documents.inspection.no') }}</td></tr>
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
        <h2>{{ __('battery_documents.inspection.remarks') }}</h2>
        <div class="box" style="height: auto; min-height: 50px; white-space: pre-line;">{{ $dossier->inspection_remarks ?: __('battery_documents.inspection.none') }}</div>

        <table class="w" style="margin-top: 10px;">
            <tr>
                <td style="width: 50%; padding-right: 10px;">{{ __('battery_documents.inspection.for_contractor') }}<br><span class="label">{{ __('battery_documents.common.date') }}:</span> {{ $dossier->execution_date?->format('d/m/Y') }}<br><span class="label">{{ __('battery_documents.inspection.name_signature') }}</span><div class="box">@include('batteries.pdf.signature', ['document' => 'inspection'])</div></td>
                <td>{{ __('battery_documents.inspection.for_client', ['client' => settings()->get('batteries.client_short')]) }}<br><span class="label">{{ __('battery_documents.common.date') }}:</span><br><span class="label">{{ __('battery_documents.inspection.name_signature') }}</span><div class="box"></div></td>
            </tr>
        </table>
    </div>
@endsection
