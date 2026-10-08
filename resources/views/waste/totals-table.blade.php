{{-- Shared by the totals page and its PDF. --}}
<table data-waste-totals>
    <thead>
        <tr>
            <th>{{ __('erp.waste.waste_code') }}</th>
            <th>{{ __('erp.waste.type') }}</th>
            <th class="num">{{ __('erp.waste.entries') }}</th>
            <th class="num">{{ __('erp.waste.total_weight') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($totals['groups'] as $group)
            @php($single = count($group['rows']) === 1 && $group['rows'][0]['category']->is($group['category']))
            @unless ($single)
                <tr class="group">
                    <td class="mono">{{ $group['category']->waste_code }}</td>
                    <td>{{ $group['category']->name }}</td>
                    <td class="num">{{ $group['count'] }}</td>
                    <td class="num mono">{{ \App\Models\WasteEntry::formatKg($group['kg']) }}</td>
                </tr>
            @endunless
            @foreach ($group['rows'] as $row)
                <tr @if ($single) class="group" @endif>
                    <td class="mono {{ $row['category']->isHazardous() ? 'hazard' : '' }}">{{ $row['category']->waste_code }}</td>
                    <td>@unless ($single)&nbsp;&nbsp;&nbsp;@endunless{{ $row['category']->name }}</td>
                    <td class="num">{{ $row['count'] }}</td>
                    <td class="num mono">{{ \App\Models\WasteEntry::formatKg($row['kg']) }}</td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="4" class="muted">{{ __('erp.waste.no_entries') }}</td></tr>
        @endforelse
        <tr class="total">
            <td colspan="2">{{ __('erp.waste.grand_total') }}</td>
            <td class="num">{{ $totals['count'] }}</td>
            <td class="num mono">{{ \App\Models\WasteEntry::formatKg($totals['kg']) }}</td>
        </tr>
    </tbody>
</table>
