{{-- The security tag list, for the security system. --}}
@extends('access.pdf-layout')

@section('title', __('erp.access.tags'))

@section('content')
    <table>
        <thead>
            <tr>
                <th>{{ __('erp.access.tag_number') }}</th>
                <th>{{ __('erp.access.employee') }}</th>
                <th>{{ __('erp.access.system_name') }}</th>
                <th>{{ __('erp.access.status') }}</th>
                <th class="center">{{ __('erp.access.access_warehouse') }}</th>
                <th class="center">{{ __('erp.access.access_office') }}</th>
                <th class="center">{{ __('erp.access.door_keys') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tags as $tag)
                <tr @class(['off' => ! $tag->isActive()])>
                    <td class="mono">{{ $tag->tag_number }}</td>
                    <td>{{ $tag->employee?->fullName() }}</td>
                    <td>{{ $tag->system_name }}</td>
                    <td>{{ $tag->isActive() ? __('erp.access.active') : __('erp.access.deactivated') }}</td>
                    <td class="center">{{ $tag->access_warehouse ? '✓' : '' }}</td>
                    <td class="center">{{ $tag->access_office ? '✓' : '' }}</td>
                    <td class="center">{{ $tag->door_keys ? '✓' : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
