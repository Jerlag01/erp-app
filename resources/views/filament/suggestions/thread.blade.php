{{--
    The conversation on an idea or complaint, oldest first: replies of administrators and of the
    submitter, with their photos (served by a controller that checks access). Inline styles: Filament
    ships precompiled CSS.
--}}
@php
    /** @var \App\Models\Suggestion $suggestion */
    $responses = $suggestion->responses()->get();
@endphp
<div style="display: flex; flex-direction: column; gap: .75rem;" data-suggestion-thread>
    @if (filled($suggestion->response))
        {{-- The answer given before replies existed. --}}
        <div style="align-self: flex-start; max-width: 48rem; padding: .75rem 1rem; border-radius: .75rem; border: 1px solid rgb(14 116 194 / .35); background: rgb(14 116 194 / .08);">
            <div style="font-size: .75rem; opacity: .75; margin-bottom: .25rem;">{{ $suggestion->handler?->name ?? __('erp.suggestions.responses.management') }}@if ($suggestion->handled_at) · {{ $suggestion->handled_at->format('d/m/Y H:i') }}@endif</div>
            <div style="white-space: pre-wrap;">{{ $suggestion->response }}</div>
        </div>
    @endif

    @forelse ($responses as $response)
        @php($mine = $response->user_id === auth()->id())
        <div wire:key="response-{{ $response->id }}" data-response
             style="align-self: {{ $mine ? 'flex-end' : 'flex-start' }}; max-width: 48rem; padding: .75rem 1rem; border-radius: .75rem; border: 1px solid {{ $response->from_admin ? 'rgb(14 116 194 / .35)' : 'rgb(148 163 184 / .4)' }}; background: {{ $response->from_admin ? 'rgb(14 116 194 / .08)' : 'rgb(148 163 184 / .1)' }};">
            <div style="font-size: .75rem; opacity: .75; margin-bottom: .25rem;">
                <strong>{{ $response->author_name }}</strong>@if ($response->from_admin) · {{ __('erp.suggestions.responses.management') }}@endif · {{ $response->created_at->format('d/m/Y H:i') }}
            </div>
            <div style="white-space: pre-wrap;">{{ $response->body }}</div>
            @if (filled($response->photos))
                <div style="display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .5rem;">
                    @foreach (array_values($response->photos) as $index => $path)
                        @php($url = route('suggestions.response-photo', ['response' => $response, 'index' => $index]))
                        <a href="{{ $url }}" target="_blank" rel="noopener" title="{{ basename($path) }}">
                            <img src="{{ $url }}" alt="{{ basename($path) }}" loading="lazy" style="display: block; width: 7rem; height: 5.25rem; object-fit: cover; border-radius: .375rem; border: 1px solid rgb(148 163 184 / .35);">
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        @if (blank($suggestion->response))
            <p style="opacity: .7; margin: 0;">{{ __('erp.suggestions.responses.none') }}</p>
        @endif
    @endforelse
</div>
