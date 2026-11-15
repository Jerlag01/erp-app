<x-filament-panels::page>
    {{-- Filament ships precompiled CSS, so this table is styled inline. --}}
    <style>
        .erp-qr-export table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
        .erp-qr-export th { text-align: left; padding: .4rem .6rem; font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; opacity: .7; border-bottom: 1px solid rgb(148 163 184 / .4); white-space: nowrap; }
        .erp-qr-export td { padding: .4rem .6rem; border-bottom: 1px solid rgb(148 163 184 / .2); }
        .erp-qr-export .mono { font-family: var(--erp-mono, monospace); font-size: .75rem; white-space: nowrap; }
        .erp-qr-export ol { margin: 0; padding-left: 1.25rem; list-style: decimal; display: grid; gap: .25rem; font-size: .875rem; }
    </style>

    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    @php($preview = $this->preview())
    @php($count = $this->count())

    <x-filament::section :heading="trans_choice('erp.stock.qr_export.count', $count, ['count' => $count])" :description="__('erp.stock.qr_export.preview')">
        <div class="erp-qr-export" style="overflow-x: auto;">
            @if ($preview['rows']->isEmpty())
                <p style="opacity: .7;">{{ __('erp.stock.qr_export.empty') }}</p>
            @else
                <table>
                    <thead><tr>@foreach ($preview['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($preview['rows'] as $row)
                            <tr>@foreach ($row as $index => $value)<td @class(['mono' => $index === 0])>{{ $value }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('erp.stock.qr_export.howto_title')" collapsible>
        <div class="erp-qr-export">
            <ol>
                @foreach (__('erp.stock.qr_export.howto') as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        </div>
    </x-filament::section>
</x-filament-panels::page>
