{{--
    The public page of a GPW request link: the request, and approve / refuse buttons while it is pending.
    From the buttons in the mail (a personal key, see GpwKeyController) $mode is "approve" or "reject": the
    same page with both buttons (nothing is decided by opening it); "reject" puts the cursor in the reason.
--}}
@php
    $project = $gpw->project;
    // Several projects asked in one mail: all of them on this page, one decision for those still waiting.
    $batch = $gpw->batch();
    $several = $batch->count() > 1;
    $pending = $batch->contains(fn ($item) => $item->isPending());
    $mode ??= null;
    $action ??= null;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $several ? __('erp.gpw.batch.page_title', ['count' => $batch->count()]) : __('erp.gpw.page.title', ['project' => $project?->reference]) }}</title>
    <style>
        :root { --text: #0f172a; --muted: #64748b; --line: #e2e8f0; --accent: #002b45; --bg: #f8fafc; --card: #ffffff; --ok: #15803d; --no: #b91c1c; }
        @media (prefers-color-scheme: dark) { :root { --text: #e2e8f0; --muted: #94a3b8; --line: #334155; --accent: #93c5fd; --bg: #0f172a; --card: #111827; --ok: #4ade80; --no: #f87171; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.6 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        main { max-width: 40rem; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        header { margin-bottom: 1rem; } header img { max-height: 3rem; max-width: 12rem; }
        article { background: var(--card); border: 1px solid var(--line); border-radius: .75rem; padding: 1.5rem; }
        h1 { font-size: 1.4rem; line-height: 1.25; margin: 0 0 1rem; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: .35rem 1rem; margin: 0 0 1.25rem; }
        dt { color: var(--muted); } dd { margin: 0; overflow-wrap: anywhere; }
        .period { font-weight: 700; }
        textarea { width: 100%; min-height: 5rem; font: inherit; color: inherit; background: transparent; border: 1px solid var(--line); border-radius: .5rem; padding: .5rem; }
        .buttons { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1rem; }
        button { font: inherit; font-weight: 600; border: 0; border-radius: .5rem; padding: .65rem 1.25rem; cursor: pointer; color: #fff; }
        .approve { background: var(--ok); } .reject { background: var(--no); }
        .status { padding: .75rem 1rem; border-radius: .5rem; border: 1px solid var(--line); font-weight: 600; }
        .status.approved, .status.planned, .status.in_progress, .status.executed { color: var(--ok); } .status.rejected { color: var(--no); }
        .error { color: var(--no); font-size: .9rem; }
        .muted { color: var(--muted); font-size: .9rem; }
        .sites { margin: 0; padding-left: 1.1rem; }
        .projects { margin: 0 0 1.25rem; padding-left: 1.1rem; } .projects li { margin-bottom: .6rem; }
        .status-inline { font-weight: 600; } .status-inline.approved, .status-inline.planned, .status-inline.in_progress, .status-inline.executed { color: var(--ok); } .status-inline.rejected { color: var(--no); }
        .warning { margin: 0 0 1.25rem; padding: .85rem 1rem; border: 1px solid var(--no); border-left-width: 4px; border-radius: .5rem; background: color-mix(in srgb, var(--no) 10%, transparent); color: var(--no); font-weight: 700; }
        .internal { margin: 1rem 0 0; padding: .75rem 1rem; border: 1px solid var(--line); border-left: 4px solid var(--accent); border-radius: .5rem; }
    </style>
</head>
<body>
<main>
    <header>
        @if ($logo = settings()->get('appearance.logo'))
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="{{ settings()->siteName() }}">
        @else
            <strong>{{ settings()->siteName() }}</strong>
        @endif
    </header>

    <article>
        <h1>{{ $several ? __('erp.gpw.batch.page_title', ['count' => $batch->count()]) : __('erp.gpw.page.title', ['project' => $project?->reference]) }}</h1>
        @if ($several)
            {{-- Each project with its COW code(s), address and own period; decided ones show their outcome. --}}
            <p class="muted">{{ __('erp.gpw.batch.page_help') }}</p>
            <ul class="projects">
                @foreach ($batch as $item)
                    <li>
                        <strong>{{ $item->project?->reference }}</strong> · {{ $item->cowCodes() ?? '—' }}
                        <br><span class="period">{{ $item->period() }}</span>
                        @if ($address = $item->project?->addressLine() ?? $item->project?->workSite?->addressLine())<br><span class="muted">{{ $address }}</span>@endif
                        @if (filled($item->project?->short_description))<br><span class="muted">{{ $item->project->short_description }}</span>@endif
                        @unless ($item->isPending())<br><span class="status-inline {{ $item->status->value }}">{{ $item->status->getLabel() }}@if ($item->decided_by_name) · {{ $item->decided_by_name }}@endif</span>@endunless
                    </li>
                @endforeach
            </ul>
        @endif
        <dl>
            @if ($several)
                {{-- The projects are listed above. --}}
            @else
            <dt>{{ __('erp.gpw.page.period') }}</dt><dd class="period">{{ $gpw->sites->count() > 1 ? $gpw->overallPeriod() : $gpw->period() }}</dd>
            @if ($gpw->sites->count() > 1)
                {{-- One request for several COW codes, each with its own period; one decision covers them all. --}}
                <dt>{{ __('erp.gpw.sites.cow_codes') }}</dt>
                <dd>
                    <ul class="sites">
                        @foreach ($gpw->sites as $site)
                            <li><strong>{{ $site->cow_code ?? '—' }}</strong>: {{ $site->period() }}@if ($address = $site->workSite?->addressLine())<br><span class="muted">{{ $address }}</span>@endif</li>
                        @endforeach
                    </ul>
                </dd>
            @else
                <dt>{{ __('erp.client_portal.cow_code') }}</dt><dd>{{ $project?->workSite?->cow_code ?? $project?->cow_code ?? '—' }}</dd>
                <dt>{{ __('erp.gpw.page.address') }}</dt><dd>{{ $project?->addressLine() ?? $project?->workSite?->addressLine() ?? '—' }}</dd>
            @endif
            @if (filled($project?->short_description))
                <dt>{{ __('erp.client_portal.description') }}</dt><dd>{{ $project->short_description }}</dd>
            @endif
            @endif
            @if (filled($gpw->remarks))
                <dt>{{ __('erp.gpw.remarks') }}</dt><dd style="white-space: pre-line">{{ $gpw->remarks }}</dd>
            @endif
            @if ($employees = $gpw->employeeNames())
                <dt>{{ __('erp.gpw.employees') }}</dt><dd>{{ $employees }}</dd>
            @endif
            @php($contactPerson = $gpw->contactPerson())
            <dt>{{ __('erp.gpw.contact_person') }}</dt><dd>{{ $contactPerson?->name ?? settings()->siteName() }}@if ($contactPerson)<br><span class="muted">{{ collect([$contactPerson->email, $contactPerson->phone])->filter()->implode(' · ') }}</span>@endif</dd>
        </dl>

        @if ($pending)
            {{-- Opening a link never decides anything: the request is approved or refused only with these buttons. --}}
            <form method="post" @if ($action) action="{{ $action }}" @endif>
                @csrf
                <p class="warning" role="alert">{{ __('erp.gpw.page.blocks_execution') }}</p>
                @if ($several)
                    <p><strong>{{ __('erp.gpw.batch.decides_all', ['count' => $batch->filter(fn ($item) => $item->isPending())->count()]) }}</strong></p>
                @endif
                @if (filled($decider ?? null))
                    <p>{{ __('erp.gpw.page.deciding_as', ['name' => $decider]) }}</p>
                @endif
                <label for="note">{{ $mode === 'reject' ? __('erp.gpw.page.reject_reason') : __('erp.gpw.page.note') }}</label>
                <textarea id="note" name="note" maxlength="2000" @if ($mode === 'reject') autofocus @endif>{{ old('note') }}</textarea>
                @error('note') <p class="error">{{ __('erp.gpw.page.note_required') }}</p> @enderror
                {{-- The client's own systems are not updated: the decision is only for our project follow-up. --}}
                <p class="internal"><strong>{{ __('erp.gpw.page.internal_only') }}</strong></p>
                <div class="buttons">
                    <button class="approve" type="submit" name="decision" value="approve">{{ __('erp.gpw.approve') }}</button>
                    <button class="reject" type="submit" name="decision" value="reject">{{ __('erp.gpw.reject') }}</button>
                </div>
                <p class="muted">{{ __('erp.gpw.page.reject_help') }}</p>
            </form>
        @elseif (! $several)
            <p class="status {{ $gpw->status->value }}">
                {{ $gpw->status->getLabel() }}@if ($gpw->decided_at) · {{ $gpw->decided_at->format('d/m/Y H:i') }}@endif
                @if ($gpw->decided_by_name) · {{ $gpw->decided_by_name }}@endif
            </p>
            @if (filled($gpw->decision_note))
                <p style="white-space: pre-line">{{ $gpw->decision_note }}</p>
            @endif
        @endif
    </article>
</main>
</body>
</html>
