{{-- The interims' weekly sheets waiting for verification before they go to their interim office. --}}
@php($dec = fn ($value) => number_format((float) $value, 2, ',', ''))
@php($canPdf = \App\Support\PdfAccess::canGenerate(auth()->user(), 'timesheets'))
<x-filament-panels::page>
    <p style="font-size: .875rem; opacity: .8;">{{ __('erp.timesheets.interim.intro') }}</p>

    @forelse ($this->groups() as $group)
        <x-filament::section>
            <x-slot name="heading">{{ $group['office']->name }} · {{ __('erp.timesheets.week') }} {{ $group['monday']->isoWeek() }} ({{ $group['monday']->format('d/m/Y') }} – {{ $group['monday']->addDays(4)->format('d/m/Y') }})</x-slot>
            <x-slot name="description">{{ __('erp.interim_offices.contacts') }}: {{ implode(', ', array_keys($group['office']->contactAddresses())) ?: __('erp.interim_offices.no_contacts') }}</x-slot>
            <x-slot name="afterHeader">{{ ($this->verifyAction)(['office' => $group['office']->id, 'week' => $group['monday']->toDateString()]) }}</x-slot>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: .875rem;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 1px solid rgb(148 163 184 / .4);">
                            <th style="padding: .5rem;">{{ __('erp.timesheets.employee') }}</th>
                            @foreach (\App\Support\Timesheets::DAYS as $day)<th style="padding: .5rem; text-align: right; text-transform: capitalize;">{{ mb_substr($day, 0, 2) }}</th>@endforeach
                            <th style="padding: .5rem; text-align: right;">{{ __('erp.timesheets.hours') }}</th>
                            <th style="padding: .5rem; text-align: right;">{{ __('erp.timesheets.overtime_week') }}</th>
                            <th style="padding: .5rem; text-align: right;">Km</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['rows'] as $row)
                            <tr style="border-bottom: 1px solid rgb(148 163 184 / .2);">
                                <td style="padding: .5rem; font-weight: 600;">{{ $row['user']->name }}</td>
                                @foreach ($row['data']['days'] as $day)
                                    <td style="padding: .5rem; text-align: right; font-family: var(--erp-mono);" title="{{ $day['status']['note'] ?? '' }}">{{ $dec($day['total']) }}@if ($day['status'])<span style="color: var(--erp-accent);">*</span>@endif</td>
                                @endforeach
                                <td style="padding: .5rem; text-align: right; font-family: var(--erp-mono); font-weight: 700;">{{ $dec($row['data']['hours']) }}</td>
                                <td style="padding: .5rem; text-align: right; font-family: var(--erp-mono);">{{ $dec($row['data']['overtime_now']) }}</td>
                                <td style="padding: .5rem; text-align: right; font-family: var(--erp-mono);">{{ number_format($row['data']['km'], 0, ',', '.') }}</td>
                                <td style="padding: .5rem; white-space: nowrap;">
                                    <x-filament::link :href="\App\Filament\Pages\Timesheet::getUrl(['employee' => $row['user']->id, 'date' => $group['monday']->toDateString()])" icon="heroicon-m-pencil-square">{{ __('erp.timesheets.open') }}</x-filament::link>
                                    @if ($canPdf)
                                        &nbsp;
                                        <x-filament::link :href="route('timesheets.pdf', ['user' => $row['user']->id, 'week' => $group['monday']->toDateString()])" target="_blank" icon="heroicon-m-document-arrow-down">PDF</x-filament::link>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @empty
        <x-filament::section>
            <p style="text-align: center; opacity: .7;">{{ __('erp.timesheets.interim.nothing') }}</p>
        </x-filament::section>
    @endforelse

    @if (($sent = $this->recentlySent())->isNotEmpty())
        <x-filament::section :heading="__('erp.timesheets.interim.recently_sent')" collapsible collapsed>
            <table style="width: 100%; border-collapse: collapse; font-size: .875rem;">
                @foreach ($sent as $week)
                    <tr style="border-bottom: 1px solid rgb(148 163 184 / .2);">
                        <td style="padding: .4rem;">{{ $week->interim_sent_at->format('d/m/Y H:i') }}</td>
                        <td style="padding: .4rem;">{{ __('erp.timesheets.week') }} {{ $week->week_start->isoWeek() }}</td>
                        <td style="padding: .4rem; font-weight: 600;">{{ $week->user?->name }}</td>
                        <td style="padding: .4rem;">{{ $week->interimOffice?->name }}</td>
                        <td style="padding: .4rem; opacity: .8;">{{ __('erp.timesheets.interim.verified_by', ['name' => $week->interimVerifier?->name ?? '—']) }}</td>
                    </tr>
                @endforeach
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
