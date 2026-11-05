{{-- In the client portal: the files of a closed battery room (609), per upload zone; pictures as thumbnails. --}}
@php
    $size = fn (?int $bytes) => \Illuminate\Support\Number::fileSize((int) $bytes, precision: 1);
@endphp
<div style="display: flex; flex-direction: column; gap: 1rem;" data-client-project-files>
    @forelse ($files->groupBy('section') as $section => $sectionFiles)
        <div>
            <strong>{{ \App\Enums\ProjectFileSection::from($section)->getLabel() }}</strong>
            @if ($section === \App\Enums\ProjectFileSection::Images->value)
                <div style="display: grid; gap: .5rem; grid-template-columns: repeat(auto-fill, minmax(7rem, 1fr)); margin-top: .5rem;">
                    @foreach ($sectionFiles as $file)
                        <a href="{{ route('client.project.file', [$project, $file]) }}" target="_blank" rel="noopener" title="{{ $file->name }}"
                            style="display: block; aspect-ratio: 1; border-radius: .5rem; overflow: hidden; border: 1px solid rgb(148 163 184 / .35);">
                            @if ($file->latestVersion?->isPreviewableImage())
                                <img src="{{ route('client.project.file', [$project, $file]) }}" alt="{{ $file->name }}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                            @else
                                <span style="display: flex; height: 100%; align-items: center; justify-content: center; padding: .5rem; font-size: .75rem; overflow-wrap: anywhere; text-align: center;">{{ $file->name }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @else
                @foreach ($sectionFiles as $file)
                    <div style="font-size: .85rem; margin-top: .35rem; overflow-wrap: anywhere;">
                        <a href="{{ route('client.project.file', [$project, $file]) }}" style="text-decoration: underline;">{{ $file->name }}</a>
                        <span style="opacity: .65;">· {{ $size($file->latestVersion?->size) }} · {{ $file->latestVersion?->created_at?->format('d/m/Y') }}</span>
                    </div>
                @endforeach
            @endif
        </div>
    @empty
        <div style="font-size: .85rem; opacity: .7;">{{ __('erp.client_portal.no_files') }}</div>
    @endforelse
</div>
