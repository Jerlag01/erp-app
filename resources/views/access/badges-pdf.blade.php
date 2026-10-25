{{-- The badge & electronic key list. --}}
@extends('access.pdf-layout')

@section('title', __('erp.access.badges'))

@section('content')
    <table>
        <thead>
            <tr>
                <th>{{ __('erp.access.employee') }}</th>
                <th>{{ __('erp.access.status') }}</th>
                <th>{{ __('erp.access.badge_number') }}</th>
                <th>{{ __('erp.access.key_number') }}</th>
                <th class="center">{{ __('erp.access.has_clad') }}</th>
                <th style="width: 40%;">{{ __('erp.access.clearance') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($badges as $badge)
                <tr @class(['off' => ! $badge->isActive()])>
                    <td>{{ $badge->employee?->fullName() }}</td>
                    <td>{{ $badge->isActive() ? __('erp.access.active') : __('erp.access.deactivated') }}</td>
                    <td class="mono">{{ $badge->badge_number }}</td>
                    <td class="mono">{{ $badge->key_number }}</td>
                    <td class="center">{{ $badge->has_clad ? '✓' : '' }}</td>
                    <td style="white-space: pre-line;">{{ $badge->clearance }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
