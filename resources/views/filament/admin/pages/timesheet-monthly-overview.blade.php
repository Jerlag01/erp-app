{{-- Everyone's prestations per month (admin). --}}
<x-filament-panels::page>
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; margin-bottom: 1rem;">
        <div style="display: inline-flex; border: 1px solid rgb(148 163 184 / .4); border-radius: .5rem; overflow: hidden;">
            <button type="button" wire:click="previous" style="padding: .4rem .7rem;" aria-label="{{ __('erp.calendar.previous') }}">&larr;</button>
            <button type="button" wire:click="next" style="padding: .4rem .7rem; border-left: 1px solid rgb(148 163 184 / .4);" aria-label="{{ __('erp.calendar.next') }}">&rarr;</button>
        </div>
        <div style="display: inline-flex; border: 1px solid rgb(148 163 184 / .4); border-radius: .5rem; overflow: hidden;" role="group">
            @foreach (['1' => __('erp.timesheets.span_month'), '2' => __('erp.timesheets.span_two_months'), 'year' => __('erp.timesheets.span_year')] as $value => $label)
                <button type="button" wire:click="setSpan('{{ $value }}')" aria-pressed="{{ $span === $value ? 'true' : 'false' }}"
                    style="padding: .4rem .8rem; {{ $loop->first ? '' : 'border-left: 1px solid rgb(148 163 184 / .4);' }} {{ $span === $value ? 'background: var(--erp-navy); color: #fff;' : '' }}">{{ $label }}</button>
            @endforeach
        </div>
        <input type="month" wire:model.live="month" style="padding: .35rem .6rem; border: 1px solid rgb(148 163 184 / .4); border-radius: .5rem; background: transparent;" aria-label="{{ __('erp.timesheets.month') }}">
        <h2 style="font-size: 1.125rem; font-weight: 700;">{{ $title }}</h2>
        <div wire:loading.delay style="font-size: .8125rem; opacity: .7;">{{ __('erp.calendar.loading') }}</div>
    </div>

    @foreach ($report['months'] as $block)
        <x-filament::section>
            <div style="overflow-x: auto; background: #fff; color: #0f172a; border-radius: .5rem; padding: .5rem;">
                @include('timesheets.report-table', ['block' => $block, 'fontSize' => '.75rem'])
            </div>
        </x-filament::section>
    @endforeach

    <p style="font-size: .75rem; opacity: .75;">
        <strong>V</strong> Verlof · <strong>Z</strong> Ziekte · <strong>R</strong> Recuperatie overuren · <strong>IR</strong> Inhaalrust · <strong>FV</strong> Familiaal verlof · <strong>OP</strong> Opleiding · <strong>F</strong> Feestdag —
        {{ __('erp.timesheets.report_mobility', ['rate' => number_format($rate, 4, ',', '')]) }}
    </p>
</x-filament-panels::page>
