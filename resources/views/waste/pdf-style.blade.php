<style>
    @page { margin: 18mm 14mm 18mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1e293b; }
    .header { border-bottom: 3px solid {{ settings()->color('accent') }}; padding-bottom: 8px; margin-bottom: 12px; }
    .site { font-size: 8pt; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
    h1 { font-size: 16pt; margin: 4px 0 2px; color: {{ settings()->color('primary') }}; }
    .period { font-size: 9pt; color: #475569; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; background: {{ settings()->color('primary') }}; color: #fff; padding: 5px 6px; font-size: 8pt; }
    td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    tr:nth-child(even) td { background: #f8fafc; }
    .num { text-align: right; white-space: nowrap; }
    .mono { font-family: DejaVu Sans Mono, monospace; }
    .muted { color: #64748b; font-size: 8pt; }
    .hazard { color: #b91c1c; }
    tr.group td { background: #e2e8f0; font-weight: bold; }
    tr.total td { border-top: 2px solid {{ settings()->color('primary') }}; font-weight: bold; background: #fff; }
    .footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #94a3b8; text-align: center; }
</style>
