{{-- In the client portal: the as-built standard files of one COW-code, an upload zone per category, with replace and (for client uploads) remove. --}}
@php
    $zones = \App\Filament\Client\Resources\AsbuiltStandardFiles\Pages\ViewClientAsbuiltStandardFiles::zones();
    $groups = $this->filesPerZone();
    $size = fn (?int $bytes) => \Illuminate\Support\Number::fileSize((int) $bytes, precision: 1);
@endphp
<x-filament::section :heading="__('erp.client_portal.asbuilt_library.zones')" :description="__('erp.client_portal.asbuilt_library.zones_help')">
    <div style="display: grid; gap: .75rem; grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));" data-asbuilt-standard-files>
        @foreach ($zones as $key => $label)
            @php($files = $groups->get($key, collect()))
            <div style="padding: .75rem 1rem; border-radius: .75rem; border: 1px solid rgb(148 163 184 / .35);">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: .5rem;">
                    <strong>{{ $label }}</strong>
                    <x-filament::icon-button icon="heroicon-o-arrow-up-tray" size="sm" :label="__('erp.client_portal.asbuilt_library.upload')" :tooltip="__('erp.client_portal.asbuilt_library.upload')"
                        wire:click="mountAction('upload', {{ \Illuminate\Support\Js::from(['category' => $key]) }})" />
                </div>
                @forelse ($files as $file)
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; font-size: .85rem; margin-top: .45rem;">
                        <div style="overflow-wrap: anywhere;">
                            <a href="{{ route('client.asbuilt.library', $file) }}" style="text-decoration: underline;">{{ $file->original_name }}</a>
                            <div style="opacity: .65; font-size: .78rem;">{{ $size($file->size) }} · {{ $file->updated_at->format('d/m/Y') }}</div>
                        </div>
                        <div style="display: flex; gap: .15rem; flex-shrink: 0;">
                            <x-filament::icon-button icon="heroicon-o-arrow-path" size="sm" :label="__('erp.client_portal.asbuilt_library.replace')" :tooltip="__('erp.client_portal.asbuilt_library.replace')"
                                wire:click="mountAction('replace', {{ \Illuminate\Support\Js::from(['file' => $file->id]) }})" />
                            @if ($file->uploaded_by_client_user_id)
                                <x-filament::icon-button icon="heroicon-o-trash" size="sm" color="danger" :label="__('erp.actions.delete')" :tooltip="__('erp.actions.delete')"
                                    wire:click="mountAction('remove', {{ \Illuminate\Support\Js::from(['file' => $file->id]) }})" />
                            @endif
                        </div>
                    </div>
                @empty
                    <div style="font-size: .8rem; opacity: .6; margin-top: .35rem;">{{ __('erp.asbuilt.no_files') }}</div>
                @endforelse
            </div>
        @endforeach
    </div>
</x-filament::section>
