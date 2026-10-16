@extends('batteries.pdf.layout', ['landscape' => true])

@section('title', __('battery_documents.floor_plan.title'))

@section('content')
    <table class="w plain">
        <tr>
            <td><h1>{{ __('battery_documents.floor_plan.title') }}</h1></td>
            <td class="num">{{ __('battery_documents.common.dossier') }} <strong>{{ $dossier->reference() }}</strong> · {{ __('battery_documents.common.cow_code') }} <strong>{{ $dossier->cowCode() }}</strong> · {{ __('battery_documents.common.room') }} {{ $dossier->new_room }}</td>
        </tr>
    </table>

    @if ($image = $dossier->floorPlanDataUri())
        <div class="center" style="border: 1px solid #94a3b8;">
            <img src="{{ $image }}" style="max-width: 100%; max-height: 160mm;" alt="">
        </div>
    @else
        <p class="muted">{{ __('battery_documents.floor_plan.empty') }}</p>
    @endif
@endsection
