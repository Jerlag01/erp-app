{{-- Shared finance page styles; Filament's CSS is precompiled, so these are plain CSS. --}}
<style>
    .erp-fin-filters { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem 1.25rem; }
    .erp-fin-filters label { display: inline-flex; align-items: center; gap: .5rem; font-size: .875rem; }
    .erp-fin-select, .erp-fin-date { padding: .35rem .6rem; border: 1px solid rgb(148 163 184 / .5); border-radius: .5rem; background: transparent; color: inherit; font-size: .875rem; }
    .dark .erp-fin-select option { background: rgb(15 23 42); }
    .erp-fin-muted { font-size: .8125rem; opacity: .65; }
    .erp-fin-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .erp-fin-table th { text-align: left; padding: .5rem .75rem; font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; opacity: .7; border-bottom: 1px solid rgb(148 163 184 / .4); white-space: nowrap; }
    .erp-fin-table td { padding: .45rem .75rem; border-bottom: 1px solid rgb(148 163 184 / .2); }
    .erp-fin-table .num, .erp-fin-doc .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .erp-fin-table tr.is-selected td { background: color-mix(in srgb, var(--primary-500) 12%, transparent); }
    .erp-fin-table tr.is-total td { font-weight: 700; border-top: 2px solid var(--primary-500); border-bottom: 0; }
    .erp-fin-table tbody tr:not(.is-total):hover td { background: rgb(148 163 184 / .08); }
    .erp-fin-count { display: inline-block; min-width: 1.4rem; margin-left: .25rem; padding: 0 .3rem; border-radius: 999px; font-size: .6875rem; text-align: center; background: rgb(148 163 184 / .18); }
    .erp-fin-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(20rem, 1fr)); }
    .erp-fin-doc { display: grid; grid-template-columns: auto 1fr auto; gap: .75rem; align-items: center; padding: .35rem 0; border-bottom: 1px solid rgb(148 163 184 / .2); font-size: .875rem; }
    .erp-fin-doc-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .erp-fin-mono { font-family: var(--erp-mono, monospace); font-weight: 600; }
    a.erp-fin-mono { text-decoration: underline; }
    .erp-fin-toggle input { width: 1rem; height: 1rem; }
</style>
