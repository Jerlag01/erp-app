{{-- A self-built dashboard block (see CustomDashboardWidget): counter, follow-up list or status overview. --}}
@php
    use App\Support\Dashboard\DashboardWidgetData;
    $widget = $this->record();
    $data = $this->data();
    $n = fn ($value) => DashboardWidgetData::number($value);
    $pct = fn ($value) => $value === null ? '—' : DashboardWidgetData::number($value).' %';
    $colors = ['primary' => 'var(--primary-600)', 'success' => 'var(--success-600)', 'warning' => 'var(--warning-600)', 'danger' => 'var(--danger-600)', 'info' => 'var(--info-600)', 'gray' => 'var(--gray-500)'];
    $color = $colors[$widget?->setting('color', 'primary')] ?? $colors['primary'];
@endphp
<x-filament-widgets::widget>
    <x-filament::section compact>
        <x-slot name="heading">
            <span data-dashboard-widget="{{ $widget?->id }}">{{ $widget?->title ?? __('erp.dashboard.builder.missing') }}</span>
        </x-slot>
        <x-slot name="afterHeader">
            <div style="display: flex; gap: .25rem; align-items: center;">
                @if (! empty($data['url']))
                    <x-filament::icon-button :href="$data['url']" tag="a" icon="heroicon-o-arrow-top-right-on-square" color="gray" size="sm" :label="$data['source'] ?? ''" />
                @endif
                {{ $this->editAction }}
                {{ $this->deleteAction }}
            </div>
        </x-slot>

        @if (isset($data['error']))
            <p style="font-size: .875rem; opacity: .7;">{{ __('erp.dashboard.builder.errors.'.$data['error']) }}</p>
        @elseif ($widget->type === \App\Models\DashboardWidget::COUNTER)
            <div style="display: flex; align-items: baseline; gap: .75rem; flex-wrap: wrap;">
                <span style="font-size: 2rem; font-weight: 700; line-height: 1.1; color: {{ $color }}; font-variant-numeric: tabular-nums;" data-counter-value>
                    {{ $data['display'] === 'percentage' ? $pct($data['percentage']) : $n($data['value']) }}
                </span>
                @if ($data['display'] === 'both')
                    <span style="font-size: 1.1rem; font-weight: 600; opacity: .8;">{{ $pct($data['percentage']) }}</span>
                @endif
            </div>
            @if ($data['base'] !== null)
                <div style="font-size: .8rem; opacity: .7; margin-top: .25rem;">{{ __('erp.dashboard.builder.of_total', ['value' => $n($data['value']), 'total' => $n($data['base'])]) }}</div>
                <div style="height: .35rem; border-radius: 999px; background: rgb(148 163 184 / .25); margin-top: .4rem; overflow: hidden;">
                    <div style="height: 100%; width: {{ min(100, max(0, (float) $data['percentage'])) }}%; background: {{ $color }};"></div>
                </div>
            @endif
            <div style="font-size: .75rem; opacity: .6; margin-top: .4rem;">{{ $data['source'] }}</div>
        @elseif ($widget->type === \App\Models\DashboardWidget::LIST)
            @if ($data['rows'] === [])
                <p style="font-size: .875rem; opacity: .7;">{{ __('erp.dashboard.builder.empty') }}</p>
            @else
                <div style="overflow-x: auto;">
                    <table style="width: 100%; font-size: .85rem; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; opacity: .7;">
                                <th style="padding: .3rem .4rem .3rem 0; font-weight: 600;">{{ $data['source'] }}</th>
                                @foreach ($data['columns'] as $label)
                                    <th style="padding: .3rem .4rem; font-weight: 600;">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['rows'] as $row)
                                <tr style="border-top: 1px solid rgb(148 163 184 / .2);">
                                    <td style="padding: .35rem .4rem .35rem 0; font-weight: 600;">
                                        @if ($row['url'])
                                            <a href="{{ $row['url'] }}" style="color: var(--primary-600);">{{ $row['title'] }}</a>
                                        @else
                                            {{ $row['title'] }}
                                        @endif
                                    </td>
                                    @foreach ($row['values'] as $value)
                                        <td style="padding: .35rem .4rem;">{{ $value }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($data['total'] > count($data['rows']))
                    <div style="font-size: .75rem; opacity: .65; margin-top: .4rem;">{{ __('erp.dashboard.builder.more', ['shown' => count($data['rows']), 'total' => $data['total']]) }}</div>
                @endif
            @endif
        @else
            @if ($data['groups'] === [])
                <p style="font-size: .875rem; opacity: .7;">{{ __('erp.dashboard.builder.empty') }}</p>
            @else
                <div style="display: grid; gap: .45rem;">
                    @foreach ($data['groups'] as $group)
                        <div data-status-group>
                            <div style="display: flex; justify-content: space-between; gap: .5rem; font-size: .85rem;">
                                <span>{{ $group['label'] }}</span>
                                <span style="font-weight: 600; font-variant-numeric: tabular-nums;">
                                    @if ($data['display'] === 'percentage')
                                        {{ $pct($group['percentage']) }}
                                    @elseif ($data['display'] === 'both')
                                        {{ $n($group['value']) }} <span style="opacity: .65; font-weight: 500;">· {{ $pct($group['percentage']) }}</span>
                                    @else
                                        {{ $n($group['value']) }}
                                    @endif
                                </span>
                            </div>
                            <div style="height: .35rem; border-radius: 999px; background: rgb(148 163 184 / .25); margin-top: .2rem; overflow: hidden;">
                                <div style="height: 100%; width: {{ min(100, max(0, $group['percentage'])) }}%; background: var(--primary-500);"></div>
                            </div>
                        </div>
                    @endforeach
                    <div style="font-size: .75rem; opacity: .65;">{{ __('erp.dashboard.builder.total', ['total' => $n($data['total'])]) }} · {{ $data['source'] }}</div>
                </div>
            @endif
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
