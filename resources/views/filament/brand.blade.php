{{-- Filament's precompiled CSS only ships its own classes, so sizing here uses inline styles. --}}
<span style="display: flex; align-items: center; gap: .625rem; height: 2.5rem; white-space: nowrap;">
    @if ($logo = settings()->logoUrl())
        <img src="{{ $logo }}" alt="{{ settings()->siteName() }}" style="height: 2.25rem; width: auto; max-width: 9rem; object-fit: contain;">
    @else
        <span class="erp-mark" style="display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: .375rem; flex-shrink: 0;">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"/></svg>
        </span>
    @endif
    <span style="display: flex; flex-direction: column; line-height: 1.1;">
        <span style="color: inherit; font-size: .9375rem; font-weight: 800; letter-spacing: -.01em;">{{ settings()->siteName() }}</span>
        <span class="erp-mono" style="opacity: .6; font-size: .625rem; font-weight: 600; letter-spacing: .25em; text-transform: uppercase;">{{ $label }}</span>
    </span>
</span>
