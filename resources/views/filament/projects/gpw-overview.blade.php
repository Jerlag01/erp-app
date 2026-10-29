{{-- The GPW requests of one project at a glance: period, where each stands, and how it was asked. --}}
@php($requests = $getState() ?? [])
<table style="width: 100%; border-collapse: collapse; font-size: .875rem;" data-project-gpw>
    <thead>
        <tr style="text-align: left; opacity: .7;">
            <th style="padding: .25rem .5rem .25rem 0; font-weight: 500;">{{ __('erp.gpw.starts_on') }}</th>
            <th style="padding: .25rem .5rem; font-weight: 500;">{{ __('erp.gpw.ends_on') }}</th>
            <th style="padding: .25rem .5rem; font-weight: 500;">{{ __('erp.gpw.overview_status_label') }}</th>
            <th style="padding: .25rem .5rem; font-weight: 500;">{{ __('erp.gpw.source') }}</th>
            <th style="padding: .25rem 0 .25rem .5rem; font-weight: 500;">{{ __('erp.gpw.decided_by') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($requests as $gpw)
            <tr style="border-top: 1px solid rgb(148 163 184 / .25);">
                <td style="padding: .35rem .5rem .35rem 0; white-space: nowrap;">{{ $gpw->starts_on->format('d/m/Y') }}</td>
                <td style="padding: .35rem .5rem; white-space: nowrap;">{{ $gpw->ends_on->format('d/m/Y') }}</td>
                <td style="padding: .35rem .5rem;">
                    <x-filament::badge :color="$gpw->status->getColor()" :icon="$gpw->status->getIcon()">{{ $gpw->overviewStatus() }}</x-filament::badge>
                </td>
                <td style="padding: .35rem .5rem; opacity: .8;">{{ __('erp.gpw.sources.'.$gpw->source) }}</td>
                <td style="padding: .35rem 0 .35rem .5rem; opacity: .8;">{{ $gpw->decided_by_name ?? '—' }}@if ($gpw->decided_at) · {{ $gpw->decided_at->format('d/m/Y') }}@endif</td>
            </tr>
        @endforeach
    </tbody>
</table>
