<x-filament-panels::page>
    @include('partials.rich-content-css')
    {{-- Filament ships precompiled CSS, so the layout here uses inline styles. --}}
    <style>
        .erp-kb-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr)); }
        .erp-kb-list { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: .375rem; }
        .erp-kb-list a { text-decoration: none; }
        .erp-kb-list a:hover { text-decoration: underline; }
        .erp-kb-muted { font-size: .8125rem; opacity: .7; }
        .erp-kb-layout { display: grid; gap: 1.5rem; }
        @media (min-width: 1024px) { .erp-kb-layout { grid-template-columns: minmax(0, 1fr) 20rem; } }
    </style>

    @if ($current = $this->currentArticle())
        <div class="erp-kb-layout" data-kb-article="{{ $current->id }}">
            <div style="display: flex; flex-direction: column; gap: 1rem; min-width: 0;">
                <x-filament::section>
                    <div class="erp-kb-article erp-rich">{{ $current->renderedBody() }}</div>
                </x-filament::section>

                @if ($downloads = $current->downloads())
                    <x-filament::section :heading="__('erp.kb.downloads')" icon="heroicon-o-paper-clip">
                        <ul class="erp-kb-list">
                            @foreach ($downloads as $download)
                                <li>
                                    @if (\App\Support\PdfAccess::isPdf($download['name']) && ! \App\Support\PdfAccess::canDownload(auth()->user(), 'knowledge_base'))
                                        <span>{{ $download['name'] }}</span> <span class="erp-kb-muted">({{ __('erp.pdf.no_download_right') }})</span>
                                    @else
                                        <x-filament::link :href="route('kb.download', ['article' => $current, 'index' => $download['index']])" icon="heroicon-o-arrow-down-tray">
                                            {{ $download['name'] }}
                                        </x-filament::link>
                                    @endif
                                    <span class="erp-kb-muted">· {{ $download['size'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-filament::section>
                @endif
            </div>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <x-filament::section>
                    <x-filament::link tag="button" wire:click="backToOverview" icon="heroicon-o-arrow-left">{{ __('erp.kb.back') }}</x-filament::link>
                    <p class="erp-kb-muted" style="margin-top: .75rem;">
                        {{ __('erp.kb.updated_on', ['date' => $current->updated_at->format('d/m/Y')]) }}@if ($current->editor) · {{ $current->editor->name }}@endif
                    </p>
                    @unless ($current->is_published && $current->category->is_visible)
                        <x-filament::badge color="warning" style="margin-top: .5rem;">{{ __('erp.kb.not_visible_badge') }}</x-filament::badge>
                    @endunless
                </x-filament::section>

                @if (($related = $this->related($current))->isNotEmpty())
                    <x-filament::section :heading="__('erp.kb.related')">
                        <ul class="erp-kb-list">
                            @foreach ($related as $item)
                                <li><x-filament::link tag="button" wire:click="$set('article', {{ $item->id }})">{{ $item->translate('title') }}</x-filament::link></li>
                            @endforeach
                        </ul>
                    </x-filament::section>
                @endif
            </div>
        </div>
    @else
        <div style="display: flex; flex-wrap: wrap; gap: .75rem; align-items: center;">
            <div style="flex: 1 1 20rem; max-width: 36rem;">
                <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                    <x-filament::input type="search" wire:model.live.debounce.400ms="search" :placeholder="__('erp.kb.search')" data-kb-search />
                </x-filament::input.wrapper>
            </div>
            @if ($this->category)
                <x-filament::link tag="button" wire:click="$set('category', null)" icon="heroicon-o-x-mark">{{ __('erp.kb.all_categories') }}</x-filament::link>
            @endif
        </div>

        @if (($results = $this->results()) !== null)
            <x-filament::section :heading="__('erp.kb.results', ['count' => $results->count()])">
                @if ($results->isEmpty())
                    <p class="erp-kb-muted">{{ __('erp.kb.no_results') }}</p>
                @else
                    <ul class="erp-kb-list">
                        @foreach ($results as $result)
                            <li>
                                <x-filament::link tag="button" wire:click="$set('article', {{ $result->id }})">{{ $result->translate('title') }}</x-filament::link>
                                <span class="erp-kb-muted">· {{ $result->category->translate('name') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        @else
            @php($categories = $this->categories())

            @if ($categories->isEmpty())
                <x-filament::section>
                    <p class="erp-kb-muted">{{ __('erp.kb.empty') }}</p>
                </x-filament::section>
            @else
                <div class="erp-kb-grid">
                    @foreach ($categories as $kbCategory)
                        <x-filament::section :heading="$kbCategory->translate('name')" :description="$kbCategory->translate('description') ?: null" icon="heroicon-o-folder">
                            @unless ($kbCategory->is_visible)
                                <x-filament::badge color="warning" style="margin-bottom: .5rem;">{{ __('erp.kb.hidden_badge') }}</x-filament::badge>
                            @endunless
                            <ul class="erp-kb-list">
                                @foreach ($kbCategory->articles->take($this->category ? 500 : 6) as $item)
                                    <li>
                                        <x-filament::link tag="button" wire:click="$set('article', {{ $item->id }})">{{ $item->translate('title') }}</x-filament::link>
                                        @unless ($item->is_published)
                                            <span class="erp-kb-muted">· {{ __('erp.kb.draft') }}</span>
                                        @endunless
                                    </li>
                                @endforeach
                            </ul>
                            @if (! $this->category && $kbCategory->articles->count() > 6)
                                <div style="margin-top: .75rem;">
                                    <x-filament::link tag="button" wire:click="$set('category', {{ $kbCategory->id }})" icon="heroicon-o-arrow-right" icon-position="after">
                                        {{ __('erp.kb.show_all', ['count' => $kbCategory->articles->count()]) }}
                                    </x-filament::link>
                                </div>
                            @endif
                        </x-filament::section>
                    @endforeach
                </div>
            @endif
        @endif
    @endif
</x-filament-panels::page>
