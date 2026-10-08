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
        <div class="period">{{ __('erp.waste.period') }}: {{ $period }} · {{ trans_choice('erp.waste.entry_count', $entries->count(), ['count' => $entries->count()]) }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('erp.fields.date') }}</th>
                @unless ($region)
                    <th>{{ __('erp.waste.type') }}</th>
                    <th>{{ __('erp.waste.waste_code') }}</th>
                @endunless
                <th class="num">{{ __('erp.waste.weight') }}</th>
                @unless ($region)
                    <th>{{ __('erp.resources.waste_processor.singular') }}</th>
                    <th>{{ __('erp.waste.processor_reference') }}</th>
                    <th>{{ __('erp.waste.region') }}</th>
                @endunless
                <th>{{ __('erp.waste.destruction_certificate_short') }}</th>
                <th>{{ __('erp.fields.cow_code') }}</th>
                <th>{{ __('erp.waste.po_number') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry->date->format('d/m/Y') }}</td>
                    @unless ($region)
                        <td>{{ $entry->category->name }}@if ($entry->category->parent)<br><span class="muted">{{ $entry->category->parent->name }}</span>@endif</td>
                        <td class="mono {{ $entry->category->isHazardous() ? 'hazard' : '' }}">{{ $entry->category->waste_code }}</td>
                    @endunless
                    <td class="num mono">{{ \App\Models\WasteEntry::formatKg($entry->weight_kg) }}</td>
                    @unless ($region)
                        <td>{{ $entry->processor->name }}</td>
                        <td>{{ $entry->processor_reference }}</td>
                        <td>{{ $entry->region?->getLabel() }}</td>
                    @endunless
                    <td class="mono">{{ $entry->destruction_certificate }}</td>
                    <td class="mono">{{ $entry->cow_code }}</td>
                    <td class="mono">{{ $entry->po_number }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="muted">{{ __('erp.waste.no_entries') }}</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="{{ $region ? 1 : 3 }}">{{ __('erp.waste.total') }}</td>
                <td class="num mono">{{ \App\Models\WasteEntry::formatKg($entries->sum('weight_kg')) }}</td>
                <td colspan="{{ $region ? 3 : 6 }}"></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
