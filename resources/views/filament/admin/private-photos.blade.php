@php
    $record = $getRecord();
@endphp

{{-- Photos are served by a controller that checks access; they are not on the public disk. --}}
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr)); gap: .75rem;" data-private-photos>
    @foreach (array_values($record->photos ?? []) as $index => $path)
        @php($url = route($routeName, [$parameter => $record, 'index' => $index]))
        <figure style="margin: 0; border-radius: .5rem; overflow: hidden; border: 1px solid rgb(148 163 184 / .35); background: rgb(148 163 184 / .1);">
            <a href="{{ $url }}" target="_blank" rel="noopener">
                <img src="{{ $url }}" alt="{{ basename($path) }}" loading="lazy" style="display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover;">
            </a>
            {{-- Some formats (e.g. iPhone HEIC) cannot be previewed in every browser: the link still opens or downloads them. --}}
            <figcaption style="padding: .375rem .5rem; font-size: .75rem;">
                <a href="{{ $url }}" target="_blank" rel="noopener" style="text-decoration: underline;">{{ __('erp.incidents.open_photo', ['name' => \Illuminate\Support\Str::limit(basename($path), 28)]) }}</a>
            </figcaption>
        </figure>
    @endforeach
</div>
