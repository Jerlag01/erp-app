{{-- In the client portal: the steps of the as-built and what this client handed in, with an upload per category. --}}
@php
    $asbuilt = $getRecord();
    $groups = $this->clientFiles();
    $size = fn (int $bytes) => \Illuminate\Support\Number::fileSize($bytes, precision: 1);
    $steps = [
        ['done' => true, 'label' => __('erp.asbuilt.steps.created'), 'date' => $asbuilt->created_at?->format('d/m/Y')],
        ['done' => $asbuilt->btv_date !== null, 'label' => __('erp.asbuilt.steps.btv_planned'), 'date' => $asbuilt->btv_date?->format('d/m/Y')],
        ['done' => $asbuilt->isClosed(), 'label' => __('erp.asbuilt.steps.finished'), 'date' => $asbuilt->closed_at?->format('d/m/Y')],
    ];
@endphp
<div style="display: flex; flex-direction: column; gap: 1rem;">
    <x-filament::section :heading="__('erp.asbuilt.follow_up')">
        <ol style="list-style: none; margin: 0; padding: 0; display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));" data-asbuilt-steps>
            @foreach ($steps as $step)
                <li style="padding: .75rem 1rem; border-radius: .75rem; border: 1px solid {{ $step['done'] ? 'rgb(34 197 94 / .45)' : 'rgb(148 163 184 / .35)' }}; background: {{ $step['done'] ? 'rgb(34 197 94 / .08)' : 'transparent' }};">
                    <strong>{{ $step['done'] ? '✓' : '○' }} {{ $step['label'] }}</strong>
                    <div style="font-size: .8rem; opacity: .75;">{{ $step['date'] ?? '—' }}</div>
                </li>
            @endforeach
        </ol>
    </x-filament::section>

    <x-filament::section :heading="__('erp.asbuilt.your_files')" :description="$asbuilt->isClosed() ? __('erp.asbuilt.closed_no_upload') : __('erp.asbuilt.client_upload_help')">
        <div style="display: grid; gap: .75rem; grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));">
            @foreach (\App\Filament\Client\Resources\Asbuilts\Pages\ViewClientAsbuilt::uploadCategories() as $key => $label)
                @php($files = $groups[$key] ?? [])
                <div style="padding: .75rem 1rem; border-radius: .75rem; border: 1px solid rgb(148 163 184 / .35);">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: .5rem;">
                        <strong>{{ $label }}</strong>
                        @unless ($asbuilt->isClosed())
                            <x-filament::icon-button icon="heroicon-o-arrow-up-tray" size="sm" :label="__('erp.asbuilt.client_upload')" :tooltip="__('erp.asbuilt.client_upload')"
                                wire:click="mountAction('upload', {{ json_encode(['category' => $key]) }})" />
                        @endunless
                    </div>
                    @forelse ($files as $file)
                        <div style="font-size: .85rem; margin-top: .35rem; overflow-wrap: anywhere;">
                            <a href="{{ route('client.asbuilt.file', $file) }}" style="text-decoration: underline;">{{ $file->fileName() }}</a>
                            <span style="opacity: .65;">· {{ $size($file->sizeBytes()) }} · {{ $file->created_at->format('d/m/Y') }}</span>
                        </div>
                    @empty
                        <div style="font-size: .8rem; opacity: .6; margin-top: .35rem;">{{ __('erp.asbuilt.no_files') }}</div>
                    @endforelse
                </div>
            @endforeach
        </div>
    </x-filament::section>
</div>
