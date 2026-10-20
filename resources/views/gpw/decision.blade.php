{{-- The public page of a GPW request link: the request, and approve / refuse buttons while it is pending. --}}
@php($project = $gpw->project)
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('erp.gpw.page.title', ['project' => $project?->reference]) }}</title>
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
        .status.approved, .status.planned { color: var(--ok); } .status.rejected { color: var(--no); }
        .error { color: var(--no); font-size: .9rem; }
        .muted { color: var(--muted); font-size: .9rem; }
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
        <h1>{{ __('erp.gpw.page.title', ['project' => $project?->reference]) }}</h1>
        <dl>
            <dt>{{ __('erp.gpw.page.period') }}</dt><dd class="period">{{ $gpw->period() }}</dd>
            <dt>{{ __('erp.client_portal.cow_code') }}</dt><dd>{{ $project?->workSite?->cow_code ?? $project?->cow_code ?? '—' }}</dd>
            <dt>{{ __('erp.gpw.page.address') }}</dt><dd>{{ $project?->addressLine() ?? $project?->workSite?->addressLine() ?? '—' }}</dd>
            @if (filled($project?->short_description))
                <dt>{{ __('erp.client_portal.description') }}</dt><dd>{{ $project->short_description }}</dd>
            @endif
            @if (filled($gpw->remarks))
                <dt>{{ __('erp.gpw.remarks') }}</dt><dd style="white-space: pre-line">{{ $gpw->remarks }}</dd>
            @endif
            <dt>{{ __('erp.gpw.page.requested_by') }}</dt><dd>{{ $gpw->requester?->name ?? settings()->siteName() }}</dd>
        </dl>

        @if ($gpw->isPending())
            <form method="post">
                @csrf
                <label for="note">{{ __('erp.gpw.page.note') }}</label>
                <textarea id="note" name="note" maxlength="2000">{{ old('note') }}</textarea>
                @error('note') <p class="error">{{ __('erp.gpw.page.note_required') }}</p> @enderror
                <div class="buttons">
                    <button class="approve" type="submit" name="decision" value="approve">{{ __('erp.gpw.approve') }}</button>
                    <button class="reject" type="submit" name="decision" value="reject">{{ __('erp.gpw.reject') }}</button>
                </div>
                <p class="muted">{{ __('erp.gpw.page.reject_help') }}</p>
            </form>
        @else
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
