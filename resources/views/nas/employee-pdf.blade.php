{{-- The personal details of an employee, as the copy on the NAS (Personeel/{name}/). --}}
@extends('nas.pdf-layout')

@php
    $e = $employee;
    $date = fn ($value) => $value?->format('d/m/Y');
    $rows = fn (array $pairs) => array_filter($pairs, fn ($value) => filled($value));
    $enum = fn ($value) => $value instanceof \Filament\Support\Contracts\HasLabel ? $value->getLabel() : $value;
@endphp

@section('content')
    <h1>{{ $e->fullName() }}</h1>
    <div class="muted">{{ __('erp.employees.tabs.personal') }}</div>

    <h2>{{ __('erp.employees.tabs.personal') }}</h2>
    <table>
        @foreach ($rows([
            __('erp.fields.first_name') => $e->first_name,
            __('erp.fields.last_name') => $e->last_name,
            __('erp.fields.address') => trim(implode(' ', array_filter([$e->street, $e->house_number, $e->addition])).', '.trim($e->postal_code.' '.$e->city).($e->country ? ', '.$e->country->localName() : ''), ', '),
            __('erp.employees.private_email') => $e->private_email,
            __('erp.employees.private_phone') => $e->private_phone,
            __('erp.fields.email') => $e->user?->email,
            __('erp.employees.national_number') => $e->national_number,
            __('erp.employees.date_of_birth') => $date($e->date_of_birth),
            __('erp.employees.place_of_birth') => $e->place_of_birth,
            __('erp.employees.mother_tongue') => $e->mother_tongue,
            __('erp.employees.bank_account') => $e->bank_account,
            __('erp.employees.education_level') => $enum($e->education_level),
        ]) as $label => $value)
            <tr><td class="label">{{ $label }}</td><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    <h2>{{ __('erp.employees.tabs.employment') }}</h2>
    <table>
        @foreach ($rows([
            __('erp.fields.job_title') => $e->user?->job_title,
            __('erp.employees.employment_date') => $date($e->employment_date),
            __('erp.employees.employment_category') => $enum($e->employment_category),
            __('erp.employees.contract_term') => $enum($e->contract_term),
            __('erp.employees.notice.started_on') => $date($e->notice_started_on),
            __('erp.employees.notice.ends_on') => $date($e->notice_ends_on),
            __('erp.employees.left_on') => $date($e->left_on),
            __('erp.employees.leaving_reason') => $e->leaving_reason,
        ]) as $label => $value)
            <tr><td class="label">{{ $label }}</td><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    <h2>{{ __('erp.employees.tabs.driver_license') }}</h2>
    <table>
        <tr><td class="label">{{ __('erp.employees.driver_license_number') }}</td><td>{{ $e->driver_license_number ?: '—' }}</td></tr>
        <tr><td class="label">{{ __('erp.employees.driver_license_categories') }}</td><td>{{ implode(', ', array_map(fn (\App\Enums\DriverLicenseCategory $category): string => $category->value, $e->driverLicenseCategories())) ?: '—' }}</td></tr>
        <tr><td class="label">{{ __('erp.employees.driver_license_expires_on') }}</td><td>{{ $date($e->driver_license_expires_on) ?? '—' }}</td></tr>
    </table>

    <h2>{{ __('erp.employees.tabs.emergency') }}</h2>
    <table>
        @foreach ([1, 2] as $n)
            @if (filled($e->{"emergency{$n}_first_name"}) || filled($e->{"emergency{$n}_phone"}))
                <tr>
                    <td class="label">{{ __("erp.employees.emergency_contact_{$n}") }}</td>
                    <td>{{ trim($e->{"emergency{$n}_first_name"}.' '.$e->{"emergency{$n}_last_name"}) }} · {{ $e->{"emergency{$n}_phone"} }}@if ($e->{"emergency{$n}_relation"}) · {{ $e->{"emergency{$n}_relation"} }}@endif</td>
                </tr>
            @endif
        @endforeach
    </table>

    <h2>{{ __('erp.employees.tabs.clothing') }}</h2>
    <table>
        <tr>
            <td>{{ __('erp.employees.size_pants') }}: {{ $e->size_pants ?: '—' }}</td>
            <td>{{ __('erp.employees.size_shirt') }}: {{ $e->size_shirt ?: '—' }}</td>
            <td>{{ __('erp.employees.size_sweater') }}: {{ $e->size_sweater ?: '—' }}</td>
            <td>{{ __('erp.employees.size_shoes') }}: {{ $e->size_shoes ?: '—' }}</td>
        </tr>
    </table>

    @if ($e->certificates->isNotEmpty())
        <h2>{{ __('erp.employees.certificates') }}</h2>
        <table>
            <tr><th>{{ __('erp.fields.type') }}</th><th>{{ __('erp.employees.obtained_on') }}</th><th>{{ __('erp.employees.expires_on') }}</th></tr>
            @foreach ($e->certificates as $certificate)
                <tr><td>{{ $enum($certificate->type) }}</td><td>{{ $date($certificate->obtained_on) }}</td><td>{{ $date($certificate->expires_on) ?? '—' }}</td></tr>
            @endforeach
        </table>
    @endif

    @if (filled($e->notes))
        <h2>{{ __('erp.fields.notes') }}</h2>
        <div style="white-space: pre-line;">{{ $e->notes }}</div>
    @endif
@endsection
