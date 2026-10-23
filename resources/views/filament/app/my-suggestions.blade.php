{{-- The employee's own ideas and complaints, and the conversation on the one that is open. --}}
<x-filament-panels::page>
    @php($current = $this->current())

    @if ($current)
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
                <x-filament::link tag="button" wire:click="open(null)" icon="heroicon-o-arrow-left">{{ __('erp.suggestions.responses.back') }}</x-filament::link>
            </div>

            <x-filament::section>
                <x-slot name="heading">{{ $current->type->getLabel() }} · {{ $current->submitted_at->format('d/m/Y H:i') }}</x-slot>
                <x-slot name="afterHeader"><x-filament::badge :color="$current->status->getColor()">{{ $current->status->getLabel() }}</x-filament::badge></x-slot>
                <div style="white-space: pre-wrap;">{{ $current->description }}</div>
                @if (filled($current->photos))
                    <div style="display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem;">
                        @foreach (array_values($current->photos) as $index => $path)
                            @php($url = route('suggestions.photo', ['suggestion' => $current, 'index' => $index]))
                            <a href="{{ $url }}" target="_blank" rel="noopener"><img src="{{ $url }}" alt="{{ basename($path) }}" loading="lazy" style="width: 7rem; height: 5.25rem; object-fit: cover; border-radius: .375rem;"></a>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section :heading="__('erp.suggestions.responses.title')" icon="heroicon-o-chat-bubble-left-right">
                @include('filament.suggestions.thread', ['suggestion' => $current])
            </x-filament::section>

            <x-filament::section :heading="__('erp.suggestions.responses.reply')">
                {{ $this->replyForm }}
            </x-filament::section>
        </div>
    @else
        @php($suggestions = $this->suggestions())

        @if ($suggestions->isEmpty())
            <x-filament::section>
                <p style="margin: 0;">{{ __('erp.suggestions.responses.no_submissions') }}</p>
            </x-filament::section>
        @else
            <div style="display: flex; flex-direction: column; gap: .75rem;">
                @foreach ($suggestions as $item)
                    <button type="button" wire:key="suggestion-{{ $item->id }}" wire:click="open({{ $item->id }})" data-suggestion="{{ $item->id }}"
                        style="text-align: left; width: 100%; padding: .875rem 1rem; border-radius: .75rem; border: 1px solid rgb(148 163 184 / .35); background: transparent; cursor: pointer;">
                        <div style="display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; justify-content: space-between;">
                            <strong>{{ $item->type->getLabel() }} · {{ $item->submitted_at->format('d/m/Y') }}</strong>
                            <span style="display: inline-flex; gap: .5rem; align-items: center;">
                                <span style="font-size: .8rem; opacity: .75;">{{ trans_choice('erp.suggestions.responses.count', $item->responses_count + (filled($item->response) ? 1 : 0), ['count' => $item->responses_count + (filled($item->response) ? 1 : 0)]) }}</span>
                                <x-filament::badge :color="$item->status->getColor()">{{ $item->status->getLabel() }}</x-filament::badge>
                            </span>
                        </div>
                        <div style="margin-top: .25rem; opacity: .85;">{{ \Illuminate\Support\Str::limit($item->description, 160) }}</div>
                    </button>
                @endforeach
            </div>
        @endif
    @endif
</x-filament-panels::page>
