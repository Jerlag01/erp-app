<x-filament-panels::page>
    {{-- Filament ships precompiled CSS, so this table is styled inline. --}}
    <style>
        .erp-waste-totals table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .erp-waste-totals th { text-align: left; padding: .5rem .75rem; font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; opacity: .7; border-bottom: 1px solid rgb(148 163 184 / .4); }
        .erp-waste-totals td { padding: .45rem .75rem; border-bottom: 1px solid rgb(148 163 184 / .2); }
        .erp-waste-totals .num { text-align: right; white-space: nowrap; }
        .erp-waste-totals .mono { font-family: var(--erp-mono, monospace); }
        .erp-waste-totals .hazard { color: rgb(220 38 38); }
        .erp-waste-totals .muted { opacity: .6; }
        .erp-waste-totals tr.group td { font-weight: 700; background: rgb(148 163 184 / .12); }
        .erp-waste-totals tr.total td { font-weight: 700; border-top: 2px solid var(--primary-500); border-bottom: 0; }
    </style>

    <x-filament::section>
        <div style="display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end;">
            <label style="display: flex; flex-direction: column; gap: .25rem; font-size: .8125rem;">
                {{ __('erp.incidents.from') }}
                <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper>
            </label>
            <label style="display: flex; flex-direction: column; gap: .25rem; font-size: .8125rem;">
                {{ __('erp.incidents.until') }}
                <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="until" /></x-filament::input.wrapper>
            </label>
            <div style="display: flex; flex-wrap: wrap; gap: .5rem;">
                @foreach ($this->years() as $year)
                    <x-filament::button size="sm" color="gray" wire:click="setYear({{ $year }})">{{ $year }}</x-filament::button>
                @endforeach
                <x-filament::button size="sm" color="gray" wire:click="clearPeriod">{{ __('erp.waste.all_time') }}</x-filament::button>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <div class="erp-waste-totals" style="overflow-x: auto;">
            @include('waste.totals-table', ['totals' => $this->totals()])
        </div>
    </x-filament::section>
</x-filament-panels::page>
