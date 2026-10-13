{{-- Prestation sheets of all employees for one week. --}}
@php($dec = fn ($value) => number_format((float) $value, 2, ',', ''))
<x-filament-panels::page>
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; margin-bottom: 1rem;">
        <div style="display: inline-flex; border: 1px solid rgb(148 163 184 / .4); border-radius: .5rem; overflow: hidden;">
            <button type="button" wire:click="previousWeek" style="padding: .4rem .7rem;" aria-label="{{ __('erp.calendar.previous') }}">&larr;</button>
            <button type="button" wire:click="thisWeek" style="padding: .4rem .7rem; border-left: 1px solid rgb(148 163 184 / .4);">{{ __('erp.calendar.today') }}</button>
            <button type="button" wire:click="nextWeek" style="padding: .4rem .7rem; border-left: 1px solid rgb(148 163 184 / .4);" aria-label="{{ __('erp.calendar.next') }}">&rarr;</button>
        </div>
        <h2 style="font-size: 1.125rem; font-weight: 700;">{{ __('erp.timesheets.week') }} {{ $monday->isoWeek() }} · {{ $monday->format('d/m/Y') }} – {{ $monday->addDays(4)->format('d/m/Y') }}</h2>
    </div>

    <x-filament::section>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: .875rem;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid rgb(148 163 184 / .4);">
                        <th style="padding: .5rem;">{{ __('erp.timesheets.employee') }}</th>
                        <th style="padding: .5rem;">{{ __('erp.timesheets.plate') }}</th>
                        @foreach (\App\Support\Timesheets::DAYS as $day)<th style="padding: .5rem; text-align: right; text-transform: capitalize;">{{ mb_substr($day, 0, 2) }}</th>@endforeach
                        <th style="padding: .5rem; text-align: right;">{{ __('erp.timesheets.hours') }}</th>
                        <th style="padding: .5rem; text-align: right;">{{ __('erp.timesheets.overtime_week') }}</th>
                        <th style="padding: .5rem; text-align: right;">{{ __('erp.timesheets.overtime_balance') }}</th>
                        <th style="padding: .5rem; text-align: right;">Km</th>
                        <th style="padding: .5rem;">{{ __('erp.timesheets.sent_column') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr style="border-bottom: 1px solid rgb(148 163 184 / .2);">
                            <td style="padding: .5rem; font-weight: 600;">{{ $row['user']->name }}</td>
                            <td style="padding: .5rem; font-family: var(--erp-mono);">{{ $row['week']['plate'] ?: '—' }}</td>
                            @foreach ($row['week']['days'] as $day)
                                <td style="padding: .5rem; text-align: right; font-family: var(--erp-mono);" title="{{ $day['status']['note'] ?? '' }}">{{ $dec($day['total']) }}@if ($day['status'])<span style="color: var(--erp-accent);">*</span>@endif</td>
                            @endforeach
                            <td style="padding: .5rem; text-align: right; font-family: var(--erp-mono); font-weight: 700;">{{ $dec($row['week']['hours']) }}</td>
                            <td style="padding: .5rem; text-align: right; font-family: var(--erp-mono);">{{ $dec($row['week']['overtime_now']) }}</td>
                            <td style="padding: .5rem; text-align: right; font-family: var(--erp-mono);">{{ $dec($row['week']['overtime_new']) }}</td>
                            <td style="padding: .5rem; text-align: right; font-family: var(--erp-mono);">{{ number_format($row['week']['km'], 0, ',', '.') }}</td>
                            <td style="padding: .5rem;">{{ $row['sent_at'] ? \Illuminate\Support\Carbon::parse($row['sent_at'])->format('d/m H:i') : '—' }}</td>
                            <td style="padding: .5rem; white-space: nowrap;">
                                <x-filament::link :href="\App\Filament\Pages\Timesheet::getUrl(['employee' => $row['user']->id, 'date' => $monday->toDateString()])" icon="heroicon-m-pencil-square">{{ __('erp.timesheets.open') }}</x-filament::link>
                                @if (\App\Support\PdfAccess::canGenerate(auth()->user(), 'timesheets'))
                                    &nbsp;
                                    <x-filament::link :href="route('timesheets.pdf', ['user' => $row['user']->id, 'week' => $monday->toDateString()])" target="_blank" icon="heroicon-m-document-arrow-down">PDF</x-filament::link>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="14" style="padding: 1rem; text-align: center; opacity: .7;">{{ __('erp.timesheets.nobody') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p style="margin-top: .5rem; font-size: .75rem; opacity: .7;">* {{ __('erp.timesheets.star_help') }}</p>
    </x-filament::section>
</x-filament-panels::page>
