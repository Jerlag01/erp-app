{{-- The overview of a project, as the copy on the NAS ({category}/{reference}/): its details, people, GPW, deliveries and the list of its files. --}}
@extends('nas.pdf-layout')

@php
    $p = $project;
    $date = fn ($value) => $value?->format('d/m/Y');
    $planned = $p->planningItems()->selectRaw('min(date) as first_day, max(date) as last_day')->first();
    $files = $p->files->sortBy(['section', 'name'])->groupBy('section');
@endphp

@section('content')
    <h1>{{ $p->reference }}</h1>
    <div class="muted">{{ $p->category?->label() }} · {{ $p->status?->getLabel() }}</div>

    <h2>{{ __('erp.client_portal.project') }}</h2>
    <table>
        @foreach (array_filter([
            __('erp.projects.reference') => $p->reference,
            __('erp.projects.short_description') => $p->short_description,
            __('erp.fields.status') => $p->status?->getLabel(),
            __('erp.client_portal.cow_code') => $p->workSite?->cow_code ?? $p->cow_code,
            __('erp.client_portal.address') => $p->addressLine() ?? $p->workSite?->addressLine(),
            __('erp.client_portal.description') => $p->subtitle() !== $p->short_description ? $p->subtitle() : null,
            __('erp.client_portal.po_numbers') => $p->poNumbers->pluck('number')->implode(', '),
            __('erp.client_portal.project_leaders') => $p->leaders->map(fn ($leader) => trim($leader->name.' '.($leader->email ? '('.$leader->email.')' : '')))->implode(', '),
            __('erp.resources.team.plural') => $p->teams->pluck('name')->implode(', '),
            __('erp.client_portal.planned_execution') => $planned?->first_day ? \Illuminate\Support\Carbon::parse($planned->first_day)->format('d/m/Y').' – '.\Illuminate\Support\Carbon::parse($planned->last_day)->format('d/m/Y') : null,
            __('erp.client_portal.created') => $date($p->created_at),
            __('erp.fields.notes') => $p->notes,
        ], fn ($value) => filled($value)) as $label => $value)
            <tr><td class="label">{{ $label }}</td><td style="white-space: pre-line;">{{ $value }}</td></tr>
        @endforeach
    </table>

    @if ($p->gpwRequests->isNotEmpty())
        <h2>{{ __('erp.gpw.short') }}</h2>
        <table>
            <tr><th>{{ __('erp.gpw.period') }}</th><th>{{ __('erp.fields.status') }}</th><th>{{ __('erp.gpw.sent_to') }}</th><th>{{ __('erp.gpw.decision') }}</th></tr>
            @foreach ($p->gpwRequests as $gpw)
                <tr><td>{{ $gpw->period() }}</td><td>{{ $gpw->status->getLabel() }}</td><td>{{ $gpw->contact_name }}</td><td>{{ trim(($gpw->decided_at?->format('d/m/Y') ?? '').' '.$gpw->decided_by_name) ?: '—' }}</td></tr>
            @endforeach
        </table>
    @endif

    @if ($p->batteryDeliveries->isNotEmpty())
        <h2>{{ __('erp.client_portal.battery_deliveries') }}</h2>
        <table>
            <tr><th>{{ __('erp.client_portal.order_confirmation') }}</th><th>{{ __('erp.client_portal.pieces') }}</th><th>{{ __('erp.client_portal.planned_delivery') }}</th><th>{{ __('erp.client_portal.delivered') }}</th></tr>
            @foreach ($p->batteryDeliveries as $delivery)
                <tr><td>{{ $delivery->order_confirmation }}</td><td>{{ $delivery->pieces }}</td><td>{{ $date($delivery->planned_delivery_date) }}</td><td>{{ $delivery->delivered ? $date($delivery->delivered_at) : '—' }}</td></tr>
            @endforeach
        </table>
    @endif

    @if ($p->asbuilt)
        <h2>As-built</h2>
        <table>
            <tr><td class="label">{{ __('erp.fields.status') }}</td><td>{{ $p->asbuilt->status->getLabel() }}</td></tr>
            <tr><td class="label">{{ __('erp.asbuilt.btv_requested_on') }}</td><td>{{ $date($p->asbuilt->btv_requested_on) ?? '—' }}</td></tr>
            <tr><td class="label">{{ __('erp.asbuilt.btv_date') }}</td><td>{{ $date($p->asbuilt->btv_date) ?? '—' }}</td></tr>
        </table>
    @endif

    @if ($files->isNotEmpty())
        <h2>{{ __('erp.client_portal.files') }}</h2>
        <table>
            @foreach ($files as $section => $sectionFiles)
                <tr><th colspan="3">{{ \App\Enums\ProjectFileSection::tryFrom($section)?->getLabel() ?? $section }}</th></tr>
                @foreach ($sectionFiles as $file)
                    <tr><td>{{ $file->name }}</td><td>v{{ $file->version }}</td><td>{{ $date($file->latestVersion?->created_at) }}</td></tr>
                @endforeach
            @endforeach
        </table>
    @endif
@endsection
