{{-- The written signature on a document, inside its signature box (empty while not signed). --}}
@php
    $signature = $dossier->signature($document);
    $image = $signature?->dataUri();
@endphp
@if ($signature)
    <div style="font-size: 7.5pt;">
        @if ($image)<img src="{{ $image }}" alt="" style="height: {{ $height ?? 38 }}px; max-width: 100%;"><br>@endif
        {{ $signature->signer_name }} · {{ $signature->signed_at->format('d/m/Y H:i') }}
    </div>
@endif
