{{-- The monthly advances per statute, with the period and start values that can be changed. --}}
@php
    $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',');
    $money = fn ($value) => '€ '.number_format((float) $value, 2, ',', '.');
    $rows = $this->rows();
@endphp
<x-filament-panels::page>
    <x-filament::section>
        <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); align-items: end;">
            <label style="display: flex; flex-direction: column; gap: .25rem;">
                <span style="font-size: .875rem; font-weight: 500;">{{ __('erp.hr.advances.month') }}</span>
                <x-filament::input.wrapper><x-filament::input type="month" wire:model.live="month" /></x-filament::input.wrapper>
            </label>
            <label style="display: flex; flex-direction: column; gap: .25rem;">
                <span style="font-size: .875rem; font-weight: 500;">{{ __('erp.hr.advances.from') }}</span>
                <x-filament::input.wrapper><x-filament::input type="date" wire:model.live.debounce.500ms="from" /></x-filament::input.wrapper>
            </label>
            <label style="display: flex; flex-direction: column; gap: .25rem;">
                <span style="font-size: .875rem; font-weight: 500;">{{ __('erp.hr.advances.until') }}</span>
                <x-filament::input.wrapper><x-filament::input type="date" wire:model.live.debounce.500ms="until" /></x-filament::input.wrapper>
            </label>
            <label style="display: flex; flex-direction: column; gap: .25rem;">
                <span style="font-size: .875rem; font-weight: 500;">{{ __('erp.hr.advances.base_workers') }}</span>
                <x-filament::input.wrapper prefix="€"><x-filament::input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="bases.workers" /></x-filament::input.wrapper>
            </label>
            <label style="display: flex; flex-direction: column; gap: .25rem;">
                <span style="font-size: .875rem; font-weight: 500;">{{ __('erp.hr.advances.base_servants') }}</span>
                <x-filament::input.wrapper prefix="€"><x-filament::input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="bases.servants" /></x-filament::input.wrapper>
            </label>
        </div>
        <p style="margin: .75rem 0 0; font-size: .8rem; opacity: .75;">{{ __('erp.hr.advances.rules') }}</p>
    </x-filament::section>

    <x-filament::tabs>
        <x-filament::tabs.item :active="$group === 'workers'" wire:click="setGroup('workers')" icon="heroicon-o-wrench-screwdriver">{{ __('erp.hr.advances.workers') }}</x-filament::tabs.item>
        <x-filament::tabs.item :active="$group === 'servants'" wire:click="setGroup('servants')" icon="heroicon-o-briefcase">{{ __('erp.hr.advances.servants') }}</x-filament::tabs.item>
    </x-filament::tabs>

    <x-filament::section>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: .875rem;" data-hr-advances>
                <thead>
                    <tr style="text-align: right; border-bottom: 1px solid rgb(148 163 184 / .4);">
                        <th style="text-align: left; padding: .5rem;">{{ __('erp.hr.advances.employee') }}</th>
                        <th style="padding: .5rem;">{{ __('erp.hr.advances.start_value') }}</th>
                        <th style="padding: .5rem;">{{ __('erp.hr.advances.workdays') }}</th>
                        <th style="padding: .5rem;">{{ __('erp.hr.advances.vacation') }}</th>
                        <th style="padding: .5rem;">{{ __('erp.hr.advances.family') }}</th>
                        <th style="padding: .5rem;">{{ __('erp.hr.advances.unpaid') }}</th>
                        <th style="padding: .5rem;">{{ __('erp.hr.advances.worked') }}</th>
                        <th style="padding: .5rem;">{{ __('erp.hr.advances.advance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php($result = $row['result'])
                        <tr wire:key="advance-{{ $row['employee']->id }}" style="text-align: right; border-bottom: 1px solid rgb(148 163 184 / .2);">
                            <td style="text-align: left; padding: .5rem;"><strong>{{ $row['employee']->fullName() }}</strong><br><span style="font-size: .75rem; opacity: .7;">{{ $row['employee']->employment_category?->getLabel() }}</span></td>
                            <td style="padding: .5rem; width: 9rem;">
                                <x-filament::input.wrapper prefix="€">
                                    <x-filament::input type="number" step="0.01" min="0" :placeholder="number_format($result['base'], 2, '.', '')" wire:model.live.debounce.500ms="overrides.{{ $row['employee']->id }}" />
                                </x-filament::input.wrapper>
                            </td>
                            <td style="padding: .5rem;">{{ $number($result['total']) }}</td>
                            <td style="padding: .5rem;">{{ $number($result['vacation']) }}@if ($result['vacation_counted']) <span title="{{ __('erp.hr.advances.vacation_counted') }}">*</span>@endif</td>
                            <td style="padding: .5rem;">{{ $number($result['family']) }}</td>
                            <td style="padding: .5rem;">{{ $number($result['unpaid']) }}</td>
                            <td style="padding: .5rem;"><strong>{{ $number($result['worked']) }}</strong></td>
                            <td style="padding: .5rem;"><strong>{{ $money($result['advance']) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="padding: 1rem; text-align: center; opacity: .7;">{{ __('erp.hr.advances.none') }}</td></tr>
                    @endforelse
                </tbody>
                @if ($rows !== [])
                    <tfoot>
                        <tr style="text-align: right;">
                            <td colspan="7" style="padding: .5rem;"><strong>{{ __('erp.pdf.total') }}</strong></td>
                            <td style="padding: .5rem;"><strong>{{ $money($this->total()) }}</strong></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <p style="margin: .75rem 0 0; font-size: .75rem; opacity: .7;">* {{ __('erp.hr.advances.vacation_counted') }} · {{ __('erp.hr.advances.formula') }}</p>
    </x-filament::section>
</x-filament-panels::page>
