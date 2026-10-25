{{-- The written signature of an onboarding document, small, in the signatures list. --}}
@php($image = $getRecord()->signatureDataUri())
@if ($image)
    <img src="{{ $image }}" alt="" style="max-height: 40px; max-width: 140px; background: #fff; border-radius: .25rem; padding: .125rem;">
@else
    <span style="opacity: .5;">—</span>
@endif
