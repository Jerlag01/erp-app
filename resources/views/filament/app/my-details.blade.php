{{-- Filament's precompiled CSS only ships its own classes, so layout here uses inline styles. --}}
@php
    $fieldLabel = fn (string $field): string => \App\Filament\Admin\Resources\EmployeeChangeRequests\EmployeeChangeRequestResource::fieldLabel($field);
    $display = fn (string $field, mixed $value): string => \App\Filament\Admin\Resources\EmployeeChangeRequests\EmployeeChangeRequestResource::displayValue($field, $value);
@endphp
<x-filament-panels::page>
    <style>
        .erp-md-muted { font-size: .8125rem; opacity: .75; }
        .erp-md-list { display: flex; flex-direction: column; gap: .5rem; }
        .erp-md-row { display: flex; justify-content: space-between; gap: 1rem; align-items: center; flex-wrap: wrap; border: 1px solid rgb(148 163 184 / .35); border-radius: .5rem; padding: .5rem .75rem; }
        .dark .erp-md-row { border-color: rgb(255 255 255 / .1); }
        .erp-md-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .erp-md-table td, .erp-md-table th { padding: .35rem .5rem; text-align: left; border-bottom: 1px solid rgb(148 163 184 / .25); vertical-align: top; }
    </style>

    @if ($pending)
        <x-filament::section icon="heroicon-o-clock" icon-color="warning" :heading="__('erp.my_details.pending_heading')" :description="__('erp.my_details.pending_help', ['date' => $pending->updated_at->format('d/m/Y H:i')])">
            <table class="erp-md-table">
                <thead><tr><th>{{ __('erp.my_details.field') }}</th><th>{{ __('erp.my_details.current') }}</th><th>{{ __('erp.my_details.new') }}</th></tr></thead>
                <tbody>
                    @foreach ($pending->new_values as $field => $value)
                        <tr><td>{{ $fieldLabel($field) }}</td><td class="erp-md-muted">{{ $display($field, $pending->old_values[$field] ?? null) }}</td><td><strong>{{ $display($field, $value) }}</strong></td></tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top: .75rem;">{{ $this->withdrawAction }}</div>
        </x-filament::section>
    @endif

    <form wire:submit="submit" style="display: flex; flex-direction: column; gap: 1rem;">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">{{ __('erp.my_details.submit') }}</x-filament::button>
            <p class="erp-md-muted" style="margin-top: .5rem;">{{ __('erp.my_details.submit_help') }}</p>
        </div>
    </form>

    <x-filament::section icon="heroicon-o-academic-cap" :heading="__('erp.employees.certificates')">
        <x-slot name="afterHeader">{{ $this->addCertificateAction }}</x-slot>

        @if ($certificates->isEmpty())
            <p class="erp-md-muted">{{ __('erp.employees.no_certificates') }}</p>
        @else
            <div class="erp-md-list">
                @foreach ($certificates as $certificate)
                    <div class="erp-md-row">
                        <strong>{{ $certificate->label() }}</strong>
                        <span class="erp-md-muted">
                            @if ($certificate->obtained_on){{ __('erp.employees.obtained_on') }}: {{ $certificate->obtained_on->format('d/m/Y') }}@endif
                            @if ($certificate->expires_on) · {{ __('erp.employees.expires_on') }}: {{ $certificate->expires_on->format('d/m/Y') }}@endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($requests->isNotEmpty())
            <h3 style="font-weight: 600; margin: 1rem 0 .5rem;">{{ __('erp.my_details.certificate_requests') }}</h3>
            <div class="erp-md-list">
                @foreach ($requests as $request)
                    <div class="erp-md-row">
                        <span><strong>{{ $request->label() }}</strong> <span class="erp-md-muted">· {{ $request->created_at->format('d/m/Y') }}</span></span>
                        <span style="display: flex; gap: .5rem; align-items: center;">
                            <x-filament::badge :color="$request->status->getColor()">{{ $request->status->getLabel() }}</x-filament::badge>
                            @if ($request->rejection_reason)<span class="erp-md-muted">{{ $request->rejection_reason }}</span>@endif
                            @if ($request->isPending()){{ ($this->withdrawCertificateAction)(['request' => $request->id]) }}@endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
