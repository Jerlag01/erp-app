{{-- The idea/complaint box link, left of the search bar. Inline styles: Filament ships precompiled CSS. --}}
@if (\App\Filament\App\Pages\SubmitSuggestion::canAccess())
    <a href="{{ \App\Filament\App\Pages\SubmitSuggestion::getUrl(panel: 'app') }}" data-suggestion-link
       title="{{ __('erp.suggestions.title') }}"
       style="display: inline-flex; align-items: center; gap: .375rem; padding: .375rem .75rem; border-radius: .5rem; border: 1px solid rgb(247 148 29 / .5); background: rgb(247 148 29 / .1); font-size: .875rem; font-weight: 600; white-space: nowrap;">
        <x-filament::icon icon="heroicon-o-light-bulb" style="width: 1.125rem; height: 1.125rem; color: var(--erp-accent);" />
        <span class="erp-suggestion-label">{{ __('erp.suggestions.link') }}</span>
    </a>
@endif
