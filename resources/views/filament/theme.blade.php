{{--
    Industrial look borrowed from sa-jacobs.be: deep navy, safety orange, Inter + JetBrains Mono.
    Filament ships precompiled CSS, so the overrides live here instead of a Tailwind build.
--}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
    :root {
        --erp-navy: {{ settings()->color('primary') }};
        --erp-navy-dark: color-mix(in srgb, var(--erp-navy) 60%, black);
        --erp-accent: {{ settings()->color('accent') }};
        --erp-mono: 'JetBrains Mono', ui-monospace, 'Courier New', monospace;
    }

    .erp-mono { font-family: var(--erp-mono); }
    .erp-mark { background: var(--erp-accent); color: #fff; }
    /* The idea box link in the top bar keeps only its icon on small screens. */
    @media (max-width: 639px) { .erp-suggestion-label { display: none; } }

    /* Sidebar: navy panel with light text and an orange marker on the active item. */
    @media (min-width: 1024px) {
        .fi-main-sidebar {
            background: linear-gradient(180deg, var(--erp-navy) 0%, var(--erp-navy-dark) 100%) !important;
        }
    }
    .fi-main-sidebar { background: linear-gradient(180deg, var(--erp-navy) 0%, var(--erp-navy-dark) 100%); color: #fff; }
    .fi-main-sidebar .fi-sidebar-header { background: transparent !important; box-shadow: none !important; --tw-ring-color: rgba(255,255,255,.08) !important; }
    .fi-main-sidebar .fi-logo { color: #fff; }
    .fi-main-sidebar .fi-sidebar-group-label {
        font-family: var(--erp-mono); font-size: .6875rem; letter-spacing: .18em; text-transform: uppercase;
        color: var(--erp-accent);
    }
    .fi-main-sidebar .fi-sidebar-group-btn .fi-icon,
    .fi-main-sidebar .fi-sidebar-group-collapse-btn { color: rgba(255,255,255,.45); }
    .fi-main-sidebar .fi-sidebar-item-label { color: rgba(255,255,255,.82); }
    .fi-main-sidebar .fi-sidebar-item-icon { color: rgba(255,255,255,.5); }
    .fi-main-sidebar .fi-sidebar-item-btn { border-left: 3px solid transparent; border-radius: .375rem; }
    .fi-main-sidebar .fi-sidebar-item-btn:hover { background: rgba(255,255,255,.07) !important; }
    .fi-main-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background: rgba(255,255,255,.1) !important; border-left-color: var(--erp-accent);
    }
    .fi-main-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-label,
    .fi-main-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-icon { color: #fff !important; }
    .fi-main-sidebar .fi-sidebar-header-controls::after { border-color: rgba(255,255,255,.1) !important; }
    .fi-main-sidebar .fi-sidebar-footer, .fi-main-sidebar .fi-sidebar-database-notifications-btn-label { color: rgba(255,255,255,.75); }
    .fi-main-sidebar .fi-icon-btn { color: rgba(255,255,255,.6); }

    /* Topbar: thin accent rule under it, like the site's header. */
    .fi-topbar { box-shadow: inset 0 -2px 0 var(--erp-accent); }

    /* Mono, uppercase labels for table headers, stats and section headings. */
    .fi-ta-header-cell, .fi-ta-header-cell-sort-btn span, .fi-wi-stats-overview-stat-label {
        font-family: var(--erp-mono); font-size: .6875rem !important; letter-spacing: .08em; text-transform: uppercase;
    }
    .fi-wi-stats-overview-stat { border-top: 3px solid var(--erp-accent); }
    .fi-wi-stats-overview-stat-value { color: var(--erp-navy); }
    .dark .fi-wi-stats-overview-stat-value { color: #fff; }
    .fi-header-heading { letter-spacing: -.02em; font-weight: 800; }

    /* Login and password reset: navy hero background with an orange-topped card. */
    .fi-simple-layout {
        background:
            radial-gradient(circle at 85% 15%, color-mix(in srgb, var(--erp-accent) 22%, transparent) 0, transparent 40%),
            linear-gradient(135deg, var(--erp-navy-dark) 0%, var(--erp-navy) 100%);
    }
    .fi-simple-main { border-top: 4px solid var(--erp-accent); }
    .fi-simple-header .fi-logo { color: var(--erp-navy); }
    .dark .fi-simple-header .fi-logo { color: #fff; }
</style>
