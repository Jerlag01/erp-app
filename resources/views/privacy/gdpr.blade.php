{{-- The public GDPR statement of the ERP platform: a plain, readable page that also works on a phone. --}}
@php($company = trim($company))
@php($companyName = strtok($company, "\n") ?: settings()->siteName())
@php($replace = ['company' => $companyName, 'site' => settings()->siteName()])
@php($fill = fn (string $text): string => str_replace([':company', ':site'], [$replace['company'], $replace['site']], $text))
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('gdpr.title', $replace) }}</title>
    <style>
        :root { --text: #0f172a; --muted: #64748b; --line: #e2e8f0; --accent: #002b45; --bg: #f8fafc; --card: #ffffff; }
        @media (prefers-color-scheme: dark) { :root { --text: #e2e8f0; --muted: #94a3b8; --line: #334155; --accent: #93c5fd; --bg: #0f172a; --card: #111827; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.6 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        main { max-width: 46rem; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        header { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        header img { max-height: 3rem; max-width: 12rem; }
        nav a { color: var(--accent); margin-left: .5rem; text-decoration: none; font-weight: 600; }
        nav a[aria-current] { text-decoration: underline; }
        article { background: var(--card); border: 1px solid var(--line); border-radius: .75rem; padding: 1.5rem; }
        h1 { font-size: 1.6rem; line-height: 1.25; margin: 0 0 .25rem; }
        h2 { font-size: 1.15rem; margin: 1.75rem 0 .5rem; color: var(--accent); }
        .muted { color: var(--muted); font-size: .9rem; }
        address { font-style: normal; white-space: pre-line; padding: .75rem 1rem; border-left: 3px solid var(--accent); margin: .5rem 0; }
        ul { padding-left: 1.25rem; } li { margin: .25rem 0; }
        a { color: var(--accent); overflow-wrap: anywhere; }
    </style>
</head>
<body>
<main>
    <header>
        @if ($logo = settings()->get('appearance.logo'))
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="{{ $companyName }}">
        @else
            <strong>{{ $companyName }}</strong>
        @endif
        <nav aria-label="{{ __('gdpr.language') }}">
            @foreach (\App\Http\Controllers\PrivacyPolicyController::LOCALES as $option)
                <a href="{{ route('gdpr', ['lang' => $option]) }}" @if ($option === $locale) aria-current="page" @endif lang="{{ $option }}">{{ strtoupper($option) }}</a>
            @endforeach
        </nav>
    </header>

    <article>
        <h1>{{ __('gdpr.title', $replace) }}</h1>
        <p class="muted">{{ __('gdpr.updated', ['date' => $updated]) }}</p>
        <p>{{ $fill(__('gdpr.intro')) }}</p>

        @foreach (__('gdpr.sections') as $key => $section)
            <h2>{{ $fill($section['title']) }}</h2>

            @if ($key === 'controller')
                <p>{{ $section['body'] }}</p>
                <address>{{ $company }}</address>
                @if ($emails !== [])
                    <p>{{ $section['contact'] }}</p>
                    <ul data-gdpr-contacts>
                        @foreach ($emails as $email)
                            <li><a href="mailto:{{ $email }}">{{ $email }}</a></li>
                        @endforeach
                    </ul>
                @endif
                @continue
            @endif

            @isset($section['body'])
                <p>{{ $fill($section['body']) }}</p>
            @endisset
            @isset($section['items'])
                <ul>
                    @foreach ($section['items'] as $item)
                        <li>{{ $fill($item) }}</li>
                    @endforeach
                </ul>
            @endisset
            @foreach (['not', 'health', 'transfers'] as $extra)
                @isset($section[$extra])
                    <p>{{ $fill($section[$extra]) }}</p>
                @endisset
            @endforeach
        @endforeach

        <p class="muted" style="margin-top: 2rem;">
            {{ __('gdpr.app_policy') }}
            <a href="{{ route('privacy', ['lang' => $locale]) }}">{{ route('privacy') }}</a>
        </p>
    </article>
</main>
</body>
</html>
