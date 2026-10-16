@extends('batteries.pdf.layout')

@section('title', __('battery_documents.delivery.title'))

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
            <td class="label" style="width: 18%;">{{ __('battery_documents.common.delivery_address') }}</td>
            <td>
                <strong>{{ $dossier->cowCode() }}</strong> &nbsp; {{ settings()->get('batteries.client_short') }}<br>
                {{ trim(($site?->street ?? '').' '.($site?->house_number ?? '')) }}<br>
                {{ trim(($site?->postal_code ?? '').' '.($site?->city ?? '')) }}
            </td>
        </tr>
    </table>

    <h1 style="margin-top: 14px;">{{ __('battery_documents.delivery.title') }}</h1>

    <table class="w grid" style="margin-top: 8px;">
        <tr>
            <td class="label" style="width: 16%;">{{ __('battery_documents.common.dossier') }} N°</td><td class="bold">{{ $dossier->reference() }}</td>
            <td class="label" style="width: 12%;">{{ __('battery_documents.common.date') }}:</td><td>{{ $date($dossier->execution_date) }}</td>
            <td class="label" style="width: 14%;">{{ __('battery_documents.common.order_date') }}</td><td>{{ $date($dossier->order_date) }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('battery_documents.common.client') }}</td><td>{{ settings()->get('batteries.client_name') }}</td>
            <td class="label">{{ __('battery_documents.common.vat') }}</td><td>{{ settings()->get('batteries.client_vat') }}</td>
            <td class="label">{{ __('battery_documents.common.client_ref') }}</td><td class="mono">{{ $dossier->po_number }}</td>
        </tr>
    </table>

    <table class="w grid" style="margin-top: 12px;">
        <thead>
            <tr><th>{{ __('battery_documents.common.article') }}</th><th class="num" style="width: 14%;">{{ __('battery_documents.common.quantity') }}</th><th style="width: 7%;"></th></tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="3">
                    {{ __('battery_documents.common.cow_code') }}: <strong>{{ $dossier->cowCode() }}</strong> &nbsp; {{ __('battery_documents.common.room') }}: <strong>{{ $dossier->new_room }}</strong><br>
                    ID: {{ $dossier->idNumbers() }}
                </td>
            </tr>
            <tr><td><strong>{{ $dossier->deliveryLine() }}</strong><br>{{ $dossier->batteryDescription() }}</td><td class="num">{{ $dossier->new_count }}</td><td>{{ __('battery_documents.common.pcs') }}</td></tr>
            <tr><td>{{ $dossier->rackLine() }}<br>{{ __('battery_documents.delivery.rack_type', ['code' => $dossier->rack()?->code]) }}</td><td class="num">{{ $dossier->new_count }}</td><td>{{ __('battery_documents.common.pcs') }}</td></tr>
            <tr><td>{{ __('battery_documents.delivery.vertical_install') }}</td><td class="num">{{ $quantity($dossier->vertical_install_m) }}</td><td>m</td></tr>
            @if ($dossier->removal)
                <tr><td>{{ $dossier->destroyLine() }}</td><td class="num">{{ $dossier->old_count }}</td><td>{{ __('battery_documents.common.pcs') }}</td></tr>
                <tr><td>{{ __('battery_documents.delivery.vertical_removal') }} &nbsp; <span class="label">{{ __('battery_documents.delivery.room') }}</span> {{ $dossier->old_room }}</td><td class="num">{{ $quantity($dossier->vertical_removal_m) }}</td><td>m</td></tr>
            @endif
            <tr><td>{{ __('battery_documents.delivery.ppe') }}</td><td class="num">{{ $quantity($dossier->ppe_sets) }}</td><td>{{ __('battery_documents.common.pcs') }}</td></tr>
            <tr><td>{{ __('battery_documents.delivery.maintenance') }}</td><td class="num">{{ $quantity($dossier->maintenance_sets) }}</td><td>{{ __('battery_documents.common.pcs') }}</td></tr>
            <tr><td>{{ __('battery_documents.delivery.load_plates') }}</td><td class="num">{{ $quantity($dossier->load_plates) }}</td><td>{{ __('battery_documents.common.pcs') }}</td></tr>
            <tr><td>{{ __('battery_documents.delivery.man_hours') }}</td><td class="num">{{ $quantity($dossier->man_hours) }}</td><td>{{ __('battery_documents.common.hours') }}</td></tr>
            <tr><td>{{ __('battery_documents.delivery.flexible_cable', ['section' => $dossier->powerflex_section]) }}</td><td class="num">{{ $quantity($dossier->powerflex_length_m) }}</td><td>m</td></tr>
            <tr><td>{{ __('battery_documents.delivery.frotek', ['name' => $dossier->cable?->name]) }}</td><td class="num">{{ $quantity($dossier->frotek_count) }}</td><td>{{ __('battery_documents.common.pcs') }}</td></tr>
            <tr><td>{{ __('battery_documents.delivery.lugs', ['section' => $dossier->lug_section]) }}</td><td class="num">{{ $quantity($dossier->lug_count) }}</td><td>{{ __('battery_documents.common.pcs') }}</td></tr>
            <tr><td>{{ __('battery_documents.delivery.fuses', ['amperage' => $dossier->fuse_amperage, 'type' => $dossier->fuse_type ?: 'gG']) }}</td><td class="num">{{ $quantity($dossier->fuse_count) }}</td><td>{{ __('battery_documents.common.pcs') }}</td></tr>
            <tr><td>{{ __('battery_documents.delivery.tray', ['type' => $dossier->tray_type]) }}</td><td class="num">{{ $quantity($dossier->tray_length_m) }}</td><td>m</td></tr>
            <tr><td>{{ __('battery_documents.delivery.pictos') }}</td><td class="num">{{ $quantity($dossier->picto_plates) }}</td><td>{{ __('battery_documents.common.pcs') }}</td></tr>
            <tr><td>{{ __('battery_documents.delivery.doc_holder') }}</td><td class="num">{{ $quantity($dossier->doc_holders) }}</td><td>{{ __('battery_documents.common.pcs') }}</td></tr>
        </tbody>
    </table>

    <table class="w nobreak" style="margin-top: 16px;">
        <tr>
            <td style="width: 48%; padding-right: 10px;"><span class="label">{{ __('battery_documents.common.date') }}</span><div class="box">{{ $date($dossier->execution_date) }}</div></td>
            <td style="width: 52%;"><span class="label">{{ __('battery_documents.common.signature') }}</span><div class="box">@include('batteries.pdf.signature', ['document' => 'delivery'])</div></td>
        </tr>
    </table>
@endsection
