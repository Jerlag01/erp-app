{{--
    The waste registry, or the battery list of one region, as PDF. Battery entries show their lines
    (destruction certificate, COW code, PO number); a regional list has a row per line of that region.
--}}
@php
    $kg = fn ($value) => \App\Models\WasteEntry::formatKg($value);
    $lines = fn (array $values) => implode('<br>', array_map('e', $values));
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
        <table>
            <thead>
                <tr>
                    <th>{{ __('erp.fields.date') }}</th>
                    <th class="num">{{ __('erp.waste.weight') }}</th>
                    <th>{{ __('erp.waste.destruction_certificate_short') }}</th>
                    <th>{{ __('erp.fields.cow_code') }}</th>
                    <th>{{ __('erp.waste.po_number') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['entry']->date->format('d/m/Y') }}</td>
                        <td class="num mono">{{ $kg($row['line']->weight_kg) }}</td>
                        <td class="mono">{{ $row['line']->destruction_certificate }}</td>
                        <td class="mono">{{ $row['line']->cow_code }}</td>
                        <td class="mono">{{ $row['line']->po_number }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('erp.waste.no_entries') }}</td></tr>
                @endforelse
                <tr class="total">
                    <td>{{ __('erp.waste.total') }}</td>
                    <td class="num mono">{{ $kg($rows->sum(fn ($row) => (float) $row['line']->weight_kg)) }}</td>
                    <td colspan="3"></td>
                </tr>
            </tbody>
        </table>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ __('erp.fields.date') }}</th>
                    <th>{{ __('erp.waste.type') }}</th>
                    <th>{{ __('erp.waste.waste_code') }}</th>
                    <th class="num">{{ __('erp.waste.weight') }}</th>
                    <th>{{ __('erp.resources.waste_processor.singular') }}</th>
                    <th>{{ __('erp.waste.processor_reference') }}</th>
                    <th>{{ __('erp.waste.region') }}</th>
                    <th>{{ __('erp.waste.destruction_certificate_short') }}</th>
                    <th>{{ __('erp.fields.cow_code') }}</th>
                    <th>{{ __('erp.waste.po_number') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    @php($split = $entry->lines->count() > 1)
                    <tr>
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
                    </tr>
                @empty
                    <tr><td colspan="10" class="muted">{{ __('erp.waste.no_entries') }}</td></tr>
                @endforelse
                <tr class="total">
                    <td colspan="3">{{ __('erp.waste.total') }}</td>
                    <td class="num mono">{{ $kg($entries->sum('weight_kg')) }}</td>
                    <td colspan="6"></td>
                </tr>
            </tbody>
        </table>
    @endif
</body>
</html>
