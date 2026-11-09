{{-- In the client portal: the documents (PDF), waste report and photos of a finished battery replacement (604). --}}
@php
    $project = $getRecord();
    $dossier = \App\Support\BatteryReports::dossier($project);
    $photos = \App\Support\BatteryReports::photos($dossier);
    $wasteReports = \App\Support\BatteryReports::wasteReports($project);
    $size = fn (?int $bytes) => \Illuminate\Support\Number::fileSize((int) $bytes, precision: 1);
    $row = 'display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .6rem .85rem; border-radius: .6rem; border: 1px solid rgb(148 163 184 / .35); text-decoration: none;';
@endphp
<div style="display: flex; flex-direction: column; gap: 1rem;" data-client-battery-report>
    <x-filament::section :heading="__('erp.client_portal.battery_reports.documents')">
        <div style="display: grid; gap: .5rem; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));">
            @if ($dossier)
                @foreach (\App\Support\BatteryReports::DOCUMENTS as $document)
                    <a href="{{ route('client.battery-report.document', [$project, $document]) }}" target="_blank" rel="noopener" style="{{ $row }}">
                        <span><x-filament::icon icon="heroicon-o-document-text" style="width: 1.1rem; height: 1.1rem; display: inline-block; vertical-align: -3px;" /> {{ __('erp.batteries.documents.'.$document) }}</span>
                        <span style="font-size: .75rem; opacity: .6;">PDF</span>
                    </a>
                @endforeach
            @endif
            @foreach ($wasteReports as $file)
                <a href="{{ route('client.battery-report.waste', [$project, $file]) }}" target="_blank" rel="noopener" style="{{ $row }}">
                    <span><x-filament::icon icon="heroicon-o-trash" style="width: 1.1rem; height: 1.1rem; display: inline-block; vertical-align: -3px;" /> {{ __('erp.enums.project_file_section.waste_reports') }} · {{ $file->name }}</span>
                    <span style="font-size: .75rem; opacity: .6;">{{ $size($file->latestVersion?->size) }}</span>
                </a>
            @endforeach
        </div>
        @if ($wasteReports->isEmpty())
            <div style="font-size: .8rem; opacity: .65; margin-top: .6rem;">{{ __('erp.client_portal.battery_reports.no_waste_report') }}</div>
        @endif
        @unless ($dossier)
            <div style="font-size: .85rem; opacity: .7;">{{ __('erp.client_portal.battery_reports.no_dossier') }}</div>
        @endunless
    </x-filament::section>

    <x-filament::section :heading="__('erp.client_portal.battery_reports.photos')">
        @if ($photos === [])
            <div style="font-size: .85rem; opacity: .7;">{{ __('erp.client_portal.battery_reports.no_photos') }}</div>
        @else
            <div style="display: grid; gap: .5rem; grid-template-columns: repeat(auto-fill, minmax(8rem, 1fr));">
                @foreach ($photos as $index => $path)
                    <a href="{{ route('client.battery-report.photo', [$project, $index]) }}" target="_blank" rel="noopener"
                        style="display: block; aspect-ratio: 1; border-radius: .5rem; overflow: hidden; border: 1px solid rgb(148 163 184 / .35);">
                        <img src="{{ route('client.battery-report.photo', [$project, $index]) }}" alt="{{ __('erp.client_portal.battery_reports.photo', ['number' => $loop->iteration]) }}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</div>
