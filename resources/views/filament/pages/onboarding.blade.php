{{-- The onboarding documents of the signed-in user: open each one, then sign that you read it. --}}
<x-filament-panels::page>
    @php
        $documents = $this->documents();
        $signatures = $this->signatures();
    @endphp

    @if ($this->mustComplete())
        <div style="padding: .75rem 1rem; border-radius: .75rem; border: 1px solid rgb(245 158 11 / .5); background: rgb(245 158 11 / .1);" data-onboarding-required>
            <strong>{{ __('erp.onboarding.required_title') }}</strong><br>
            {{ __('erp.onboarding.required_help') }}
        </div>
    @endif

    @if ($documents->isEmpty())
        <x-filament::section>{{ __('erp.onboarding.none') }}</x-filament::section>
    @else
        <div style="display: grid; gap: .75rem;" wire:poll.10s>
            @foreach ($documents as $document)
                @php($row = $signatures->get($document->id))
                <x-filament::section>
                    <div style="display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between;" data-onboarding-document="{{ $document->id }}">
                        <div style="min-width: 0;">
                            <div style="font-weight: 600;">{{ $loop->iteration }}. {{ $document->title }}</div>
                            <div style="font-size: .85rem; opacity: .75;">
                                @if ($row?->isSigned())
                                    ✓ {{ __('erp.onboarding.signed_on', ['date' => $row->signed_at->format('d/m/Y H:i'), 'name' => $row->signer_name]) }}
                                @elseif ($row?->opened_at)
                                    {{ __('erp.onboarding.opened_on', ['date' => $row->opened_at->format('d/m/Y H:i')]) }}
                                @else
                                    {{ __('erp.onboarding.not_opened') }}
                                @endif
                            </div>
                        </div>
                        <div style="display: flex; gap: .5rem; flex-wrap: wrap;">
                            <x-filament::button tag="a" color="gray" icon="heroicon-o-document-text" :href="route('onboarding.document', $document)" target="_blank" wire:click="opened({{ $document->id }})">
                                {{ __('erp.onboarding.open') }}
                            </x-filament::button>
                            @unless ($row?->isSigned())
                                <x-filament::button icon="heroicon-o-pencil-square" :disabled="! $row?->opened_at" wire:click="mountAction('sign', {{ \Illuminate\Support\Js::from(['document' => $document->id]) }})">
                                    {{ __('erp.onboarding.sign') }}
                                </x-filament::button>
                            @endunless
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
