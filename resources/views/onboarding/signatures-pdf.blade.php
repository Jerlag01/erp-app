{{-- The onboarding signatures: who opened and signed which document, with the written signature. --}}
@extends('access.pdf-layout')

@section('title', __('erp.onboarding.signatures'))

@section('content')
    <table>
        <thead>
            <tr>
                <th>{{ __('erp.onboarding.employee') }}</th>
                <th>{{ __('erp.onboarding.document_title') }}</th>
                <th>{{ __('erp.onboarding.language') }}</th>
                <th>{{ __('erp.onboarding.opened_at') }}</th>
                <th>{{ __('erp.onboarding.signed_at') }}</th>
                <th>{{ __('erp.onboarding.signer_name') }}</th>
                <th style="width: 18%;">{{ __('erp.onboarding.signature') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($signatures as $signature)
                <tr>
                    <td>{{ $signature->user?->name }}</td>
                    <td>{{ $signature->document?->title }}</td>
                    <td>{{ \App\Models\OnboardingDocument::LOCALES[$signature->document?->locale] ?? '' }}</td>
                    <td>{{ $signature->opened_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $signature->signed_at?->format('d/m/Y H:i') ?? __('erp.onboarding.not_signed') }}</td>
                    <td>{{ $signature->signer_name }}</td>
                    <td>@if ($image = $signature->signatureDataUri())<img src="{{ $image }}" alt="" style="height: 28px;">@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
