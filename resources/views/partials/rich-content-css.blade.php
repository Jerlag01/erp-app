{{-- Styles of text written with the rich text editor (knowledge base, toolbox meetings): headings, lists,
     pictures anywhere in the text, columns, tables. Filament ships precompiled CSS, so they are inline. --}}
<style>
        .erp-rich { line-height: 1.65; overflow-wrap: anywhere; }
        .erp-rich h1, .erp-rich h2, .erp-rich h3 { font-weight: 700; margin: 1.25em 0 .5em; }
        .erp-rich h1 { font-size: 1.5rem; } .erp-rich h2 { font-size: 1.25rem; } .erp-rich h3 { font-size: 1.1rem; }
        .erp-rich p, .erp-rich ul, .erp-rich ol, .erp-rich pre, .erp-rich blockquote, .erp-rich table { margin: 0 0 1em; }
        .erp-rich ul { list-style: disc; padding-left: 1.5rem; } .erp-rich ol { list-style: decimal; padding-left: 1.5rem; }
        .erp-rich a { color: var(--primary-600); text-decoration: underline; }
        .erp-rich img { max-width: 100%; height: auto; border-radius: .5rem; display: inline-block; vertical-align: middle; }
        .erp-rich p:has(> img:only-child) { margin: 1em 0; }
        .erp-rich [style*="text-align: center"] img { margin-inline: auto; }
        /* Columns from the editor ("grid"), e.g. a picture beside text; stacked on small screens. */
        .erp-rich .grid-layout { display: grid; gap: 1.25rem; margin: 0 0 1em; grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 768px) { .erp-rich .grid-layout { grid-template-columns: var(--cols); } .erp-rich .grid-layout-col { grid-column: var(--col-span); } }
        .erp-rich .grid-layout-col > :last-child { margin-bottom: 0; }
        .erp-rich details { border: 1px solid rgb(148 163 184 / .4); border-radius: .5rem; padding: .5rem .75rem; margin: 0 0 1em; }
        .erp-rich summary { cursor: pointer; font-weight: 600; }
        .erp-rich mark { background: rgb(250 204 21 / .45); padding: 0 .1em; border-radius: .15em; }
        .erp-rich hr { border: 0; border-top: 1px solid rgb(148 163 184 / .4); margin: 1.5em 0; }
        .erp-rich code { font-family: var(--erp-mono, monospace); font-size: .875em; background: rgb(148 163 184 / .15); padding: .1em .3em; border-radius: .25rem; }
        .erp-rich pre { background: rgb(148 163 184 / .15); padding: .75rem 1rem; border-radius: .5rem; overflow-x: auto; }
        .erp-rich pre code { background: none; padding: 0; }
        .erp-rich blockquote { border-left: 3px solid var(--primary-500); padding-left: 1rem; opacity: .85; }
        .erp-rich table { border-collapse: collapse; width: 100%; } .erp-rich td, .erp-rich th { border: 1px solid rgb(148 163 184 / .4); padding: .375rem .5rem; }
</style>
