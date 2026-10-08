{{--
    Grouped monthly bars: invoices, credit notes, incoming invoices. One y-axis in euro; colours
    are the validated categorical slots 1–3 (light and dark steps), with a legend and a table view.
--}}
@php
    $series = [
        'invoices' => __('erp.resources.invoice.plural'),
        'credit_notes' => __('erp.resources.credit_note.plural'),
        'expenses' => __('erp.finance.expenses_received'),
    ];
    $count = max(count($months), 1);
    $width = 760; $height = 260; $left = 64; $right = 8; $top = 12; $bottom = 28;
    $plotWidth = $width - $left - $right; $plotHeight = $height - $top - $bottom;
    // A round step for the grid lines.
    $magnitude = 10 ** floor(log10($max));
    $step = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($s) => $max / $s <= 5) ?? $magnitude * 10;
    $ceiling = ceil($max / $step) * $step;
    $groupWidth = $plotWidth / $count;
    $barWidth = max(2, min(18, ($groupWidth - 8) / 3 - 2));
    $y = fn ($value) => $top + $plotHeight - max(0, $value) / $ceiling * $plotHeight;
    $short = fn ($value) => abs($value) >= 1000 ? '€'.rtrim(rtrim(number_format($value / 1000, 1, ',', '.'), '0'), ',').'k' : '€'.number_format($value, 0, ',', '.');
@endphp
<div class="erp-fin-chart" data-finance-chart="{{ $id }}">
    <style>
        .erp-fin-chart { --s-invoices: #2a78d6; --s-credit_notes: #eb6834; --s-expenses: #1baf7a; --grid: rgb(148 163 184 / .25); --axis: rgb(100 116 139); }
        .dark .erp-fin-chart { --s-invoices: #3987e5; --s-credit_notes: #d95926; --s-expenses: #199e70; --grid: rgb(255 255 255 / .08); --axis: rgb(148 163 184); }
        .erp-fin-chart svg { width: 100%; height: auto; display: block; }
        .erp-fin-chart .bar { transition: opacity .12s; }
        .erp-fin-chart g.month:hover .bar { opacity: 1; }
        .erp-fin-chart svg:hover g.month:not(:hover) .bar { opacity: .45; }
        .erp-fin-chart g.month .hit { fill: transparent; }
        .erp-fin-chart g.month:hover .hit { fill: rgb(148 163 184 / .08); }
        .erp-fin-legend { display: flex; flex-wrap: wrap; gap: .25rem 1.25rem; margin-bottom: .5rem; font-size: .8125rem; }
        .erp-fin-legend span { display: inline-flex; align-items: center; gap: .4rem; }
        .erp-fin-legend i { width: .75rem; height: .75rem; border-radius: 3px; display: inline-block; }
    </style>
    <div class="erp-fin-legend">
        @foreach ($series as $key => $label)
            <span><i style="background: var(--s-{{ $key }});"></i>{{ $label }}</span>
        @endforeach
    </div>
    <svg viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ __('erp.finance.statistics.per_month') }}">
        @for ($tick = 0; $tick <= $ceiling + 0.001; $tick += $step)
            <line x1="{{ $left }}" x2="{{ $width - $right }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" stroke="var(--grid)" stroke-width="1" />
            <text x="{{ $left - 8 }}" y="{{ $y($tick) + 4 }}" text-anchor="end" font-size="11" fill="var(--axis)">{{ $short($tick) }}</text>
        @endfor

        @foreach (array_values($months) as $index => $row)
            @php($x0 = $left + $index * $groupWidth + ($groupWidth - (3 * $barWidth + 4)) / 2)
            <g class="month">
                <rect class="hit" x="{{ $left + $index * $groupWidth }}" y="{{ $top }}" width="{{ $groupWidth }}" height="{{ $plotHeight }}" />
                <title>{{ $row['month']->locale(app()->getLocale())->translatedFormat('F Y') }}&#10;@foreach ($series as $key => $label){{ $label }}: {{ \App\Models\Invoice::money($row[$key]) }}&#10;@endforeach</title>
                @foreach (array_keys($series) as $s => $key)
                    @php($value = $row[$key])
                    @php($x = $x0 + $s * ($barWidth + 2))
                    @php($barTop = $y($value))
                    @php($barHeight = $top + $plotHeight - $barTop)
                    @if ($barHeight >= 0.5)
                        @php($r = min(4, $barWidth / 2, $barHeight))
                        {{-- Rounded data end, square at the baseline. --}}
                        <path class="bar" fill="var(--s-{{ $key }})" d="M{{ $x }},{{ $top + $plotHeight }} V{{ $barTop + $r }} Q{{ $x }},{{ $barTop }} {{ $x + $r }},{{ $barTop }} H{{ $x + $barWidth - $r }} Q{{ $x + $barWidth }},{{ $barTop }} {{ $x + $barWidth }},{{ $barTop + $r }} V{{ $top + $plotHeight }} Z" />
                    @endif
                @endforeach
                <text x="{{ $left + ($index + 0.5) * $groupWidth }}" y="{{ $height - 8 }}" text-anchor="middle" font-size="11" fill="var(--axis)">{{ $row['month']->locale(app()->getLocale())->translatedFormat($count > 12 ? 'M y' : 'M') }}</text>
            </g>
        @endforeach
        <line x1="{{ $left }}" x2="{{ $width - $right }}" y1="{{ $top + $plotHeight }}" y2="{{ $top + $plotHeight }}" stroke="var(--axis)" stroke-width="1" />
    </svg>
</div>
