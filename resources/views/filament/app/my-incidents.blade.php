{{-- The employee's own earlier reports. Filament's precompiled CSS only ships its own classes, so styling is inline. --}}
@php
    $incidents = \App\Models\Incident::query()->where('user_id', auth()->id())->latest('reported_at')->limit(10)->get();
@endphp

@if ($incidents->isNotEmpty())
    <x-filament::section :heading="__('erp.incidents.my_reports')" data-my-incidents>
        <ul style="display: flex; flex-direction: column; gap: .5rem;">
            @foreach ($incidents as $incident)
                <li style="display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; padding: .5rem 0; border-bottom: 1px solid rgb(148 163 184 / .25);">
                    <span style="font-family: var(--erp-mono); font-size: .8125rem;">{{ $incident->reported_at->format('d/m/Y H:i') }}</span>
                    <x-filament::badge :color="$incident->type->getColor()">{{ $incident->type->getLabel() }}</x-filament::badge>
                    <span style="flex: 1; min-width: 10rem;">{{ $incident->placeName() ?? $incident->location_details ?? '—' }}</span>
                    <x-filament::badge :color="$incident->status->getColor()">{{ $incident->status->getLabel() }}</x-filament::badge>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
@endif
