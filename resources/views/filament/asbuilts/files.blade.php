{{-- The files of an as-built per category, with download links and (for those who manage it) an upload per category and remove buttons. --}}
@php
    $asbuilt = $getRecord();
    $canManage = \Illuminate\Support\Facades\Gate::allows('update', $asbuilt);
    $groups = $this->filesByCategory();
    $size = fn (int $bytes) => \Illuminate\Support\Number::fileSize($bytes, precision: 1);
@endphp
<div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(22rem, 1fr));" data-asbuilt-files>
    @foreach ($groups as $key => $files)
        @php($category = \App\Enums\AsbuiltCategory::tryFrom($key))
        @continue($category === null && $files === [])
        <x-filament::section :icon="$category?->getIcon() ?? 'heroicon-o-inbox-arrow-down'" compact>
            <x-slot name="heading">
                @if ($category)<span style="font-family: var(--erp-mono); opacity: .6;">{{ \Illuminate\Support\Str::before($category->folder(), ' -') }}</span> {{ $category->getLabel() }}@else{{ __('erp.asbuilt.general_zone') }}@endif
            </x-slot>
            <x-slot name="afterHeader">
                <span style="display: inline-flex; gap: .5rem; align-items: center;">
                    <x-filament::badge color="gray">{{ count($files) }}</x-filament::badge>
                    @if ($canManage && $category && $category !== \App\Enums\AsbuiltCategory::BtvReports)
                        <x-filament::icon-button icon="heroicon-o-arrow-up-tray" size="sm" :label="__('erp.asbuilt.add_files')" :tooltip="__('erp.asbuilt.add_files')"
                            wire:click="mountAction('{{ $category->isRegister() ? 'technical' : 'upload' }}', {{ \Illuminate\Support\Js::from(['category' => $category->value]) }})" />
                    @endif
                </span>
            </x-slot>

            @if ($files === [])
                <p style="margin: 0; font-size: .875rem; opacity: .6;">{{ __('erp.asbuilt.no_files') }}</p>
            @else
                <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .4rem;">
                    @foreach ($files as $file)
                        <li wire:key="asbuilt-file-{{ $file->id }}" style="display: flex; gap: .5rem; align-items: flex-start; justify-content: space-between;">
                            <span style="min-width: 0;">
                                <a href="{{ route('asbuilt.file', $file) }}" style="text-decoration: underline; overflow-wrap: anywhere;">{{ $file->title() }}</a>
                                <span style="display: block; font-size: .75rem; opacity: .65;">
                                    {{ $file->fileName() }} · {{ $size($file->sizeBytes()) }}
                                    @if ($file->isFromLibrary()) · {{ __('erp.asbuilt.from_register') }}@endif
                                    @if ($file->clientUploader) · {{ __('erp.asbuilt.by_client', ['name' => $file->clientUploader->name]) }}@elseif ($name = $file->uploaderName()) · {{ $name }}@endif
                                </span>
                            </span>
                            @if ($canManage)
                                <x-filament::icon-button icon="heroicon-o-trash" color="danger" size="sm" :label="__('erp.asbuilt.remove')" :tooltip="$file->isFromLibrary() ? __('erp.asbuilt.remove_link') : __('erp.asbuilt.remove')"
                                    wire:click="removeFile({{ $file->id }})" wire:confirm="{{ __('erp.asbuilt.remove_confirm') }}" />
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    @endforeach
</div>
