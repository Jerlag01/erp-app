{{-- What a toolbox meeting was about (its text and the list of attachments), as the copy on the NAS. --}}
@extends('nas.pdf-layout')

@section('content')
    <h1>{{ $meeting->title }}</h1>
    <div class="muted">{{ $meeting->meeting_month->format('m/Y') }}@if ($meeting->location) · {{ $meeting->location }}@endif</div>

    @if (filled($meeting->subject_nl) || filled($meeting->subject_fr))
        <h2>{{ __('erp.toolbox.subject') }}</h2>
        @if (filled($meeting->subject_nl))<p>{{ $meeting->subject_nl }}</p>@endif
        @if (filled($meeting->subject_fr))<p>{{ $meeting->subject_fr }}</p>@endif
    @endif

    <h2>{{ __('erp.nas.toolbox_text') }}</h2>
    <div>{!! $meeting->renderedBody() !!}</div>

    @if ($meeting->attachmentList() !== [])
        <h2>{{ __('erp.fields.attachments') }}</h2>
        <ul>
            @foreach ($meeting->attachmentList() as $attachment)
                <li>{{ $attachment['name'] }}</li>
            @endforeach
        </ul>
    @endif
@endsection
