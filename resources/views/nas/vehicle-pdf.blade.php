{{-- A vehicle with its fuel passes, as the copy on the NAS (Wagenpark/{plate}/). Never a PIN code. --}}
@extends('nas.pdf-layout')

@php
    $v = $vehicle;
    $enum = fn ($value) => $value instanceof \Filament\Support\Contracts\HasLabel ? $value->getLabel() : $value;
@endphp

@section('content')
    <h1>{{ $v->plate_number }}</h1>
    <div class="muted">{{ trim($v->brand.' '.$v->type) }}</div>

    <h2>{{ __('erp.fields.vehicle') }}</h2>
    <table>
        @foreach (array_filter([
            __('erp.fields.plate_number') => $v->plate_number,
            __('erp.fields.brand') => $v->brand,
            __('erp.fields.type') => $v->type,
            __('erp.fields.vin') => $v->vin,
            __('erp.fields.year') => $v->year,
            __('erp.fields.fuel') => $enum($v->fuel),
            __('erp.fields.mileage') => $v->mileage ? number_format((float) $v->mileage, 0, ',', '.').' km' : null,
            __('erp.fields.control_date') => $v->control_date?->format('d/m/Y'),
            __('erp.fields.next_control_date') => $v->next_control_date?->format('d/m/Y'),
            __('erp.fields.status') => $enum($v->status),
            __('erp.fields.driver') => $v->driver?->name,
            __('erp.fields.notes') => $v->notes,
        ], fn ($value) => filled($value)) as $label => $value)
            <tr><td class="label">{{ $label }}</td><td style="white-space: pre-line;">{{ $value }}</td></tr>
        @endforeach
    </table>

    <h2>{{ __('erp.fuel_passes.plural') }}</h2>
    @if ($v->fuelPasses->isEmpty())
        <div class="muted">—</div>
    @else
        <table>
            <tr><th>{{ __('erp.fuel_passes.card_number') }}</th><th>{{ __('erp.fuel_passes.expires_on') }}</th><th>{{ __('erp.fields.notes') }}</th></tr>
            @foreach ($v->fuelPasses as $pass)
                <tr>
                    <td style="font-family: 'DejaVu Sans Mono', monospace;">{{ $pass->formattedCardNumber() }}</td>
                    <td>{{ $pass->expiryLabel() ?? '—' }}@if ($pass->isExpired()) ({{ __('erp.fuel_passes.expired') }})@endif</td>
                    <td>{{ $pass->notes }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endsection
