{{--
    The waste registry, or the battery list of one region, as PDF. Battery entries show their lines
    (destruction certificate, COW code, PO number, project); a regional list has a row per line of that
    region with its own weight, also when several lines share an entry and reference.
    Long lists are printed as consecutive tables of CHUNK rows: dompdf keeps the layout of one table in
    memory at once, so a single table of a thousand rows needed well over 1 GB.
--}}
@php
    $kg = fn ($value) => \App\Models\WasteEntry::formatKg($value);
    $lines = fn (array $values) => implode('<br>', array_map('e', $values));
    $chunk = 100;
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('waste.pdf-style')
</head>
<body>
    <div class="footer">{{ settings()->siteName() }} · {{ __('erp.incidents.pdf_generated', ['date' => now()->format('d/m/Y H:i')]) }}</div>

    <div class="header">
        <div class="site">{{ settings()->siteName() }} · {{ __('erp.waste.title') }}</div>
        <h1>{{ $title }}</h1>
        <div class="period">{{ __('erp.waste.period') }}: {{ $period }} · {{ trans_choice('erp.waste.entry_count', $region ? $rows->count() : $entries->count(), ['count' => $region ? $rows->count() : $entries->count()]) }}</div>
    </div>

    @if ($region)
        @php($regionHead = '<thead><tr><th>'.e(__('erp.fields.date')).'</th><th>'.e(__('erp.waste.processor_reference')).'</th><th>'.e(__('erp.waste.project_reference')).'</th><th>'.e(__('erp.fields.cow_code')).'</th><th>'.e(__('erp.waste.destruction_certificate_short')).'</th><th>'.e(__('erp.waste.po_number')).'</th><th class="num">'.e(__('erp.waste.weight')).'</th></tr></thead>')
        @forelse ($rows->chunk($chunk) as $part)
            <table>
                {!! $regionHead !!}
                <tbody>
                    @foreach ($part->values() as $index => $row)
                        <tr @class(['even' => $index % 2 === 1])>
                            <td>{{ $row['entry']->date->format('d/m/Y') }}</td>
                            <td class="mono">{{ $row['entry']->processor_reference }}</td>
                            <td class="mono">{{ $row['line']->project_reference }}</td>
                            <td class="mono">{{ $row['line']->cow_code }}</td>
                            <td class="mono">{{ $row['line']->destruction_certificate }}</td>
                            <td class="mono">{{ $row['line']->po_number }}</td>
                            <td class="num mono">{{ $kg($row['line']->weight_kg) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @empty
            <table>{!! $regionHead !!}<tbody><tr><td colspan="7" class="muted">{{ __('erp.waste.no_entries') }}</td></tr></tbody></table>
        @endforelse
        <table>
            <tbody>
                <tr class="total">
                    <td>{{ __('erp.waste.total') }}</td>
                    <td class="num mono">{{ $kg($rows->sum(fn ($row) => (float) $row['line']->weight_kg)) }}</td>
                </tr>
            </tbody>
        </table>
    @else
        @php($head = '<thead><tr><th style="width: 7%">'.e(__('erp.fields.date')).'</th><th style="width: 14%">'.e(__('erp.waste.type')).'</th><th style="width: 7%">'.e(__('erp.waste.waste_code')).'</th><th class="num" style="width: 7%">'.e(__('erp.waste.weight')).'</th><th style="width: 10%">'.e(__('erp.resources.waste_processor.singular')).'</th><th style="width: 9%">'.e(__('erp.waste.processor_reference')).'</th><th style="width: 7%">'.e(__('erp.waste.region')).'</th><th style="width: 10%">'.e(__('erp.waste.destruction_certificate_short')).'</th><th style="width: 8%">'.e(__('erp.fields.cow_code')).'</th><th style="width: 10%">'.e(__('erp.waste.po_number')).'</th><th style="width: 11%">'.e(__('erp.waste.project_reference')).'</th></tr></thead>')
        @forelse ($entries->chunk($chunk) as $part)
            <table>
                {!! $head !!}
                <tbody>
                    @foreach ($part->values() as $index => $entry)
                        @php($split = $entry->lines->count() > 1)
                        <tr @class(['even' => $index % 2 === 1])>
                            <td>{{ $entry->date->format('d/m/Y') }}</td>
                            <td>{{ $entry->category->name }}@if ($entry->category->parent)<br><span class="muted">{{ $entry->category->parent->name }}</span>@endif</td>
                            <td class="mono {{ $entry->category->isHazardous() ? 'hazard' : '' }}">{{ $entry->category->waste_code }}</td>
                            <td class="num mono">{{ $kg($entry->weight_kg) }}@if ($split)<br><span class="muted">{!! $lines($entry->lines->map(fn ($line) => $kg($line->weight_kg))->all()) !!}</span>@endif</td>
                            <td>{{ $entry->processor->name }}</td>
                            <td>{{ $entry->processor_reference }}</td>
                            <td>{!! $lines($entry->lines->map(fn ($line) => $line->region?->getLabel())->filter()->all()) !!}</td>
                            <td class="mono">{!! $lines($entry->lines->pluck('destruction_certificate')->filter()->all()) !!}</td>
                            <td class="mono">{!! $lines($entry->lines->map(fn ($line) => $line->cow_code ?? '—')->all()) !!}</td>
                            <td class="mono">{!! $lines($entry->lines->map(fn ($line) => $line->po_number ?? '—')->all()) !!}</td>
                            <td class="mono">{!! $lines($entry->lines->map(fn ($line) => $line->project_reference ?? '—')->all()) !!}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @empty
            <table>{!! $head !!}<tbody><tr><td colspan="11" class="muted">{{ __('erp.waste.no_entries') }}</td></tr></tbody></table>
        @endforelse
        <table>
            <tbody>
                <tr class="total">
                    <td>{{ __('erp.waste.total') }}</td>
                    <td class="num mono">{{ $kg($entries->sum('weight_kg')) }}</td>
                </tr>
            </tbody>
        </table>
    @endif
</body>
</html>
