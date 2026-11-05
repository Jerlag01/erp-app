{{-- The delivery reports the signed-in employee still has to sign, and their signed documents. --}}
<x-filament-panels::page>
    @php
        $reports = $this->toSign();
        $documents = $this->signedDocuments();
    @endphp

    <x-filament::section :heading="__('erp.it.delivery_report.to_sign')">
        @forelse ($reports as $report)
            <div style="display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; padding: .5rem 0; {{ $loop->first ? '' : 'border-top: 1px solid rgb(148 163 184 / .25);' }}" data-delivery-report="{{ $report->id }}">
                <div style="min-width: 0;">
                    <div style="font-weight: 600;">{{ $report->deviceLabel() }}</div>
                    <div style="font-size: .85rem; opacity: .75;">{{ $report->handed_over_on->format('d/m/Y') }} · {{ __('erp.it.delivery_report.pending') }}</div>
                </div>
                <div style="display: flex; gap: .5rem; flex-wrap: wrap;">
                    <x-filament::button tag="a" color="gray" icon="heroicon-o-document-text" :href="route('it.delivery-report', $report)" target="_blank">
                        {{ __('erp.it.delivery_report.view') }}
                    </x-filament::button>
                    <x-filament::button icon="heroicon-o-pencil-square" wire:click="mountAction('sign', {{ \Illuminate\Support\Js::from(['report' => $report->id]) }})">
                        {{ __('erp.it.delivery_report.sign') }}
                    </x-filament::button>
                </div>
            </div>
        @empty
            <div style="opacity: .7;">{{ __('erp.it.delivery_report.none_to_sign') }}</div>
        @endforelse
    </x-filament::section>

    <x-filament::section :heading="__('erp.signed_documents.title')">
        @forelse ($documents as $document)
            <div style="display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; padding: .5rem 0; {{ $loop->first ? '' : 'border-top: 1px solid rgb(148 163 184 / .25);' }}">
                <div style="min-width: 0;">
                    <div style="font-weight: 600;">{{ $document->title }}</div>
                    <div style="font-size: .85rem; opacity: .75;">{{ $document->kindLabel() }} · {{ __('erp.it.delivery_report.signed_on', ['date' => $document->signed_at->format('d/m/Y H:i')]) }}</div>
                </div>
                <x-filament::button tag="a" color="gray" icon="heroicon-o-arrow-down-tray" :href="route('signed-documents.download', $document)">
                    {{ __('erp.signed_documents.download') }}
                </x-filament::button>
            </div>
        @empty
            <div style="opacity: .7;">{{ __('erp.signed_documents.none') }}</div>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
