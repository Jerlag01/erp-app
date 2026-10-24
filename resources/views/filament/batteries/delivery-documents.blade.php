{{-- The order, confirmation and delivery note of a battery delivery: a link per document present. --}}
@php
    $delivery = $getRecord();
    $may = \Illuminate\Support\Facades\Gate::allows('viewDocuments', $delivery);
@endphp
<div style="display: flex; flex-direction: column; gap: .15rem; font-size: .8rem; padding: .25rem 0;" data-delivery-documents>
    @foreach (\App\Models\BatteryDelivery::DOCUMENTS as $kind)
        <span style="white-space: nowrap;">
            @if ($may && $delivery->hasDocument($kind))
                <a href="{{ route('batteries.delivery-document', ['delivery' => $delivery, 'kind' => $kind]) }}" target="_blank" rel="noopener" style="text-decoration: underline;" title="{{ $delivery->documentName($kind) }}">✓ {{ __("erp.batteries.delivery_documents.{$kind}_short") }}</a>
            @else
                <span style="opacity: .45;">— {{ __("erp.batteries.delivery_documents.{$kind}_short") }}</span>
            @endif
        </span>
    @endforeach
</div>
