{{--
    Planning: a week (one row per person), a month grid or a list. Filament ships precompiled CSS,
    so the page styles its own classes (prefixed erp-plan) and follows Filament's .dark class.
--}}
<x-filament-panels::page>
    <style>
        .erp-plan { --plan-border: rgb(148 163 184 / .3); --plan-muted: rgb(100 116 139); --plan-soft: rgb(148 163 184 / .08); --plan-out: rgb(148 163 184 / .04); --plan-holiday: color-mix(in srgb, var(--erp-accent) 10%, transparent); --plan-block: #64748b; }
        .dark .erp-plan { --plan-border: rgb(255 255 255 / .1); --plan-muted: rgb(148 163 184); --plan-soft: rgb(255 255 255 / .03); --plan-out: rgb(0 0 0 / .15); }
        .erp-plan-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .625rem .75rem; margin-bottom: 1rem; }
        .erp-plan-seg { display: inline-flex; border: 1px solid var(--plan-border); border-radius: .5rem; overflow: hidden; }
        .erp-plan-seg button { display: inline-flex; align-items: center; gap: .375rem; padding: .4rem .7rem; font-size: .8125rem; line-height: 1.25rem; background: transparent; cursor: pointer; }
        .erp-plan-seg button + button { border-left: 1px solid var(--plan-border); }
        .erp-plan-seg button:hover { background: var(--plan-soft); }
        .erp-plan-seg button.is-active { background: var(--erp-navy); color: #fff; }
        .dark .erp-plan-seg button.is-active { background: var(--erp-accent); }
        .erp-plan-seg svg { width: 1rem; height: 1rem; }
        .erp-plan-title { font-size: 1.125rem; font-weight: 800; letter-spacing: -.01em; text-transform: capitalize; }
        .erp-plan-filters { display: flex; flex-wrap: wrap; gap: .5rem; margin-left: auto; }
        .erp-plan-select { padding: .4rem 2rem .4rem .7rem; font-size: .8125rem; border: 1px solid var(--plan-border); border-radius: .5rem; background-color: transparent; color: inherit; max-width: 14rem; }
        .dark .erp-plan-select option { background: rgb(15 23 42); }
        .erp-plan-scroll { overflow-x: auto; border: 1px solid var(--plan-border); border-radius: .75rem; }
        .erp-plan-week { display: grid; grid-template-columns: minmax(9rem, 12rem) repeat(7, minmax(8.5rem, 1fr)); min-width: 70rem; }
        .erp-plan-month { display: grid; grid-template-columns: repeat(7, minmax(8rem, 1fr)); min-width: 56rem; }
        .erp-plan-head { padding: .5rem .625rem; font-size: .75rem; color: var(--plan-muted); border-bottom: 1px solid var(--plan-border); border-right: 1px solid var(--plan-border); }
        .erp-plan-head strong { display: block; font-family: var(--erp-mono); font-size: .6875rem; letter-spacing: .08em; text-transform: uppercase; font-weight: 600; }
        .erp-plan-head.is-today { box-shadow: inset 0 -3px 0 var(--erp-accent); color: inherit; }
        .erp-plan-head.is-holiday, .erp-plan-cell.is-holiday { background: var(--plan-holiday); }
        .erp-plan-person { padding: .5rem .625rem; font-size: .8125rem; font-weight: 600; border-bottom: 1px solid var(--plan-border); border-right: 1px solid var(--plan-border); display: flex; align-items: flex-start; gap: .5rem; position: sticky; left: 0; background: var(--erp-surface, white); z-index: 1; }
        .dark .erp-plan-person { background: rgb(24 24 27); }
        .erp-plan-dot { width: .625rem; height: .625rem; border-radius: 999px; flex-shrink: 0; margin-top: .3rem; }
        .erp-plan-cell { min-height: 4.75rem; padding: .3125rem; border-bottom: 1px solid var(--plan-border); border-right: 1px solid var(--plan-border); display: flex; flex-direction: column; gap: .25rem; position: relative; }
        .erp-plan-cell.is-weekend { background: var(--plan-soft); }
        .erp-plan-cell.is-out { background: var(--plan-out); }
        .erp-plan-cell.is-out .erp-plan-num { opacity: .4; }
        .erp-plan-month .erp-plan-cell { min-height: 7rem; }
        .erp-plan-month .erp-plan-cell:nth-child(7n), .erp-plan-month .erp-plan-head:nth-child(7n) { border-right: 0; }
        .erp-plan-num { font-family: var(--erp-mono); font-size: .75rem; font-weight: 600; width: 1.625rem; height: 1.625rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; }
        .erp-plan-cell.is-today .erp-plan-num { background: var(--erp-accent); color: #fff; }
        .erp-plan-holiday { font-size: .6875rem; font-weight: 700; color: color-mix(in srgb, var(--erp-accent) 80%, black); }
        .dark .erp-plan-holiday { color: var(--erp-accent); }
        .erp-plan-blocked { display: flex; align-items: center; gap: .25rem; padding: .25rem .375rem; border-radius: .375rem; font-size: .6875rem; font-weight: 600; color: #fff; background: repeating-linear-gradient(135deg, var(--plan-block), var(--plan-block) 6px, color-mix(in srgb, var(--plan-block) 82%, black) 6px, color-mix(in srgb, var(--plan-block) 82%, black) 12px); }
        .erp-plan-blocked.is-full { flex: 1; justify-content: center; min-height: 3.5rem; }
        .erp-plan-blocked svg { width: .75rem; height: .75rem; flex-shrink: 0; }
        .erp-plan-chip { --kind: #2a78d6; display: flex; flex-direction: column; gap: .0625rem; padding: .25rem .375rem .25rem .5rem; border-radius: .375rem; border-left: 3px solid var(--kind); background: color-mix(in srgb, var(--kind) 12%, transparent); font-size: .71875rem; line-height: 1.05rem; min-width: 0; }
        .dark .erp-plan-chip { background: color-mix(in srgb, var(--kind) 22%, transparent); }
        .erp-plan-chip.has-conflict { outline: 2px solid #dc2626; outline-offset: -1px; }
        .erp-plan-chip-top { display: flex; align-items: center; gap: .25rem; }
        .erp-plan-time { font-family: var(--erp-mono); font-size: .6875rem; color: var(--plan-muted); white-space: nowrap; }
        .erp-plan-topic { font-weight: 700; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; background: none; padding: 0; color: inherit; }
        .erp-plan-topic:hover { text-decoration: underline; }
        .erp-plan-sub { color: var(--plan-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .erp-plan-icons { margin-left: auto; display: inline-flex; align-items: center; gap: .125rem; flex-shrink: 0; }
        .erp-plan-icon { display: inline-flex; padding: .0625rem; border-radius: .25rem; color: var(--plan-muted); cursor: pointer; background: none; }
        .erp-plan-icon:hover { color: inherit; background: var(--plan-soft); }
        .erp-plan-icon svg { width: .8125rem; height: .8125rem; }
        .erp-plan-warn { color: #dc2626; display: inline-flex; }
        .erp-plan-warn svg { width: .8125rem; height: .8125rem; }
        .erp-plan-badge { font-family: var(--erp-mono); font-size: .625rem; color: var(--plan-muted); white-space: nowrap; }
        .erp-plan-add { display: none; align-self: flex-start; align-items: center; gap: .25rem; margin-top: auto; padding: .125rem .375rem; border-radius: .375rem; font-size: .6875rem; color: var(--plan-muted); border: 1px dashed var(--plan-border); background: transparent; cursor: pointer; }
        .erp-plan-cell:hover .erp-plan-add, .erp-plan-add:focus-visible { display: inline-flex; }
        .erp-plan-add:hover { color: inherit; border-color: currentColor; }
        .erp-plan-add svg { width: .75rem; height: .75rem; }
        @media (hover: none) { .erp-plan-add { display: inline-flex; } }
        .erp-plan-legend { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1.25rem; margin-top: 1rem; font-size: .8125rem; color: var(--plan-muted); }
        .erp-plan-legend span { display: inline-flex; align-items: center; gap: .375rem; }
        .erp-plan-swatch { display: inline-block; width: 1.5rem; height: .875rem; border-radius: .25rem; }
        .erp-plan-list { display: flex; flex-direction: column; gap: 1rem; }
        .erp-plan-day { border: 1px solid var(--plan-border); border-radius: .75rem; overflow: hidden; }
        .erp-plan-day h3 { display: flex; flex-wrap: wrap; align-items: baseline; gap: .5rem; padding: .5rem .875rem; font-size: .875rem; font-weight: 700; background: var(--plan-soft); border-bottom: 1px solid var(--plan-border); text-transform: capitalize; }
        .erp-plan-day h3 small { font-weight: 600; text-transform: none; }
        .erp-plan-table { width: 100%; font-size: .8125rem; border-collapse: collapse; }
        .erp-plan-table td { padding: .5rem .875rem; border-top: 1px solid var(--plan-border); vertical-align: top; }
        .erp-plan-table tr:first-child td { border-top: 0; }
        .erp-plan-table td.time { font-family: var(--erp-mono); white-space: nowrap; width: 7.5rem; }
        .erp-plan-kind { display: inline-flex; align-items: center; gap: .25rem; font-size: .75rem; font-weight: 600; white-space: nowrap; }
        .erp-plan-kind i { width: .625rem; height: .625rem; border-radius: 2px; }
        .erp-plan-blocked-line { display: flex; flex-wrap: wrap; gap: .375rem; padding: .5rem .875rem; border-top: 1px solid var(--plan-border); }
        .erp-plan-empty { padding: 2rem; text-align: center; color: var(--plan-muted); border: 1px dashed var(--plan-border); border-radius: .75rem; }
        @media (max-width: 640px) {
            .erp-plan-table td.who { display: none; }
            .erp-plan-filters { margin-left: 0; width: 100%; }
            .erp-plan-select { flex: 1; max-width: none; }
        }
    </style>

    <div class="erp-plan">
        <div class="erp-plan-toolbar">
            <div class="erp-plan-seg" role="group" aria-label="{{ __('erp.planning.layout') }}">
                <button type="button" wire:click="setDisplay('calendar')" @class(['is-active' => $display === 'calendar']) aria-pressed="{{ $display === 'calendar' ? 'true' : 'false' }}">
                    <x-filament::icon icon="heroicon-m-calendar-days" /> {{ __('erp.planning.calendar') }}
                </button>
                <button type="button" wire:click="setDisplay('list')" @class(['is-active' => $display === 'list']) aria-pressed="{{ $display === 'list' ? 'true' : 'false' }}">
                    <x-filament::icon icon="heroicon-m-list-bullet" /> {{ __('erp.planning.list') }}
                </button>
            </div>

            <div class="erp-plan-seg" role="group" aria-label="{{ __('erp.planning.period') }}">
                <button type="button" wire:click="setPeriod('week')" @class(['is-active' => $period === 'week']) aria-pressed="{{ $period === 'week' ? 'true' : 'false' }}">{{ __('erp.planning.period_week') }}</button>
                <button type="button" wire:click="setPeriod('month')" @class(['is-active' => $period === 'month']) aria-pressed="{{ $period === 'month' ? 'true' : 'false' }}">{{ __('erp.planning.period_month') }}</button>
            </div>

            <div class="erp-plan-seg">
                <button type="button" wire:click="previous" title="{{ __('erp.calendar.previous') }}" aria-label="{{ __('erp.calendar.previous') }}">&larr;</button>
                <button type="button" wire:click="today">{{ __('erp.calendar.today') }}</button>
                <button type="button" wire:click="next" title="{{ __('erp.calendar.next') }}" aria-label="{{ __('erp.calendar.next') }}">&rarr;</button>
            </div>

            <h2 class="erp-plan-title">{{ $heading }}</h2>

            <div wire:loading.delay style="font-size: .8125rem; color: var(--plan-muted);">{{ __('erp.calendar.loading') }}</div>

            <div class="erp-plan-filters">
                @if (count($teams) > 1)
                    <select wire:model.live="team" class="erp-plan-select" aria-label="{{ __('erp.resources.team.singular') }}">
                        <option value="">{{ __('erp.calendar.all_teams') }}</option>
                        @foreach ($teams as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                @endif
                @if (count($persons) > 1)
                    <select wire:model.live="person" class="erp-plan-select" aria-label="{{ __('erp.planning.employee') }}">
                        <option value="">{{ __('erp.planning.everyone') }}</option>
                        @foreach ($persons as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        </div>

        @if ($display === 'calendar' && $period === 'week')
            {{-- Week: one row per person, one column per day. --}}
            <div class="erp-plan-scroll">
                <div class="erp-plan-week" role="grid">
                    <div class="erp-plan-head" role="columnheader">{{ __('erp.planning.employee') }}</div>
                    @foreach ($days as $day)
                        @php($key = $day->toDateString())
                        <div @class(['erp-plan-head', 'is-today' => $day->isToday(), 'is-holiday' => $holidays->has($key)]) role="columnheader">
                            <strong>{{ $day->locale(app()->getLocale())->translatedFormat('D') }}</strong>
                            {{ $day->format('d/m') }}
                            @if ($holidays->has($key))
                                <div class="erp-plan-holiday">{{ $holidays->get($key) }}</div>
                            @endif
                        </div>
                    @endforeach

                    @forelse ($people as $person)
                        <div class="erp-plan-person" role="rowheader">
                            <i class="erp-plan-dot" style="background: {{ $colors[$person->id] }};"></i>
                            <span>{{ $person->name }}</span>
                        </div>
                        @foreach ($days as $day)
                            @php($key = $day->toDateString())
                            @php($reason = $blocked[$person->id][$key] ?? null)
                            <div @class(['erp-plan-cell', 'is-weekend' => $day->isWeekend(), 'is-holiday' => $holidays->has($key)]) role="gridcell" data-date="{{ $key }}" data-person="{{ $person->id }}">
                                @if ($reason)
                                    <div @class(['erp-plan-blocked', 'is-full' => ! isset($byPersonDay[$person->id.'|'.$key])]) title="{{ __('erp.planning.blocked_help') }}">
                                        <x-filament::icon icon="heroicon-m-no-symbol" /> {{ $reason }}
                                    </div>
                                @endif
                                @foreach ($byPersonDay[$person->id.'|'.$key] ?? [] as $entry)
                                    @include('filament.pages.planning-chip', ['entry' => $entry, 'showPerson' => false])
                                @endforeach
                                @if ($canPlan && ! $reason)
                                    <button type="button" class="erp-plan-add" wire:click="mountAction('createItem', @js(['user' => $person->id, 'date' => $key]))" aria-label="{{ __('erp.planning.add_for', ['name' => $person->name, 'date' => $day->format('d/m')]) }}">
                                        <x-filament::icon icon="heroicon-m-plus" /> {{ __('erp.planning.add_short') }}
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    @empty
                        <div style="grid-column: 1 / -1; padding: 1.5rem; text-align: center; color: var(--plan-muted);">{{ __('erp.planning.nobody') }}</div>
                    @endforelse
                </div>
            </div>
        @elseif ($display === 'calendar')
            {{-- Month: a grid of days with everyone's items. --}}
            <div class="erp-plan-scroll">
                <div class="erp-plan-month" role="grid">
                    @foreach ($days->take(7) as $day)
                        <div class="erp-plan-head" role="columnheader"><strong>{{ $day->locale(app()->getLocale())->translatedFormat('D') }}</strong></div>
                    @endforeach
                    @foreach ($days as $day)
                        @php($key = $day->toDateString())
                        <div @class([
                            'erp-plan-cell',
                            'is-out' => $day->month !== $from->month,
                            'is-weekend' => $day->isWeekend() && $day->month === $from->month,
                            'is-holiday' => $holidays->has($key),
                            'is-today' => $day->isToday(),
                        ]) role="gridcell" data-date="{{ $key }}">
                            <span class="erp-plan-num">{{ $day->day }}</span>
                            @if ($holidays->has($key))
                                <div class="erp-plan-holiday">{{ $holidays->get($key) }}</div>
                            @endif
                            @foreach ($people as $person)
                                @if ($reason = $blocked[$person->id][$key] ?? null)
                                    <div class="erp-plan-blocked" title="{{ __('erp.planning.blocked_help') }}">
                                        <x-filament::icon icon="heroicon-m-no-symbol" />
                                        <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $person->name }} · {{ $reason }}</span>
                                    </div>
                                @endif
                            @endforeach
                            @foreach ($byDay[$key] ?? [] as $entry)
                                @include('filament.pages.planning-chip', ['entry' => $entry, 'showPerson' => true])
                            @endforeach
                            @if ($canPlan)
                                <button type="button" class="erp-plan-add" wire:click="mountAction('createItem', @js(['date' => $key]))" aria-label="{{ __('erp.planning.add_on', ['date' => $day->format('d/m')]) }}">
                                    <x-filament::icon icon="heroicon-m-plus" /> {{ __('erp.planning.add_short') }}
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            {{-- List: per day, the items and who is unavailable. --}}
            @php($listDays = $days->filter(fn ($day) => isset($byDay[$day->toDateString()]) || collect($blocked)->contains(fn ($personDays) => isset($personDays[$day->toDateString()]))))
            <div class="erp-plan-list">
                @forelse ($listDays as $day)
                    @php($key = $day->toDateString())
                    <section class="erp-plan-day" aria-label="{{ $day->format('d/m/Y') }}">
                        <h3>
                            {{ $day->locale(app()->getLocale())->translatedFormat('l d F Y') }}
                            @if ($holidays->has($key))
                                <small class="erp-plan-holiday">{{ $holidays->get($key) }}</small>
                            @endif
                        </h3>
                        @if (isset($byDay[$key]))
                            <table class="erp-plan-table">
                                <tbody>
                                    @foreach ($byDay[$key] as $entry)
                                        @php($item = $entry['item'])
                                        <tr>
                                            <td class="time">{{ $item->timeRange() }}</td>
                                            <td class="who">
                                                <span style="display: inline-flex; align-items: center; gap: .375rem;"><i class="erp-plan-dot" style="margin: 0; background: {{ $colors[$item->user_id] ?? '#64748b' }};"></i>{{ $item->user->name }}</span>
                                            </td>
                                            <td>
                                                <span class="erp-plan-kind"><i style="background: {{ $entry['color'] }};"></i>{{ $item->kind->getLabel() }}</span>
                                            </td>
                                            <td style="width: 100%;">
                                                @if ($entry['url'])
                                                    <a href="{{ $entry['url'] }}" class="erp-plan-topic" style="white-space: normal;">{{ $item->topic() }}</a>
                                                @else
                                                    <button type="button" class="erp-plan-topic" style="white-space: normal;" wire:click="mountAction('viewItem', @js(['item' => $item->id]))">{{ $item->topic() }}</button>
                                                @endif
                                                @if ($item->subtitle())
                                                    <div class="erp-plan-sub" style="white-space: normal;">{{ $item->subtitle() }}</div>
                                                @endif
                                                @if (filled($item->description))
                                                    <div style="color: var(--plan-muted); white-space: pre-line;">{{ \Illuminate\Support\Str::limit($item->description, 200) }}</div>
                                                @endif
                                                @if ($tasks = $item->taskList())
                                                    <div class="erp-plan-badge">{{ __('erp.planning.tasks_done', ['done' => collect($tasks)->where('done', true)->count(), 'total' => count($tasks)]) }}</div>
                                                @endif
                                                @if ($entry['conflict'])
                                                    <div class="erp-plan-warn" style="font-size: .75rem; gap: .25rem; align-items: center;"><x-filament::icon icon="heroicon-m-exclamation-triangle" /> {{ __('erp.planning.conflict', ['reason' => $entry['conflict']]) }}</div>
                                                @endif
                                            </td>
                                            <td style="white-space: nowrap;">
                                                <button type="button" class="erp-plan-icon" wire:click="mountAction('viewItem', @js(['item' => $item->id]))" title="{{ __('erp.actions.view') }}" aria-label="{{ __('erp.actions.view') }}"><x-filament::icon icon="heroicon-m-eye" /></button>
                                                @if ($entry['canManage'])
                                                    <button type="button" class="erp-plan-icon" wire:click="mountAction('editItem', @js(['item' => $item->id]))" title="{{ __('erp.actions.edit') }}" aria-label="{{ __('erp.actions.edit') }}"><x-filament::icon icon="heroicon-m-pencil-square" /></button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                        @php($away = $people->filter(fn ($person) => isset($blocked[$person->id][$key])))
                        @if ($away->isNotEmpty())
                            <div class="erp-plan-blocked-line">
                                @foreach ($away as $person)
                                    <span class="erp-plan-blocked"><x-filament::icon icon="heroicon-m-no-symbol" /> {{ $person->name }} · {{ $blocked[$person->id][$key] }}</span>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @empty
                    <div class="erp-plan-empty">{{ __('erp.planning.empty') }}</div>
                @endforelse
            </div>
        @endif

        <div class="erp-plan-legend">
            @foreach ($kinds as $kind)
                <span><i class="erp-plan-swatch" style="background: color-mix(in srgb, {{ $kind->hex() }} 18%, transparent); border-left: 3px solid {{ $kind->hex() }};"></i>{{ $kind->getLabel() }}</span>
            @endforeach
            <span><i class="erp-plan-swatch erp-plan-blocked" style="padding: 0;"></i>{{ __('erp.planning.blocked_legend') }}</span>
            <span><span class="erp-plan-warn"><x-filament::icon icon="heroicon-m-exclamation-triangle" /></span>{{ __('erp.planning.conflict_legend') }}</span>
        </div>
    </div>
</x-filament-panels::page>
