{{--
    Reading a toolbox meeting. The end of the text is marked as read when it scrolls into view; each
    attachment counts once it was downloaded. Inline styles: Filament ships precompiled CSS.
--}}
@php
    $meeting = $this->record;
    $participant = $this->participant();
    $attachments = $meeting->attachmentList();
    $done = 'color: rgb(22 163 74); font-weight: 600;';
    $todo = 'opacity: .75;';
@endphp
<x-filament-panels::page>
    @include('partials.rich-content-css')
    <style>
        .erp-tb-info { display: grid; gap: .75rem 1.5rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); font-size: .875rem; }
        .erp-tb-label { font-size: .7rem; letter-spacing: .06em; text-transform: uppercase; opacity: .65; }
        .erp-tb-files { display: flex; flex-direction: column; gap: .5rem; }
        .erp-tb-file { display: flex; align-items: center; gap: .75rem; justify-content: space-between; padding: .5rem .75rem; border: 1px solid rgb(148 163 184 / .35); border-radius: .5rem; }
        .erp-tb-steps { display: flex; flex-direction: column; gap: .35rem; font-size: .875rem; }
        .erp-tb-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .erp-tb-table th, .erp-tb-table td { text-align: left; padding: .45rem .5rem; border-bottom: 1px solid rgb(148 163 184 / .25); }
        .erp-tb-table img { max-height: 2.5rem; background: #fff; border-radius: .25rem; }
    </style>

    @unless ($meeting->isPublished())
        <x-filament::section>
            <span style="font-weight: 600;">{{ __('erp.toolbox.draft_notice') }}</span>
        </x-filament::section>
    @endunless

    <x-filament::section>
        <div class="erp-tb-info">
            <div><div class="erp-tb-label">{{ __('erp.toolbox.month') }}</div>{{ $meeting->monthLabel('nl') }} · {{ $meeting->monthLabel('fr') }}</div>
            <div><div class="erp-tb-label">{{ __('erp.toolbox.location') }}</div>{{ $meeting->location ?: '—' }}</div>
            <div><div class="erp-tb-label">{{ __('erp.toolbox.instructors') }}</div>{{ $meeting->instructorNames() ?: '—' }}</div>
            <div><div class="erp-tb-label">{{ __('erp.toolbox.subject') }}</div>NL {{ $meeting->subject_nl }}<br>FR {{ $meeting->subject_fr }}</div>
        </div>
    </x-filament::section>

    @if ($participant)
        <x-filament::section :heading="$participant->isSigned() ? __('erp.toolbox.signed') : __('erp.toolbox.steps_title')" data-toolbox-progress>
            @if ($participant->isSigned())
                <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    @if ($image = $participant->signatureDataUri())
                        <img src="{{ $image }}" alt="" style="max-height: 80px; background: #fff; border: 1px solid rgb(203 213 225); border-radius: .375rem; padding: .25rem;">
                    @endif
                    <div><strong>{{ $participant->signer_name }}</strong><br><span style="opacity: .75;">{{ __('erp.toolbox.signed_on', ['date' => $participant->signed_at->format('d/m/Y H:i')]) }}</span></div>
                </div>
            @else
                <div class="erp-tb-steps">
                    @if ($meeting->hasText())
                        <span style="{{ $participant->text_read_at ? $done : $todo }}">{{ $participant->text_read_at ? '✓' : '○' }} {{ __('erp.toolbox.step_text') }}</span>
                    @endif
                    @foreach ($attachments as $file)
                        <span style="{{ $participant->hasDownloaded($file['path']) ? $done : $todo }}">{{ $participant->hasDownloaded($file['path']) ? '✓' : '○' }} {{ __('erp.toolbox.step_file', ['name' => $file['name']]) }}</span>
                    @endforeach
                    <span style="margin-top: .35rem; font-weight: 600;">{{ $participant->hasReadEverything() ? __('erp.toolbox.ready_to_sign') : __('erp.toolbox.read_first') }}</span>
                </div>
            @endif
        </x-filament::section>
    @endif

    @if ($meeting->hasText())
        <x-filament::section>
            <div class="erp-rich">{{ $meeting->renderedBody() }}</div>
            {{-- Reaching this line means the whole text was scrolled through. --}}
            @if ($participant && ! $participant->text_read_at)
                <div x-data x-intersect.once="$wire.markTextRead()" style="height: 1px;" data-toolbox-end></div>
            @endif
        </x-filament::section>
    @endif

    @if ($attachments !== [])
        <x-filament::section :heading="__('erp.toolbox.sections.attachments')">
            <div class="erp-tb-files">
                @foreach ($attachments as $file)
                    <div class="erp-tb-file" wire:key="toolbox-file-{{ $file['index'] }}">
                        <span style="overflow-wrap: anywhere;">
                            @if ($participant?->hasDownloaded($file['path']))
                                <span style="{{ $done }}">✓</span>
                            @endif
                            {{ $file['name'] }}
                        </span>
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="download({{ $file['index'] }})">
                            {{ __('erp.toolbox.download') }}
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    @if ($this->seesSignatures())
        @php($rows = $this->attendance())
        <x-filament::section :heading="__('erp.toolbox.signatures')" :description="__('erp.toolbox.signed_count', ['count' => $rows->filter(fn ($row) => $row['participant']?->isSigned())->count(), 'total' => $rows->count()])">
            <table class="erp-tb-table">
                <thead>
                    <tr>
                        <th>{{ __('erp.fields.name') }}</th>
                        <th>{{ __('erp.toolbox.signed_at') }}</th>
                        <th>{{ __('erp.batteries.signature.title') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr wire:key="toolbox-attendee-{{ $row['user']->id }}">
                            <td>{{ $row['participant']?->signer_name ?: $row['user']->name }}</td>
                            <td>{{ $row['participant']?->signed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>
                                @if ($row['participant']?->isSigned() && ($image = $row['participant']->signatureDataUri()))
                                    <img src="{{ $image }}" alt="">
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
