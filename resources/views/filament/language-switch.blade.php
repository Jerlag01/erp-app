{{-- Language switch in the top bar (both panels). --}}
@php
    $locales = config('app.locales');
    $current = app()->getLocale();
@endphp
<x-filament::dropdown placement="bottom-end" teleport>
    <x-slot name="trigger">
        <button type="button" title="{{ __('erp.language.switch') }}" aria-label="{{ __('erp.language.switch') }}"
            style="display: inline-flex; align-items: center; gap: .35rem; padding: .35rem .6rem; border-radius: .5rem; font-size: .8125rem; font-weight: 600; letter-spacing: .04em;"
            class="fi-icon-btn">
            <x-filament::icon icon="heroicon-o-language" style="width: 1.25rem; height: 1.25rem;" />
            {{ strtoupper($current) }}
        </button>
    </x-slot>

    <x-filament::dropdown.list>
        @foreach ($locales as $code => $name)
            <form method="POST" action="{{ route('locale.update') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $code }}">
                <x-filament::dropdown.list.item tag="button" type="submit" :color="$code === $current ? 'primary' : 'gray'" :icon="$code === $current ? 'heroicon-m-check' : null">
                    {{ $name }}
                </x-filament::dropdown.list.item>
            </form>
        @endforeach
    </x-filament::dropdown.list>
</x-filament::dropdown>
