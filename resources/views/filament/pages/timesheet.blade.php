{{--
    Prestation sheet, one day at a time, laid out like the Excel "Prestatiefiche": the header
    (name, plate, week) and the week summary at the bottom come from the system.
--}}
@php
    $dec = fn ($value) => $value === null ? '' : number_format((float) $value, 2, ',', '');
    $current = $this->day()->toDateString();
@endphp
<x-filament-panels::page>
    <style>
        .erp-ts { --ts-border: rgb(148 163 184 / .35); --ts-muted: rgb(100 116 139); --ts-soft: rgb(148 163 184 / .08); }
        .dark .erp-ts { --ts-border: rgb(255 255 255 / .12); --ts-muted: rgb(148 163 184); --ts-soft: rgb(255 255 255 / .04); }
        .erp-ts-head { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: .75rem; padding: .875rem 1rem; border: 1px solid var(--ts-border); border-radius: .75rem; }
        .erp-ts-head dt { font-size: .6875rem; text-transform: uppercase; letter-spacing: .06em; color: var(--ts-muted); }
        .erp-ts-head dd { font-weight: 700; font-size: 1rem; }
        .erp-ts-head dd.big { font-size: 1.25rem; }
        .erp-ts-bar { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin: 1rem 0; }
        .erp-ts-nav { display: inline-flex; border: 1px solid var(--ts-border); border-radius: .5rem; overflow: hidden; }
        .erp-ts-nav button { padding: .4rem .7rem; font-size: .875rem; }
        .erp-ts-nav button + button { border-left: 1px solid var(--ts-border); }
        .erp-ts-days { display: grid; grid-template-columns: repeat(5, minmax(6.5rem, 1fr)); gap: .5rem; flex: 1; min-width: 32rem; }
        .erp-ts-day { text-align: left; padding: .5rem .625rem; border: 1px solid var(--ts-border); border-radius: .5rem; font-size: .8125rem; line-height: 1.25rem; }
        .erp-ts-day:hover { background: var(--ts-soft); }
        .erp-ts-day.is-current { border-color: var(--erp-accent); box-shadow: inset 0 0 0 1px var(--erp-accent); }
        .erp-ts-day strong { display: block; text-transform: capitalize; }
        .erp-ts-day .h { font-family: var(--erp-mono); font-weight: 700; }
        .erp-ts-day .note { display: block; color: var(--ts-muted); font-size: .75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .erp-ts-status { padding: .625rem .875rem; border-radius: .5rem; background: color-mix(in srgb, var(--erp-accent) 12%, transparent); border: 1px solid color-mix(in srgb, var(--erp-accent) 40%, transparent); font-size: .875rem; margin-bottom: .75rem; }
        .erp-ts-totals { display: flex; flex-wrap: wrap; gap: 1.5rem; margin: .75rem 0; font-size: .875rem; }
        .erp-ts-totals b { font-family: var(--erp-mono); font-size: 1rem; }
        .erp-ts-rows { width: 100%; font-size: .75rem; color: var(--ts-muted); margin-top: .25rem; }
        .erp-ts-week { width: 100%; border-collapse: collapse; font-size: .8125rem; }
        .erp-ts-week th, .erp-ts-week td { border: 1px solid var(--ts-border); padding: .375rem .5rem; text-align: center; }
        .erp-ts-week th { background: var(--ts-soft); font-weight: 600; }
        .erp-ts-week td.l { text-align: left; }
        .erp-ts-week .mono { font-family: var(--erp-mono); }
        .erp-ts-sum { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: 1rem; margin-top: 1rem; }
        .erp-ts-sum table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .erp-ts-sum td { border: 1px solid var(--ts-border); padding: .375rem .625rem; }
        .erp-ts-sum td.n { text-align: right; font-family: var(--erp-mono); font-weight: 600; }
        .erp-ts-sent { padding: .5rem .875rem; border-radius: .5rem; background: rgb(22 163 74 / .12); font-size: .875rem; margin-bottom: .75rem; }
    </style>

    <div class="erp-ts">
        <dl class="erp-ts-head">
            <div><dt>{{ __('erp.timesheets.name') }}</dt><dd class="big">{{ $employeeName }}</dd></div>
            <div><dt>{{ __('erp.timesheets.plate') }}</dt><dd>{{ $week['plate'] ?: '—' }}</dd></div>
            <div><dt>{{ __('erp.timesheets.week_from') }}</dt><dd>{{ $week['monday']->format('d/m/y') }}</dd></div>
            <div><dt>{{ __('erp.timesheets.week_until') }}</dt><dd>{{ $week['friday']->format('d/m/y') }}</dd></div>
            <div><dt>{{ __('erp.timesheets.week') }}</dt><dd class="big">{{ $week['number'] }}</dd></div>
        </dl>

        <div class="erp-ts-bar">
            <div class="erp-ts-nav">
                <button type="button" wire:click="previousWeek" aria-label="{{ __('erp.calendar.previous') }}">&larr;</button>
                <button type="button" wire:click="goTo('{{ today()->toDateString() }}')">{{ __('erp.calendar.today') }}</button>
                <button type="button" wire:click="nextWeek" aria-label="{{ __('erp.calendar.next') }}">&rarr;</button>
            </div>
            <div class="erp-ts-days" role="tablist">
                @foreach ($week['days'] as $index => $day)
                    <button type="button" role="tab" aria-selected="{{ $day['date']->toDateString() === $current ? 'true' : 'false' }}" wire:click="goTo('{{ $day['date']->toDateString() }}')" @class(['erp-ts-day', 'is-current' => $day['date']->toDateString() === $current])>
                        <strong>{{ \App\Support\Timesheets::DAYS[$index] }} {{ $day['date']->format('d/m') }}</strong>
                        <span class="h">{{ $dec($day['total']) }} u</span> · {{ $day['km'] }} km
                        @if ($day['status'])<span class="note">{{ $day['status']['note'] }}</span>@endif
                    </button>
                @endforeach
            </div>
            @if (count($employees))
                <select wire:model.live="employee" class="fi-select-input" style="max-width: 16rem; padding: .4rem .7rem; border: 1px solid var(--ts-border); border-radius: .5rem; background: transparent;" aria-label="{{ __('erp.timesheets.employee') }}">
                    <option value="">{{ auth()->user()->name }}</option>
                    @foreach ($employees as $id => $name)
                        @if ($id !== auth()->id())<option value="{{ $id }}">{{ $name }}</option>@endif
                    @endforeach
                </select>
            @endif
        </div>

        @if ($sent)
            <div class="erp-ts-sent">{{ __('erp.timesheets.week_sent', ['date' => $week['record']->sent_at->format('d/m/Y H:i')]) }}@if (! $canEdit) {{ __('erp.timesheets.week_locked') }}@endif</div>
        @endif

        <x-filament::section>
            <x-slot name="heading">
                <span style="text-transform: capitalize;">{{ $this->day()->locale('nl')->translatedFormat('l d/m/Y') }}</span>
            </x-slot>

            @if ($status)
                <div class="erp-ts-status">
                    <strong>{{ $status['note'] }}</strong> —
                    {{ $status['credit'] > 0 ? __('erp.timesheets.credited', ['hours' => $dec($status['credit'])]) : __('erp.timesheets.not_credited') }}
                </div>
            @endif

            <form wire:submit="save">
                {{ $this->form }}

                <div class="erp-ts-totals">
                    <span>{{ __('erp.timesheets.day_hours') }}: <b>{{ $dec($dayTotal) }}</b> u
                        @if ($status && $status['credit'] > 0)<span style="color: var(--ts-muted);">({{ $dec($dayWorked) }} {{ __('erp.timesheets.worked') }} + {{ $dec($status['credit']) }})</span>@endif
                    </span>
                    <span>{{ __('erp.timesheets.day_km') }}: <b>{{ $dayKm }}</b> km</span>
                    <span style="color: var(--ts-muted);">{{ __('erp.timesheets.counted_help') }}</span>
                </div>

                @if ($canEdit)
                    <x-filament::button type="submit" icon="heroicon-m-check">{{ __('erp.actions.save') }}</x-filament::button>
                @endif
            </form>
        </x-filament::section>

        <x-filament::section style="margin-top: 1rem;">
            <x-slot name="heading">{{ __('erp.timesheets.week_summary', ['week' => $week['number']]) }}</x-slot>

            <div style="overflow-x: auto;">
                <table class="erp-ts-week">
                    <thead>
                        <tr>
                            <th>{{ __('erp.timesheets.date') }}</th>
                            <th>{{ __('erp.timesheets.km') }}</th>
                            <th>{{ __('erp.timesheets.lunch_break') }}</th>
                            <th>{{ __('erp.timesheets.hours') }}</th>
                            <th class="l">{{ __('erp.timesheets.imputation') }} / {{ __('erp.timesheets.description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($week['days'] as $index => $day)
                            <tr>
                                <td class="l" style="text-transform: capitalize;">{{ \App\Support\Timesheets::DAYS[$index] }} {{ $day['date']->format('d/m') }}</td>
                                <td class="mono">{{ $day['km'] }}</td>
                                <td>{{ $day['lunch'] ? 'Ja' : 'Neen' }}</td>
                                <td class="mono"><strong>{{ $dec($day['total']) }}</strong></td>
                                <td class="l">
                                    {{ collect($day['rows'])->filter(fn ($row) => filled($row['imputation']) || filled($row['description']))->map(fn ($row) => trim(($row['imputation'] ?? '').' '.($row['description'] ?? '')))->implode(' · ') ?: ($day['status']['note'] ?? '—') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="erp-ts-sum">
                <table>
                    <tr><td colspan="2"><strong>Kilometers</strong></td></tr>
                    <tr><td>Report</td><td class="n">{{ number_format($week['km_carried'], 0, ',', '.') }}</td></tr>
                    <tr><td>Nu</td><td class="n">{{ number_format($week['km'], 0, ',', '.') }}</td></tr>
                    <tr><td>Nieuw</td><td class="n">{{ number_format($week['km_new'], 0, ',', '.') }}</td></tr>
                </table>
                <table>
                    <tr><td colspan="2"><strong>Meeruren</strong> <span style="color: var(--ts-muted);">({{ __('erp.timesheets.expected', ['hours' => $dec($week['expected'])]) }})</span></td></tr>
                    <tr><td>Report</td><td class="n">{{ $dec($week['overtime_carried']) }}</td></tr>
                    <tr><td>Nu ({{ $dec($week['hours']) }} − {{ $dec($week['expected']) }})</td><td class="n">{{ $dec($week['overtime_now']) }}</td></tr>
                    <tr><td>Nieuw</td><td class="n">{{ $dec($week['overtime_new']) }}</td></tr>
                </table>
                <div>
                    <strong>Opmerking:</strong>
                    <ul style="margin-top: .25rem; font-size: .8125rem; list-style: disc; padding-left: 1.25rem;">
                        @forelse ($week['notes'] as $note)
                            <li>{{ $note }}</li>
                        @empty
                            <li style="list-style: none; margin-left: -1.25rem; color: var(--ts-muted);">—</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
