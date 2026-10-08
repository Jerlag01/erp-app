{{--
    Month grid of public holidays and vacation. Filament ships precompiled CSS, so the calendar
    styles its own classes here (prefixed erp-cal) and follows Filament's .dark class.
--}}
<x-filament-panels::page>
    <style>
        .erp-cal { --cal-border: rgb(148 163 184 / .3); --cal-muted: rgb(100 116 139); --cal-weekend: rgb(148 163 184 / .08); --cal-out: rgb(148 163 184 / .04); --cal-holiday: color-mix(in srgb, var(--erp-accent) 12%, transparent); }
        .dark .erp-cal { --cal-border: rgb(255 255 255 / .1); --cal-muted: rgb(148 163 184); --cal-weekend: rgb(255 255 255 / .03); --cal-out: rgb(0 0 0 / .15); }
        .erp-cal-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; margin-bottom: 1rem; }
        .erp-cal-nav { display: inline-flex; align-items: center; border: 1px solid var(--cal-border); border-radius: .5rem; overflow: hidden; }
        .erp-cal-nav button { padding: .4rem .7rem; font-size: .875rem; line-height: 1.25rem; background: transparent; cursor: pointer; }
        .erp-cal-nav button + button { border-left: 1px solid var(--cal-border); }
        .erp-cal-nav button:hover { background: var(--cal-weekend); }
        .erp-cal-title { font-size: 1.25rem; font-weight: 800; letter-spacing: -.01em; text-transform: capitalize; min-width: 11rem; }
        .erp-cal-select { margin-left: auto; padding: .4rem 2rem .4rem .7rem; font-size: .875rem; border: 1px solid var(--cal-border); border-radius: .5rem; background-color: transparent; color: inherit; }
        .dark .erp-cal-select option { background: rgb(15 23 42); }
        .erp-cal-scroll { overflow-x: auto; border: 1px solid var(--cal-border); border-radius: .75rem; }
        .erp-cal-grid { display: grid; grid-template-columns: repeat(7, minmax(6.5rem, 1fr)); min-width: 46rem; }
        .erp-cal-head { padding: .5rem .625rem; font-family: var(--erp-mono); font-size: .6875rem; letter-spacing: .08em; text-transform: uppercase; color: var(--cal-muted); border-bottom: 1px solid var(--cal-border); }
        .erp-cal-day { min-height: 7rem; padding: .375rem; border-right: 1px solid var(--cal-border); border-bottom: 1px solid var(--cal-border); display: flex; flex-direction: column; gap: .25rem; }
        .erp-cal-day:nth-child(7n) { border-right: 0; }
        .erp-cal-day.is-weekend { background: var(--cal-weekend); }
        .erp-cal-day.is-out { background: var(--cal-out); }
        .erp-cal-day.is-out .erp-cal-num { opacity: .4; }
        .erp-cal-day.is-holiday { background: var(--cal-holiday); }
        .erp-cal-num { font-family: var(--erp-mono); font-size: .75rem; font-weight: 600; width: 1.625rem; height: 1.625rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; }
        .erp-cal-day.is-today .erp-cal-num { background: var(--erp-accent); color: #fff; }
        .erp-cal-holiday { display: flex; align-items: center; gap: .25rem; font-size: .6875rem; font-weight: 700; color: color-mix(in srgb, var(--erp-accent) 80%, black); }
        .dark .erp-cal-holiday { color: var(--erp-accent); }
        .erp-cal-chip { display: flex; align-items: center; gap: .25rem; padding: .125rem .375rem; border-radius: .375rem; font-size: .6875rem; font-weight: 600; line-height: 1.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border: 1px solid transparent; }
        .erp-cal-chip.is-approved { color: #fff; }
        .erp-cal-chip.is-pending { background: transparent !important; border-style: dashed; }
        .erp-cal-chip.is-mine { box-shadow: inset 0 0 0 1px rgb(255 255 255 / .55); }
        .erp-cal-chip svg { width: .75rem; height: .75rem; flex-shrink: 0; }
        .erp-cal-absence { color: #fff; background: repeating-linear-gradient(135deg, var(--absence), var(--absence) 6px, color-mix(in srgb, var(--absence) 82%, black) 6px, color-mix(in srgb, var(--absence) 82%, black) 12px); }
        .erp-cal-legend { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1.25rem; margin-top: 1rem; font-size: .8125rem; color: var(--cal-muted); }
        .erp-cal-legend span { display: inline-flex; align-items: center; gap: .375rem; }
        .erp-cal-swatch { display: inline-block; width: 1.75rem; height: .875rem; border-radius: .25rem; border: 1px solid transparent; }
        .erp-cal-people { display: flex; flex-wrap: wrap; gap: .375rem .875rem; margin-top: .5rem; font-size: .8125rem; }
        .erp-cal-dot { display: inline-block; width: .625rem; height: .625rem; border-radius: 999px; margin-right: .375rem; }
    </style>

    <div class="erp-cal">
        <div class="erp-cal-toolbar">
            <div class="erp-cal-nav">
                <button type="button" wire:click="previousMonth" title="{{ __('erp.calendar.previous') }}" aria-label="{{ __('erp.calendar.previous') }}">&larr;</button>
                <button type="button" wire:click="thisMonth">{{ __('erp.calendar.today') }}</button>
                <button type="button" wire:click="nextMonth" title="{{ __('erp.calendar.next') }}" aria-label="{{ __('erp.calendar.next') }}">&rarr;</button>
            </div>

            <h2 class="erp-cal-title">{{ $monthStart->locale(app()->getLocale())->translatedFormat('F Y') }}</h2>

            <div wire:loading.delay style="font-size: .8125rem; color: var(--cal-muted);">{{ __('erp.calendar.loading') }}</div>

            @if (count($teams) > 1)
                <select wire:model.live="team" class="erp-cal-select" aria-label="{{ __('erp.resources.team.singular') }}">
                    <option value="">{{ __('erp.calendar.all_teams') }}</option>
                    @foreach ($teams as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="erp-cal-scroll">
            <div class="erp-cal-grid" role="grid">
                @foreach ($dayNames as $dayName)
                    <div class="erp-cal-head" role="columnheader">{{ $dayName }}</div>
                @endforeach

                @foreach ($weeks as $week)
                    @foreach ($week as $day)
                        <div @class([
                            'erp-cal-day',
                            'is-out' => ! $day['in_month'],
                            'is-weekend' => $day['is_weekend'] && $day['in_month'],
                            'is-holiday' => $day['holiday'] !== null,
                            'is-today' => $day['is_today'],
                        ]) role="gridcell" data-date="{{ $day['date']->toDateString() }}">
                            <span class="erp-cal-num">{{ $day['date']->day }}</span>

                            @if ($day['holiday'])
                                <div class="erp-cal-holiday" title="{{ __('erp.resources.holiday.singular') }}">
                                    <x-filament::icon icon="heroicon-m-flag" style="width: .75rem; height: .75rem;" />
                                    <span>{{ $day['holiday'] }}</span>
                                </div>
                            @endif

                            @foreach ($day['entries'] as $entry)
                                @php($request = $entry['request'])
                                <div @class(['erp-cal-chip', 'is-pending' => $entry['pending'], 'is-approved' => ! $entry['pending'], 'is-mine' => $entry['mine']])
                                    style="{{ $entry['pending'] ? "border-color: {$entry['color']}; color: {$entry['color']};" : "background: {$entry['color']};" }}"
                                    title="{{ $request->user->name }} · {{ $request->start_date->format('d/m') }} → {{ $request->end_date->format('d/m') }} · {{ $request->status->getLabel() }}">
                                    @if ($entry['pending'])
                                        <x-filament::icon icon="heroicon-m-clock" />
                                    @endif
                                    <span style="overflow: hidden; text-overflow: ellipsis;">{{ $request->user->name }}</span>
                                    @if ($entry['half_day'])
                                        <span>½</span>
                                    @endif
                                </div>
                            @endforeach

                            {{-- Absences entered by an administrator: striped, in the colour of the type. --}}
                            @foreach ($day['absences'] as $entry)
                                @php($absence = $entry['absence'])
                                <div @class(['erp-cal-chip', 'erp-cal-absence', 'is-mine' => $entry['mine']])
                                    style="--absence: {{ $entry['color'] }};"
                                    data-absence="{{ $absence->id }}"
                                    title="{{ $absence->user->name }} · {{ $entry['label'] }} · {{ $absence->start_date->format('d/m') }} → {{ $absence->end_date->format('d/m') }}">
                                    @if ($entry['icon'])
                                        <x-filament::icon :icon="$entry['icon']" />
                                    @else
                                        <x-filament::icon icon="heroicon-m-minus-circle" />
                                    @endif
                                    <span style="overflow: hidden; text-overflow: ellipsis;">{{ $absence->user->name }}</span>
                                    @if ($entry['half_day'])
                                        <span>½</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        <div class="erp-cal-legend">
            <span><i class="erp-cal-swatch" style="background: var(--erp-navy);"></i>{{ __('erp.enums.vacation_status.approved') }}</span>
            <span><i class="erp-cal-swatch" style="border: 1px dashed var(--erp-navy);"></i>{{ __('erp.calendar.pending_legend') }}</span>
            <span><i class="erp-cal-swatch" style="background: var(--cal-holiday); border-color: var(--erp-accent);"></i>{{ __('erp.resources.holiday.singular') }}</span>
            @foreach ($absenceLegend as $item)
                <span><i class="erp-cal-swatch erp-cal-absence" style="--absence: {{ $item['color'] }};"></i>{{ $item['label'] }}</span>
            @endforeach
            <span><i class="erp-cal-swatch" style="background: var(--cal-weekend); border-color: var(--cal-border);"></i>{{ __('erp.calendar.weekend') }}</span>
        </div>

        @if ($people)
            <div class="erp-cal-people">
                @foreach ($people as $person)
                    <span><i class="erp-cal-dot" style="background: {{ $person['color'] }};"></i>{{ $person['name'] }}</span>
                @endforeach
            </div>
        @else
            <p style="margin-top: .5rem; font-size: .8125rem; color: var(--cal-muted);">{{ __('erp.calendar.nobody') }}</p>
        @endif
    </div>
</x-filament-panels::page>
