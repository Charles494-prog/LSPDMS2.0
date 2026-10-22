@extends('layouts.app')

@section('title', 'Filter & BNH Helper')

@push('styles')
<style>
    /* Same tokens as the Tarheta page: they follow the layout's light/dark variables.
       Monochrome; color is kept for errors (--bad) and save confirmations (--ok). */
    .fbnh-page, .fbnh-modal-overlay {
        --card: var(--surface, #fff); --ink: var(--text, #000); --muted: var(--text-muted, #666); --line: var(--border, #eaeaea);
        --soft: color-mix(in srgb, var(--ink) 4%, var(--card));
        --hover: color-mix(in srgb, var(--ink) 6%, var(--card));
        --bad: var(--danger, #e5484d); --ok: #18a058;
        --r: 6px; --r-lg: 12px;
        color: var(--ink);
        font-family: 'Geist', 'Inter', -apple-system, 'Helvetica Neue', Arial, sans-serif;
        -webkit-font-smoothing: antialiased; font-variant-numeric: tabular-nums;
    }
    html[data-theme="dark"] .fbnh-page, html[data-theme="dark"] .fbnh-modal-overlay { --ok: #3ecf8e; --bad: var(--danger, #ff6369); }
    .fbnh-page { max-width: 1200px; margin: 0 auto; }
    .fbnh-page *, .fbnh-page *::before, .fbnh-page *::after, .fbnh-modal-overlay *, .fbnh-modal-overlay *::before, .fbnh-modal-overlay *::after { box-sizing: border-box; }
    .fbnh-page h1, .fbnh-page h2, .fbnh-page p, .fbnh-modal-overlay h2, .fbnh-modal-overlay p { margin: 0; }
    .fbnh-page [hidden], .fbnh-modal-overlay[hidden] { display: none !important; }
    .fbnh-page input, .fbnh-modal-overlay input { color-scheme: light; }
    html[data-theme="dark"] .fbnh-page input, html[data-theme="dark"] .fbnh-modal-overlay input { color-scheme: dark; }
    .fbnh-page button:focus-visible, .fbnh-modal-overlay button:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }
    .fbnh-sr { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

    /* Page header */
    .fbnh-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; }
    .fbnh-page h1.fbnh-title { font-size: 1.5rem !important; font-weight: 600; letter-spacing: -.03em; line-height: 1.15; }
    .fbnh-page .fbnh-sub { max-width: 70ch; margin-top: .35rem; color: var(--muted); font-size: .9rem; line-height: 1.55; }

    /* Buttons */
    .fbnh-btn { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; height: 36px; padding: 0 .9rem; border: 1px solid var(--line); border-radius: var(--r); background: transparent; color: var(--ink); font: inherit; font-size: .84rem; font-weight: 500; text-decoration: none; white-space: nowrap; cursor: pointer; transition: background .12s ease, border-color .12s ease, opacity .12s ease; }
    .fbnh-btn:hover:not(:disabled) { border-color: var(--muted); background: var(--hover); }
    .fbnh-btn:disabled { opacity: .45; cursor: not-allowed; }
    .fbnh-btn-primary { border-color: var(--ink); background: var(--ink); color: var(--card); }
    .fbnh-btn-primary:hover:not(:disabled) { border-color: var(--ink); background: var(--ink); opacity: .85; }
    .fbnh-btn.is-loading { cursor: progress; }
    .fbnh-btn.is-loading:disabled { opacity: .7; }
    .fbnh-btn.is-loading::before { content: ""; width: 12px; height: 12px; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: fbnh-spin .7s linear infinite; }
    .fbnh-btn svg { width: 16px; height: 16px; flex: none; }
    .fbnh-btn.is-loading svg { display: none; }
    @keyframes fbnh-spin { to { transform: rotate(360deg); } }

    /* Checkbox (shared) */
    .fbnh-cb { flex: none; display: grid; place-content: center; width: 16px; height: 16px; margin: 0; border: 1px solid var(--muted); border-radius: 4px; background: var(--card); -webkit-appearance: none; appearance: none; cursor: pointer; }
    .fbnh-cb:checked, .fbnh-cb:indeterminate { border-color: var(--ink); background: var(--ink); }
    .fbnh-cb:checked::after { content: ""; width: 8px; height: 4px; margin-top: -1px; border-left: 2px solid var(--card); border-bottom: 2px solid var(--card); transform: rotate(-45deg); }
    .fbnh-cb:indeterminate::after { content: ""; width: 8px; height: 2px; background: var(--card); }
    .fbnh-cb:disabled { opacity: .45; cursor: not-allowed; }
    .fbnh-cb:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }
    .fbnh-check { width: 18px; height: 18px; border-radius: 4px; }

    /* Filters: one "Add Filter" menu, plus a dashed pill for each filter that narrows the list */
    .fbnh-filters { position: relative; margin-bottom: 1.25rem; }
    .fbnh-filter-row { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }
    .fbnh-addfilter { display: inline-flex; align-items: center; gap: .5rem; height: 36px; padding: 0 .95rem 0 .8rem; border: 1px solid var(--line); border-radius: 999px; background: var(--soft); color: var(--ink); font: inherit; font-size: .86rem; font-weight: 600; cursor: pointer; transition: border-color .12s ease; }
    .fbnh-addfilter:hover, .fbnh-addfilter[aria-expanded="true"] { border-color: var(--muted); }
    .fbnh-addfilter svg { width: 16px; height: 16px; flex: none; }
    .fbnh-pills { display: contents; }
    .fbnh-pill { display: inline-flex; align-items: center; height: 36px; border: 1px dashed color-mix(in srgb, var(--ink) 28%, var(--line)); border-radius: 999px; }
    .fbnh-pill:hover { border-color: var(--muted); }
    .fbnh-pill-main { display: inline-flex; align-items: center; gap: .4rem; height: 100%; padding: 0 .35rem 0 .9rem; border: 0; border-radius: 999px 0 0 999px; background: transparent; color: var(--ink); font: inherit; font-size: .86rem; cursor: pointer; }
    .fbnh-pill-k { color: var(--muted); }
    .fbnh-pill-v { font-weight: 600; }
    .fbnh-pill-x { display: grid; place-items: center; width: 30px; height: 100%; padding: 0; border: 0; border-radius: 0 999px 999px 0; background: transparent; color: var(--muted); cursor: pointer; }
    .fbnh-pill-x:hover { color: var(--ink); }
    .fbnh-pill-x svg { width: 9px; height: 9px; }
    .fbnh-pill-main:focus-visible, .fbnh-pill-x:focus-visible { outline-offset: -2px; border-radius: 999px; }
    .fbnh-link { padding: 0 .35rem; border: 0; background: none; color: var(--muted); font: inherit; font-size: .82rem; text-decoration: underline; text-underline-offset: 3px; cursor: pointer; }
    .fbnh-link:hover { color: var(--ink); }

    .fbnh-menu { position: absolute; z-index: 30; width: 340px; max-width: calc(100vw - 2rem); border: 1px solid var(--line); border-radius: var(--r-lg); background: var(--card); box-shadow: 0 10px 32px rgba(0,0,0,.2); overflow: hidden; }
    .fbnh-menu-search { display: block; width: 100%; padding: .85rem 1rem; border: 0; border-bottom: 1px solid var(--line); border-radius: 0; background: transparent; color: var(--ink); font: inherit; font-size: .92rem; -webkit-appearance: none; appearance: none; }
    .fbnh-menu-search::placeholder { color: var(--muted); }
    .fbnh-menu-search:focus { outline: none; border-bottom-color: var(--ink); }
    .fbnh-menu-list, .fbnh-menu-body { padding: .4rem; }
    .fbnh-menu-row { display: flex; align-items: center; gap: .75rem; width: 100%; padding: .6rem .65rem; border: 0; border-radius: var(--r); background: transparent; color: var(--ink); font: inherit; font-size: .92rem; text-align: left; cursor: pointer; }
    .fbnh-menu-row:hover, .fbnh-menu-row:focus-visible { background: var(--hover); }
    .fbnh-menu-row small { margin-left: auto; color: var(--muted); font-size: .78rem; }
    .fbnh-menu-row svg { width: 8px; height: 14px; flex: none; color: var(--muted); }
    .fbnh-menu-head { display: flex; align-items: center; padding: .45rem .5rem; border-bottom: 1px solid var(--line); }
    .fbnh-menu-back { display: inline-flex; align-items: center; gap: .5rem; padding: .3rem .5rem; border: 0; border-radius: var(--r); background: transparent; color: var(--ink); font: inherit; font-size: .9rem; font-weight: 600; cursor: pointer; }
    .fbnh-menu-back:hover { background: var(--hover); }
    .fbnh-menu-back svg { width: 8px; height: 14px; color: var(--muted); }
    .fbnh-field-input { width: 100%; height: 36px; padding: 0 .75rem; border: 1px solid var(--line); border-radius: var(--r); background: var(--card); color: var(--ink); font: inherit; font-size: .86rem; -webkit-appearance: none; appearance: none; transition: border-color .12s ease, box-shadow .12s ease; }
    .fbnh-field-input::placeholder { color: var(--muted); }
    .fbnh-field-input:hover { border-color: var(--muted); }
    .fbnh-field-input:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 1px var(--ink); }
    .fbnh-menu-body .fbnh-field-input { margin-bottom: .4rem; }
    .fbnh-opt { display: flex; align-items: center; gap: .6rem; min-height: 34px; padding: .3rem .5rem; border-radius: var(--r); font-size: .88rem; cursor: pointer; }
    .fbnh-opt:hover { background: var(--hover); }
    .fbnh-opt-all { margin-bottom: .25rem; border-bottom: 1px solid var(--line); border-radius: var(--r) var(--r) 0 0; font-weight: 600; }
    .fbnh-opt:has(input:disabled) { color: var(--muted); cursor: not-allowed; }
    .fbnh-opt:has(input:disabled):hover { background: transparent; }
    .fbnh-opts { max-height: 240px; overflow: auto; }
    .fbnh-locked { margin-left: auto; color: var(--muted); font-size: .72rem; }
    .fbnh-pop-empty { padding: .8rem .5rem; color: var(--muted); font-size: .84rem; text-align: center; }

    /* Status line */
    .fbnh-status { min-height: 1.2rem; margin-top: .75rem; color: var(--muted); font-size: .82rem; line-height: 1.45; }
    .fbnh-status:empty { min-height: 0; margin-top: 0; }
    .fbnh-status[data-error="true"] { color: var(--bad); }
    .fbnh-status[data-ok="true"] { color: var(--ok); }

    /* Tabs */
    .fbnh-tabs { display: flex; flex-wrap: wrap; border-bottom: 1px solid var(--line); }
    .fbnh-tab { display: inline-flex; align-items: center; min-height: 42px; margin-bottom: -1px; padding: .65rem 1.1rem; border: 0; border-bottom: 2px solid transparent; background: transparent; color: var(--muted); font: inherit; font-size: .88rem; font-weight: 500; cursor: pointer; transition: color .12s ease, border-color .12s ease; }
    .fbnh-tab:hover:not([aria-selected="true"]) { color: var(--ink); }
    .fbnh-tab[aria-selected="true"] { border-bottom-color: var(--ink); color: var(--ink); font-weight: 600; }
    .fbnh-tab:focus-visible { outline: 2px solid var(--ink); outline-offset: -2px; }
    .fbnh-panel { padding-top: 1.25rem; }
    .fbnh-panel-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
    .fbnh-page h2.fbnh-h2 { font-size: 1.05rem; font-weight: 600; letter-spacing: -.02em; line-height: 1.3; }

    /* Table card */
    .fbnh-card { border: 1px solid var(--line); border-radius: var(--r); background: var(--card); overflow: hidden; }
    .fbnh-scroll { max-width: 100%; overflow-x: auto; }
    .fbnh-table { width: 100%; min-width: 520px; border-collapse: collapse; font-size: .86rem; }
    .fbnh-table th { padding: .5rem 1rem; border-bottom: 1px solid var(--line); background: transparent; color: var(--muted); font-size: .76rem; font-weight: 500; text-align: left; white-space: nowrap; }
    .fbnh-table td { padding: .45rem 1rem; border-bottom: 1px solid var(--line); vertical-align: middle; white-space: nowrap; }
    .fbnh-table tbody tr:last-child td { border-bottom: 0; }
    .fbnh-table tbody tr:not(.is-total):hover td { background: var(--hover); }
    .fbnh-table .number { text-align: center; }
    .fbnh-table tr.is-total td, .fbnh-table tr.is-subtotal td { background: var(--soft); font-weight: 600; }
    .fbnh-table tr.is-total td { border-top: 1px solid var(--ink); }
    .fbnh-count { min-width: 2.75rem; padding: .15rem .5rem; border: 0; border-radius: var(--r); background: transparent; color: var(--ink); font: inherit; font-weight: 600; text-decoration: underline; text-underline-offset: 3px; cursor: pointer; transition: background .12s ease, color .12s ease; }
    .fbnh-count:hover { background: var(--ink); color: var(--card); text-decoration: none; }
    .fbnh-count.is-zero { color: var(--muted); font-weight: 500; }
    .fbnh-counts-table { min-width: 28rem; }
    .fbnh-counts-table th, .fbnh-counts-table td { padding: .4rem .75rem; }
    .fbnh-counts-table th:first-child, .fbnh-counts-table td:first-child { padding-left: 1rem; }
    .fbnh-counts-table thead tr:first-child th { text-align: center; }
    .fbnh-counts-table thead tr:first-child th:first-child { text-align: left; }
    .fbnh-counts-table .fbnh-summary-group { color: var(--ink); }

    .fbnh-empty { padding: 2.25rem 1rem !important; text-align: center !important; white-space: normal !important; color: var(--muted); }
    .fbnh-empty strong { display: block; margin-bottom: .2rem; color: var(--ink); font-weight: 600; }
    .fbnh-skel { display: block; width: 65%; height: 12px; border-radius: 4px; background: var(--line); animation: fbnh-pulse 1.2s ease-in-out infinite; }
    .fbnh-skel.is-short { width: 35%; }
    @keyframes fbnh-pulse { 50% { opacity: .4; } }

    .fbnh-verdict { display: inline-flex; align-items: center; gap: .5rem; font-size: .84rem; font-weight: 500; }
    .fbnh-verdict::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--ink); }
    .fbnh-verdict.not-qualified { color: var(--muted); }
    .fbnh-verdict.not-qualified::before { background: transparent; border: 1.5px dashed var(--muted); }
    .fbnh-bnh-table th.number { width: 10rem; min-width: 8rem; white-space: normal; line-height: 1.35; }
    .fbnh-bnh-table td.fbnh-checkcell .fbnh-cb { margin-inline: auto; }
    .fbnh-bnh-table td.fbnh-member { min-width: 220px; white-space: normal; overflow-wrap: anywhere; }
    .fbnh-member-name { display: inline; margin-right: .9rem; font-weight: 500; }
    .fbnh-member-meta { display: inline-flex; flex-wrap: wrap; gap: 0 .85rem; color: var(--muted); font-size: .8rem; }
    .fbnh-rowmsg { margin-left: .6rem; font-size: .76rem; }
    .fbnh-rowmsg.is-saving { color: var(--muted); }
    .fbnh-rowmsg.is-ok { color: var(--ok); }

    .fbnh-pagination { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-top: .9rem; color: var(--muted); font-size: .82rem; }
    .fbnh-pagination-controls { display: flex; gap: .5rem; }
    .fbnh-pagination .fbnh-btn { height: 30px; font-size: .78rem; }

    /* BNH toolbar: search on the left, export on the right */
    .fbnh-bar { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem 1rem; margin-bottom: .85rem; }
    .fbnh-search-row { flex: 1 1 260px; max-width: 360px; }
    .fbnh-bar .fbnh-btn { margin-left: auto; }
    .fbnh-bar + .fbnh-status { margin: -.35rem 0 .75rem; }

    /* Segmented control */
    .fbnh-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .55rem; margin-bottom: 1rem; }
    .fbnh-group-text { color: var(--muted); font-size: .8rem; }
    .fbnh-seg { display: inline-flex; padding: 2px; border: 1px solid var(--line); border-radius: var(--r); background: var(--card); }
    .fbnh-seg label { position: relative; cursor: pointer; }
    .fbnh-seg input { position: absolute; opacity: 0; width: 0; height: 0; }
    .fbnh-seg span { display: block; padding: 0 .8rem; border-radius: 4px; color: var(--muted); font-size: .82rem; font-weight: 500; line-height: 30px; white-space: nowrap; transition: background .12s ease, color .12s ease; }
    .fbnh-seg label:hover span { color: var(--ink); }
    .fbnh-seg input:checked + span { background: var(--ink); color: var(--card); }
    .fbnh-seg input:focus-visible + span { outline: 2px solid var(--ink); outline-offset: 1px; }

    /* Members dialog */
    .fbnh-modal-overlay { position: fixed; inset: 0; z-index: 1000; display: grid; place-items: center; padding: 1rem; background: rgba(0,0,0,.6); }
    .fbnh-modal { width: min(720px, 100%); max-height: min(84vh, 800px); display: flex; flex-direction: column; border: 1px solid var(--line); border-radius: var(--r); background: var(--card); overflow: hidden; }
    .fbnh-modal-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--line); }
    .fbnh-modal-header h2 { font-size: 1.05rem; font-weight: 600; letter-spacing: -.02em; }
    .fbnh-modal-header p { margin-top: .25rem; color: var(--muted); font-size: .84rem; }
    .fbnh-modal-body { overflow: auto; }
    .fbnh-modal .fbnh-table { min-width: 0; }
    .fbnh-modal .fbnh-table th:first-child { position: static; }
    .fbnh-modal-foot { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .7rem 1.25rem; border-top: 1px solid var(--line); color: var(--muted); font-size: .8rem; }
    .fbnh-modal-foot .fbnh-btn { height: 30px; font-size: .78rem; }

    @media (prefers-reduced-motion: reduce) { .fbnh-page *, .fbnh-page *::before, .fbnh-modal-overlay * , .fbnh-modal-overlay *::before { transition: none !important; animation: none !important; } }
    @media (max-width: 700px) {
        .fbnh-reload { width: 100%; }
        .fbnh-menu { left: 0 !important; right: 0; width: auto; max-width: none; }
    }
    @media (max-width: 480px) {
        .fbnh-tabs { flex-wrap: nowrap; overflow-x: auto; }
        .fbnh-tab { white-space: nowrap; }
        .fbnh-pagination { align-items: flex-start; flex-direction: column; }
        .fbnh-search-row { max-width: none; }
        .fbnh-bar .fbnh-btn { width: 100%; margin-left: 0; }
    }
</style>
@endpush

@section('content')
<div class="fbnh-page" id="fbnhApp"
    data-matrix-url="{{ route('filter-bnh-helper.matrix') }}"
    data-members-url="{{ route('filter-bnh-helper.members') }}"
    data-bnh-url="{{ route('filter-bnh-helper.bnh-records') }}"
    data-counts-url="{{ route('filter-bnh-helper.qualified-counts') }}"
    data-checks-url="{{ url('/filter-bnh-helper/records') }}"
    data-word-url="{{ route('filter-bnh-helper.bnh-word') }}"
    data-counts-word-url="{{ route('filter-bnh-helper.counts-word') }}"
    data-can-view-mk="{{ $canViewMk ? 'true' : 'false' }}"
    data-csrf="{{ csrf_token() }}">

    <header class="fbnh-head">
        <div>
            <h1 class="fbnh-title">Filter &amp; BNH Helper</h1>
            <p class="fbnh-sub">Explore active records, update BNH checks, and prepare your resumen. Inactive records are excluded.</p>
        </div>
        <button type="button" class="fbnh-btn fbnh-reload" id="fbnhReload" aria-label="Reload the current tab without changing filters">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M20 7v5h-5M4 17v-5h5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M5.6 9a7 7 0 0 1 11.7-2L20 12M4 12l2.7 5a7 7 0 0 0 11.7-2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="fbnh-btn-label" id="fbnhReloadLabel">Reload data</span>
        </button>
    </header>

    <section class="fbnh-filters" id="fbnhFilters" aria-label="Filters">
        <div class="fbnh-filter-row">
            <button type="button" class="fbnh-addfilter" id="fbnhAddFilter" aria-haspopup="true" aria-expanded="false" aria-controls="fbnhMenu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M7 12h10M10 17h4"/></svg>
                Add Filter
            </button>
            <div class="fbnh-pills" id="fbnhPills"></div>
            <button type="button" class="fbnh-link" id="fbnhReset" hidden>Reset filters</button>
        </div>

        <div class="fbnh-menu" id="fbnhMenu" role="group" aria-label="Add filter" hidden>
            <div id="fbnhViewRoot">
                <input type="search" class="fbnh-menu-search" id="fbnhMenuSearch" placeholder="Filter by…" autocomplete="off" aria-label="Filter by">
                <div class="fbnh-menu-list">
                    <button type="button" class="fbnh-menu-row" data-cat="purok"><span>Purok</span><small id="fbnhCatPurokValue">All</small><svg viewBox="0 0 8 14" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M1 1l6 6-6 6"/></svg></button>
                    <button type="button" class="fbnh-menu-row" data-cat="status"><span>Kalagayan</span><small id="fbnhCatStatusValue">All</small><svg viewBox="0 0 8 14" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M1 1l6 6-6 6"/></svg></button>
                    <div class="fbnh-pop-empty" id="fbnhMenuNone" hidden>No filters match.</div>
                </div>
            </div>

            <div id="fbnhViewPurok" hidden>
                <div class="fbnh-menu-head"><button type="button" class="fbnh-menu-back" data-back><svg viewBox="0 0 8 14" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M7 1L1 7l6 6"/></svg>Purok</button></div>
                <div class="fbnh-menu-body">
                    <input type="search" class="fbnh-field-input" id="fbnhPurokFilter" placeholder="Search puroks" autocomplete="off" aria-label="Search puroks">
                    <label class="fbnh-opt fbnh-opt-all"><input class="fbnh-cb" id="fbnhAllPuroks" type="checkbox" checked><span>All Puroks</span></label>
                    <div class="fbnh-opts" id="fbnhPurokOptions">
                        @foreach ($puroks as $purok)
                            <label class="fbnh-opt">
                                <input class="fbnh-cb fbnh-purok-option" type="checkbox" value="{{ $purok->id }}" checked>
                                <span>{{ $purok->purok_name ?: 'Purok '.$purok->purok_number }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="fbnh-pop-empty" id="fbnhPurokNone" hidden>No puroks match your search.</div>
                </div>
            </div>

            <div id="fbnhViewStatus" hidden>
                <div class="fbnh-menu-head"><button type="button" class="fbnh-menu-back" data-back><svg viewBox="0 0 8 14" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M7 1L1 7l6 6"/></svg>Kalagayan</button></div>
                <div class="fbnh-menu-body">
                    <label class="fbnh-opt fbnh-opt-all"><input class="fbnh-cb" id="fbnhAllStatuses" type="checkbox" checked><span>All</span></label>
                    <div class="fbnh-opts" id="fbnhStatusOptions">
                        @foreach (['MS', 'UWP', 'MK'] as $status)
                            <label class="fbnh-opt">
                                <input class="fbnh-cb fbnh-status-option" type="checkbox" value="{{ $status }}" @checked($status !== 'MK' || $canViewMk) @disabled($status === 'MK' && ! $canViewMk)>
                                <span>{{ $status }}</span>
                                @if ($status === 'MK' && ! $canViewMk) <span class="fbnh-locked">De-Listment access required</span> @endif
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="fbnh-status" id="fbnhPageStatus" role="status" aria-live="polite"></div>
    </section>

    <div class="fbnh-tabs" role="tablist" aria-label="Filter and BNH tools">
        <button type="button" class="fbnh-tab" id="fbnhTabMatrix" data-tab="matrix" role="tab" aria-controls="fbnhPanelMatrix" aria-selected="true">Purok Group Filter</button>
        <button type="button" class="fbnh-tab" id="fbnhTabList" data-tab="list" role="tab" aria-controls="fbnhPanelList" aria-selected="false" tabindex="-1">BNH Form Helper</button>
        <button type="button" class="fbnh-tab" id="fbnhTabResumen" data-tab="resumen" role="tab" aria-controls="fbnhPanelResumen" aria-selected="false" tabindex="-1">BNH Helper Form Resumen</button>
    </div>

    {{-- Purok Group Filter --}}
    <section class="fbnh-panel" id="fbnhPanelMatrix" role="tabpanel" aria-labelledby="fbnhTabMatrix">
        <div class="fbnh-panel-head">
            <div>
                <h2 class="fbnh-h2">Purok Group Filter</h2>
                <p class="fbnh-sub">Select a Male, Female, or Total count to view the matching members.</p>
            </div>
        </div>
        <div class="fbnh-card">
            <div class="fbnh-scroll">
                <table class="fbnh-table">
                    <thead><tr><th>Purok-Grupo</th><th class="number">Male</th><th class="number">Female</th><th class="number">Total</th></tr></thead>
                    <tbody id="fbnhMatrixBody"></tbody>
                </table>
            </div>
        </div>
        <div class="fbnh-status" id="fbnhMatrixStatus" role="status" aria-live="polite"></div>
    </section>

    {{-- BNH Form Helper --}}
    <section class="fbnh-panel" id="fbnhPanelList" role="tabpanel" aria-labelledby="fbnhTabList" hidden>
        <div class="fbnh-panel-head">
            <div>
                <h2 class="fbnh-h2">BNH Form Helper</h2>
                <p class="fbnh-sub">Mark Salaysay / Pakikipag-usap and Paghahanda. A member is qualified only when both are checked. Changes save as soon as you tick a box.</p>
                <p class="fbnh-sub" id="fbnhScope">Using the filters above.</p>
            </div>
        </div>

        <div class="fbnh-bar">
            <div class="fbnh-search-row">
                <label class="fbnh-sr" for="fbnhSearch">Search BNH records</label>
                <input type="search" class="fbnh-field-input" id="fbnhSearch" maxlength="150" placeholder="Search by member name" autocomplete="off">
            </div>
            <button type="button" class="fbnh-btn fbnh-btn-primary" id="fbnhDownloadList"><span class="fbnh-btn-label">Download Word Form</span></button>
        </div>
        <div class="fbnh-status" id="fbnhExportStatus" role="status" aria-live="polite"></div>

        <div class="fbnh-card">
            <div class="fbnh-scroll">
                <table class="fbnh-table fbnh-bnh-table">
                    <thead><tr><th>Member</th><th class="number">Salaysay / Pakikipag-usap</th><th class="number">Paghahanda</th><th>Verdict</th></tr></thead>
                    <tbody id="fbnhRecordsBody"></tbody>
                </table>
            </div>
        </div>
        <div class="fbnh-pagination">
            <span id="fbnhPageInfo">Page 1</span>
            <div class="fbnh-pagination-controls">
                <button type="button" class="fbnh-btn" id="fbnhPrevious" disabled>Previous</button>
                <button type="button" class="fbnh-btn" id="fbnhNext" disabled>Next</button>
            </div>
        </div>
        <div class="fbnh-status" id="fbnhRecordsStatus" role="status" aria-live="polite"></div>
    </section>

    {{-- Resumen --}}
    <section class="fbnh-panel" id="fbnhPanelResumen" role="tabpanel" aria-labelledby="fbnhTabResumen" hidden>
        <div class="fbnh-panel-head">
            <div>
                <h2 class="fbnh-h2">BNH Helper Form Resumen</h2>
                <p class="fbnh-sub">Qualified members have both BNH checks. Total minuses is the selected MS, UWP, and/or MK count minus those qualified members.</p>
            </div>
            <button type="button" class="fbnh-btn fbnh-btn-primary" id="fbnhDownloadCounts"><span class="fbnh-btn-label">Download Resumen</span></button>
        </div>
        <div class="fbnh-toolbar">
            <span class="fbnh-group-text" id="fbnhModeLabel">Show counts</span>
            <div class="fbnh-seg" role="radiogroup" aria-labelledby="fbnhModeLabel">
                <label><input type="radio" name="fbnhCountMode" id="fbnhShowQualified" value="qualified"><span>Qualified for BNH</span></label>
                <label><input type="radio" name="fbnhCountMode" id="fbnhShowMinuses" value="minuses"><span>Total minuses</span></label>
                <label><input type="radio" name="fbnhCountMode" id="fbnhShowBoth" value="both" checked><span>Both</span></label>
            </div>
        </div>
        <div class="fbnh-card">
            <div class="fbnh-scroll">
                <table class="fbnh-table fbnh-counts-table">
                    <thead id="fbnhCountsHead"></thead>
                    <tbody id="fbnhCountsBody"></tbody>
                </table>
            </div>
        </div>
        <div class="fbnh-status" id="fbnhCountsStatus" role="status" aria-live="polite"></div>
    </section>
</div>

<div class="fbnh-modal-overlay" id="fbnhMembersModal" role="presentation" hidden>
    <section class="fbnh-modal" role="dialog" aria-modal="true" aria-labelledby="fbnhMemberTitle">
        <div class="fbnh-modal-header">
            <div>
                <h2 id="fbnhMemberTitle">Members</h2>
                <p id="fbnhMemberCount"></p>
            </div>
            <button type="button" class="fbnh-btn" id="fbnhMemberClose">Close</button>
        </div>
        <div class="fbnh-modal-body">
            <table class="fbnh-table">
                <thead><tr><th>Name</th><th>Kalagayan</th><th>Purok-Grupo</th></tr></thead>
                <tbody id="fbnhMemberBody"></tbody>
            </table>
        </div>
        <div class="fbnh-modal-foot" id="fbnhMemberPager" hidden>
            <span id="fbnhMemberPagerInfo"></span>
            <div class="fbnh-pagination-controls">
                <button type="button" class="fbnh-btn" id="fbnhMemberPrev">Previous</button>
                <button type="button" class="fbnh-btn" id="fbnhMemberNext">Next</button>
            </div>
        </div>
    </section>
</div>

<script>
(() => {
    const app = document.getElementById('fbnhApp');
    if (!app) return;
    const $ = id => document.getElementById(id);

    const allPuroksCheckbox = $('fbnhAllPuroks');
    const purokCheckboxes = Array.from(document.querySelectorAll('.fbnh-purok-option'));
    const allStatusesCheckbox = $('fbnhAllStatuses');
    const statusCheckboxes = Array.from(document.querySelectorAll('.fbnh-status-option'));
    const availableStatusCheckboxes = statusCheckboxes.filter(checkbox => !checkbox.disabled);
    const searchInput = $('fbnhSearch');
    const showQualifiedRadio = $('fbnhShowQualified');
    const showMinusesRadio = $('fbnhShowMinuses');
    const showBothRadio = $('fbnhShowBoth');
    const modal = $('fbnhMembersModal');
    const csrf = app.dataset.csrf;
    const MEMBER_PAGE_SIZE = 25;
    const NO_SELECTION = 'Select at least one Purok and one Kalagayan to see results.';

    let currentPage = 1;
    let lastPage = 1;
    let searchTimer;
    let filterTimer;
    let activeTab = 'matrix';
    let countsData = null, countsLoading = false, countsError = '';
    let memberState = null;
    let lastFocus = null;
    const controllers = {};
    // A tab loads only when it is opened and its data is out of date. Changing a filter marks every tab out of date.
    const stale = { matrix: true, list: true, resumen: true };

    /* ---------------- Helpers ---------------- */
    const isAbort = error => error && error.name === 'AbortError';
    const begin = key => { controllers[key]?.abort(); controllers[key] = new AbortController(); return controllers[key].signal; };
    const hasSelection = () => purokCheckboxes.some(c => c.checked) && availableStatusCheckboxes.some(c => c.checked);
    const purokName = checkbox => checkbox.nextElementSibling.textContent.trim();

    const query = (extra = {}) => {
        const params = new URLSearchParams({ purok_selection: 'selected', status_selection: 'selected' });
        purokCheckboxes.filter(c => c.checked).forEach(c => params.append('purok_ids[]', c.value));
        availableStatusCheckboxes.filter(c => c.checked).forEach(c => params.append('status_ids[]', c.value));
        Object.entries(extra).forEach(([key, value]) => {
            params.delete(key);
            params.delete(`${key}[]`);
            if (Array.isArray(value)) value.forEach(item => params.append(`${key}[]`, item));
            else params.set(key, String(value));
        });
        return params.toString();
    };

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: { 'Accept': 'application/json', ...(options.body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf } : {}) },
        });
        let payload = {};
        try { payload = await response.json(); } catch (error) { throw new Error('The server returned an unreadable response.'); }
        if (!response.ok) {
            throw new Error(payload.message || Object.values(payload.errors || {}).flat()[0] || 'Unable to load the requested data.');
        }
        return payload;
    }

    function setStatus(id, message = '', isError = false, isOk = false) {
        const node = $(id);
        node.textContent = message;
        node.dataset.error = isError ? 'true' : 'false';
        node.dataset.ok = isOk ? 'true' : 'false';
    }

    function cell(row, text, className = '') {
        const node = document.createElement('td');
        node.textContent = text;
        if (className) node.className = className;
        row.append(node);
        return node;
    }

    const clear = body => { body.removeAttribute('aria-busy'); body.replaceChildren(); };

    function messageRow(body, cols, title, text = '') {
        const row = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = cols;
        td.className = 'fbnh-empty';
        const strong = document.createElement('strong');
        strong.textContent = title;
        const span = document.createElement('span');
        span.textContent = text;
        td.append(strong, span);
        row.append(td);
        body.append(row);
    }

    function skeletonRows(body, cols, count = 4) {
        body.replaceChildren();
        body.setAttribute('aria-busy', 'true');
        for (let i = 0; i < count; i++) {
            const row = document.createElement('tr');
            for (let c = 0; c < cols; c++) {
                const td = document.createElement('td');
                const bar = document.createElement('span');
                bar.className = 'fbnh-skel' + (c > 0 ? ' is-short' : '');
                td.append(bar);
                row.append(td);
            }
            body.append(row);
        }
    }

    const showNoSelection = (body, cols) => { clear(body); messageRow(body, cols, 'Nothing selected', NO_SELECTION); };

    /* ---------------- Purok Group Filter ---------------- */
    function appendCountButton(row, item, gender, count, groupLabel) {
        const td = document.createElement('td');
        td.className = 'number';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'fbnh-count' + (Number(count) === 0 ? ' is-zero' : '');
        button.textContent = String(count);
        button.setAttribute('aria-label', `Show ${gender === 'all' ? 'all' : gender} records in ${groupLabel}`);
        button.addEventListener('click', () => loadMembers(item, gender));
        td.append(button);
        row.append(td);
    }

    async function loadMatrix() {
        const body = $('fbnhMatrixBody');
        const signal = begin('matrix');
        setStatus('fbnhMatrixStatus');
        if (!hasSelection()) { showNoSelection(body, 4); return; }
        skeletonRows(body, 4);
        try {
            const data = await requestJson(`${app.dataset.matrixUrl}?${query()}`, { signal });
            clear(body);
            if (!data.rows.length) { messageRow(body, 4, 'No groups to show', 'No configured Purok groups match the selected filters.'); return; }
            for (const item of data.rows) {
                const row = document.createElement('tr');
                row.className = item.type === 'grand_total' ? 'is-total' : (item.type === 'purok_total' ? 'is-subtotal' : '');
                cell(row, item.label);
                appendCountButton(row, item, 'male', item.male, item.label);
                appendCountButton(row, item, 'female', item.female, item.label);
                appendCountButton(row, item, 'all', item.total, item.label);
                body.append(row);
            }
        } catch (error) {
            if (isAbort(error)) return;
            stale.matrix = true;
            clear(body);
            messageRow(body, 4, 'Could not load group counts', error.message);
            setStatus('fbnhMatrixStatus', error.message, true);
        }
    }

    /* ---------------- Members dialog ---------------- */
    function renderMembers() {
        const body = $('fbnhMemberBody');
        clear(body);
        const records = memberState.records;
        if (!records.length) {
            messageRow(body, 3, 'No matching records');
            $('fbnhMemberPager').hidden = true;
            return;
        }
        const pages = Math.max(1, Math.ceil(records.length / MEMBER_PAGE_SIZE));
        memberState.page = Math.min(memberState.page, pages);
        const start = (memberState.page - 1) * MEMBER_PAGE_SIZE;
        for (const member of records.slice(start, start + MEMBER_PAGE_SIZE)) {
            const row = document.createElement('tr');
            cell(row, member.name);
            cell(row, member.status || '—');
            cell(row, member.location || '—');
            body.append(row);
        }
        $('fbnhMemberPager').hidden = records.length <= MEMBER_PAGE_SIZE;
        $('fbnhMemberPagerInfo').textContent = `Showing ${start + 1}-${Math.min(start + MEMBER_PAGE_SIZE, records.length)} of ${records.length}`;
        $('fbnhMemberPrev').disabled = memberState.page <= 1;
        $('fbnhMemberNext').disabled = memberState.page >= pages;
    }

    async function loadMembers(group, gender) {
        const body = $('fbnhMemberBody');
        const genderLabel = gender === 'all' ? 'All' : (gender === 'male' ? 'Male' : 'Female');
        lastFocus = document.activeElement;
        memberState = null;
        $('fbnhMemberTitle').textContent = `${genderLabel} records in ${group.label}`;
        $('fbnhMemberCount').textContent = 'Loading matching records…';
        $('fbnhMemberPager').hidden = true;
        skeletonRows(body, 3);
        modal.hidden = false;
        $('fbnhMemberClose').focus();
        const signal = begin('members');
        try {
            const params = query({
                purok_ids: group.purok_id === null ? purokCheckboxes.filter(option => option.checked).map(option => option.value) : [String(group.purok_id)],
                grupo: group.grupo ?? '',
                gender,
            });
            const data = await requestJson(`${app.dataset.membersUrl}?${params}`, { signal });
            $('fbnhMemberCount').textContent = `${data.count} active record${data.count === 1 ? '' : 's'}`;
            memberState = { records: data.records, page: 1 };
            renderMembers();
        } catch (error) {
            if (isAbort(error)) return;
            $('fbnhMemberCount').textContent = '';
            clear(body);
            messageRow(body, 3, 'Could not load records', error.message);
        }
    }

    function closeModal(restoreFocus = true) {
        if (modal.hidden) return;
        controllers.members?.abort();
        modal.hidden = true;
        if (restoreFocus && lastFocus && lastFocus.isConnected) lastFocus.focus();
    }

    /* ---------------- BNH Form Helper ---------------- */
    function verdict(isValid1, isValid2) {
        const qualified = isValid1 && isValid2;
        const badge = document.createElement('span');
        badge.className = `fbnh-verdict ${qualified ? 'qualified' : 'not-qualified'}`;
        badge.textContent = qualified ? 'Qualified' : 'Not qualified';
        return badge;
    }

    function setRowMessage(row, text, type) {
        const node = row.querySelector('.fbnh-rowmsg');
        node.textContent = text;
        node.className = 'fbnh-rowmsg' + (type ? ` is-${type}` : '');
        if (type === 'ok') setTimeout(() => { if (node.isConnected && node.textContent === text) { node.textContent = ''; node.className = 'fbnh-rowmsg'; } }, 3000);
    }

    async function saveChecks(record, row, checkbox, previousState) {
        const checkboxes = row.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(input => { input.disabled = true; });
        setRowMessage(row, 'Saving…', 'saving');
        const values = {
            is_valid1: row.querySelector('[data-check="is_valid1"]').checked ? 1 : 0,
            is_valid2: row.querySelector('[data-check="is_valid2"]').checked ? 1 : 0,
        };
        try {
            await requestJson(`${app.dataset.checksUrl}/${record.id}/checks`, { method: 'PUT', body: JSON.stringify(values) });
            record.is_valid1 = Boolean(values.is_valid1);
            record.is_valid2 = Boolean(values.is_valid2);
            row.querySelector('[data-verdict]').firstElementChild.replaceWith(verdict(record.is_valid1, record.is_valid2));
            setRowMessage(row, 'Saved', 'ok');
            setStatus('fbnhRecordsStatus');
            // The resumen depends on these checks; it reloads the next time it is opened.
            stale.resumen = true;
            countsData = null;
        } catch (error) {
            checkbox.checked = previousState;
            setRowMessage(row, '', '');
            setStatus('fbnhRecordsStatus', `${error.message} Your change was not saved.`, true);
        } finally {
            checkboxes.forEach(input => { input.disabled = false; });
        }
    }

    async function loadBnhRecords() {
        const body = $('fbnhRecordsBody');
        const signal = begin('list');
        setStatus('fbnhRecordsStatus');
        if (!hasSelection()) {
            showNoSelection(body, 4);
            $('fbnhPageInfo').textContent = '';
            $('fbnhPrevious').disabled = true;
            $('fbnhNext').disabled = true;
            return;
        }
        skeletonRows(body, 4);
        $('fbnhPrevious').disabled = true;
        $('fbnhNext').disabled = true;
        try {
            const data = await requestJson(`${app.dataset.bnhUrl}?${query({ page: currentPage, q: searchInput.value.trim() })}`, { signal });
            lastPage = data.last_page;
            clear(body);
            $('fbnhPageInfo').textContent = `Page ${data.page} of ${data.last_page} · ${data.total} records`;
            $('fbnhPrevious').disabled = data.page <= 1;
            $('fbnhNext').disabled = data.page >= data.last_page;
            if (!data.records.length) {
                const searching = searchInput.value.trim() !== '';
                messageRow(body, 4, 'No matching records', searching ? 'No active records match that name. Try a different search.' : 'No active records match the selected filters.');
                return;
            }
            for (const record of data.records) {
                const row = document.createElement('tr');
                const memberCell = document.createElement('td');
                memberCell.className = 'fbnh-member';
                const memberName = document.createElement('span');
                memberName.className = 'fbnh-member-name';
                memberName.textContent = record.name;
                const memberMeta = document.createElement('span');
                memberMeta.className = 'fbnh-member-meta';
                for (const text of [record.status || '—', record.location || '—']) {
                    const item = document.createElement('span');
                    item.textContent = text;
                    memberMeta.append(item);
                }
                memberCell.append(memberName, memberMeta);
                row.append(memberCell);
                for (const key of ['is_valid1', 'is_valid2']) {
                    const td = document.createElement('td');
                    td.className = 'number fbnh-checkcell';
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'fbnh-cb fbnh-check';
                    checkbox.dataset.check = key;
                    checkbox.checked = record[key];
                    checkbox.setAttribute('aria-label', `${key === 'is_valid1' ? 'Salaysay / Pakikipag-usap' : 'Paghahanda'} for ${record.name}`);
                    checkbox.addEventListener('change', () => saveChecks(record, row, checkbox, !checkbox.checked));
                    td.append(checkbox);
                    row.append(td);
                }
                const verdictCell = document.createElement('td');
                verdictCell.dataset.verdict = 'true';
                const message = document.createElement('span');
                message.className = 'fbnh-rowmsg';
                message.setAttribute('role', 'status');
                verdictCell.append(verdict(record.is_valid1, record.is_valid2), message);
                row.append(verdictCell);
                body.append(row);
            }
        } catch (error) {
            if (isAbort(error)) return;
            stale.list = true;
            clear(body);
            messageRow(body, 4, 'Could not load BNH records', error.message);
            setStatus('fbnhRecordsStatus', error.message, true);
        }
    }

    /* ---------------- Resumen ---------------- */
    const countsMode = () => showBothRadio.checked ? 'both' : (showQualifiedRadio.checked ? 'qualified' : 'minuses');

    function renderCountsHead(showQualified, showMinuses) {
        const head = $('fbnhCountsHead');
        head.replaceChildren();
        const groupHeader = document.createElement('tr');
        const labelHeader = document.createElement('th');
        labelHeader.textContent = 'Purok-Grupo';
        labelHeader.rowSpan = 2;
        groupHeader.append(labelHeader);
        if (showQualified) {
            const th = document.createElement('th');
            th.className = 'fbnh-summary-group';
            th.colSpan = 3;
            th.textContent = 'Qualified for BNH';
            groupHeader.append(th);
        }
        if (showMinuses) {
            const th = document.createElement('th');
            th.className = 'fbnh-summary-minus';
            th.colSpan = 3;
            th.textContent = 'Total minuses';
            groupHeader.append(th);
        }
        head.append(groupHeader);
        const countHeader = document.createElement('tr');
        for (const show of [showQualified, showMinuses]) {
            if (!show) continue;
            for (const label of ['L', 'B', 'Total']) {
                const th = document.createElement('th');
                th.className = 'number';
                th.textContent = label;
                countHeader.append(th);
            }
        }
        head.append(countHeader);
    }

    // Switching "Qualified / Minuses / Both" only redraws the data already loaded; it never calls the server.
    function renderCounts() {
        const body = $('fbnhCountsBody');
        const mode = countsMode();
        const showQualified = mode !== 'minuses', showMinuses = mode !== 'qualified';
        const cols = 1 + (showQualified ? 3 : 0) + (showMinuses ? 3 : 0);
        document.querySelector('.fbnh-counts-table').dataset.countMode = mode;
        renderCountsHead(showQualified, showMinuses);
        clear(body);
        if (!hasSelection()) { showNoSelection(body, cols); return; }
        if (countsError) { messageRow(body, cols, 'Could not load counts', countsError); return; }
        if (!countsData) { if (countsLoading) skeletonRows(body, cols); return; }
        if (!countsData.length) { messageRow(body, cols, 'No groups to show', 'No configured Purok groups match the selected filters.'); return; }
        for (const item of countsData) {
            const row = document.createElement('tr');
            row.className = item.type === 'grand_total' ? 'is-total' : (item.type === 'purok_total' ? 'is-subtotal' : '');
            cell(row, item.label);
            if (showQualified) {
                cell(row, String(item.male), 'number');
                cell(row, String(item.female), 'number');
                cell(row, String(item.total), 'number');
            }
            if (showMinuses) {
                cell(row, String(item.minuses_male), 'number');
                cell(row, String(item.minuses_female), 'number');
                cell(row, String(item.minuses_total), 'number');
            }
            body.append(row);
        }
    }

    async function loadQualifiedCounts() {
        const signal = begin('resumen');
        countsData = null; countsError = ''; countsLoading = hasSelection();
        setStatus('fbnhCountsStatus');
        renderCounts();
        if (!countsLoading) return;
        try {
            const data = await requestJson(`${app.dataset.countsUrl}?${query()}`, { signal });
            countsData = data.rows;
            countsLoading = false;
            renderCounts();
        } catch (error) {
            if (isAbort(error)) return;
            countsLoading = false;
            countsError = error.message;
            stale.resumen = true;
            renderCounts();
            setStatus('fbnhCountsStatus', error.message, true);
        }
    }

    /* ---------------- Downloads (real result, no fake success) ---------------- */
    async function download(button, url, fallbackName, statusId) {
        if (button.dataset.busy === '1') return;
        const label = button.querySelector('.fbnh-btn-label');
        const original = label.textContent;
        button.dataset.busy = '1';
        button.disabled = true;
        button.classList.add('is-loading');
        label.textContent = 'Preparing…';
        setStatus(statusId);
        try {
            const response = await fetch(url, { credentials: 'same-origin' });
            if (!response.ok) {
                let message = `The download failed (error ${response.status}).`;
                try { const payload = await response.json(); message = payload.message || Object.values(payload.errors || {}).flat()[0] || message; } catch (error) { /* keep default */ }
                throw new Error(message);
            }
            if ((response.headers.get('Content-Type') || '').includes('text/html')) {
                throw new Error('The server did not return a file. Reload the page and try again.');
            }
            const blob = await response.blob();
            let name = fallbackName;
            const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(response.headers.get('Content-Disposition') || '');
            if (match) { try { name = decodeURIComponent(match[1]); } catch (error) { name = match[1]; } }
            const href = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = href;
            link.download = name;
            document.body.append(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(href), 1000);
            setStatus(statusId, `Downloaded ${name}.`, false, true);
        } catch (error) {
            setStatus(statusId, error.message || 'The download failed.', true);
        } finally {
            button.dataset.busy = '0';
            button.classList.remove('is-loading');
            label.textContent = original;
            updateDownloadState();
        }
    }

    function updateDownloadState() {
        for (const id of ['fbnhDownloadList', 'fbnhDownloadCounts']) {
            const button = $(id);
            button.disabled = !hasSelection() || button.dataset.busy === '1';
        }
    }

    $('fbnhDownloadList').addEventListener('click', () => download(
        $('fbnhDownloadList'), `${app.dataset.wordUrl}?${query({ q: searchInput.value.trim() })}`, 'BNH-Form.docx', 'fbnhExportStatus'));
    $('fbnhDownloadCounts').addEventListener('click', () => download(
        $('fbnhDownloadCounts'), `${app.dataset.countsWordUrl}?${query({
            show_qualified: countsMode() !== 'minuses' ? '1' : '0',
            show_minuses: countsMode() !== 'qualified' ? '1' : '0',
        })}`, 'BNH-Resumen.docx', 'fbnhCountsStatus'));

    /* ---------------- Tabs: load only what is visible ---------------- */
    const loaders = { matrix: loadMatrix, list: loadBnhRecords, resumen: loadQualifiedCounts };
    const markAllStale = () => { stale.matrix = stale.list = stale.resumen = true; countsData = null; };
    function ensureLoaded(key) {
        if (!stale[key]) return Promise.resolve();
        stale[key] = false;
        return loaders[key]();
    }

    const tabs = Array.from(document.querySelectorAll('.fbnh-tab'));
    function selectTab(tab) {
        activeTab = tab.dataset.tab;
        tabs.forEach(button => {
            const selected = button === tab;
            button.setAttribute('aria-selected', String(selected));
            button.tabIndex = selected ? 0 : -1;
            $(button.getAttribute('aria-controls')).hidden = !selected;
        });
        ensureLoaded(activeTab);
    }
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => selectTab(tab));
        tab.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0
                : event.key === 'End' ? tabs.length - 1
                    : (index + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length;
            tabs[next].focus();
            selectTab(tabs[next]);
        });
    });

    /* ---------------- Filters: "Add Filter" menu and pills ---------------- */
    const menu = $('fbnhMenu'), addFilterBtn = $('fbnhAddFilter'), filtersHost = $('fbnhFilters');
    const categories = {
        purok: { label: 'Purok', boxes: purokCheckboxes, nameOf: purokName, view: $('fbnhViewPurok'), summary: $('fbnhCatPurokValue') },
        status: { label: 'Kalagayan', boxes: availableStatusCheckboxes, nameOf: c => c.value, view: $('fbnhViewStatus'), summary: $('fbnhCatStatusValue') },
    };
    let menuView = 'root';

    function showView(name) {
        menuView = name;
        $('fbnhViewRoot').hidden = name !== 'root';
        categories.purok.view.hidden = name !== 'purok';
        categories.status.view.hidden = name !== 'status';
    }
    function placeMenu(anchor) {
        const host = filtersHost.getBoundingClientRect(), a = anchor.getBoundingClientRect();
        const left = Math.max(0, Math.min(a.left - host.left, host.width - menu.offsetWidth));
        menu.style.left = `${left}px`;
        menu.style.top = `${a.bottom - host.top + 6}px`;
    }
    function focusView(name) {
        if (name === 'root') $('fbnhMenuSearch').focus();
        else if (name === 'purok') $('fbnhPurokFilter').focus();
        else categories.status.view.querySelector('input:not(:disabled)')?.focus();
    }
    function openMenu(name, anchor) {
        menu.hidden = false;
        showView(name);
        placeMenu(anchor);
        addFilterBtn.setAttribute('aria-expanded', 'true');
        focusView(name);
    }
    function closeMenu(returnFocus = true) {
        if (menu.hidden) return;
        const view = menuView;
        menu.hidden = true;
        addFilterBtn.setAttribute('aria-expanded', 'false');
        $('fbnhMenuSearch').value = '';
        filterMenuRows();
        if (!returnFocus) return;
        const pill = view !== 'root' ? document.querySelector(`.fbnh-pill-main[data-cat="${view}"]`) : null;
        (pill || addFilterBtn).focus();
    }
    function filterMenuRows() {
        const term = $('fbnhMenuSearch').value.trim().toLocaleLowerCase();
        let visible = 0;
        document.querySelectorAll('.fbnh-menu-row[data-cat]').forEach(row => {
            const match = !term || categories[row.dataset.cat].label.toLocaleLowerCase().includes(term);
            row.hidden = !match;
            if (match) visible++;
        });
        $('fbnhMenuNone').hidden = visible > 0;
    }

    addFilterBtn.addEventListener('click', () => (!menu.hidden && menuView === 'root' ? closeMenu() : openMenu('root', addFilterBtn)));
    $('fbnhMenuSearch').addEventListener('input', filterMenuRows);
    $('fbnhMenuSearch').addEventListener('keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); document.querySelector('.fbnh-menu-row[data-cat]:not([hidden])')?.click(); }
    });
    document.querySelectorAll('.fbnh-menu-row[data-cat]').forEach(row => row.addEventListener('click', () => { showView(row.dataset.cat); focusView(row.dataset.cat); }));
    document.querySelectorAll('[data-back]').forEach(button => button.addEventListener('click', () => { showView('root'); focusView('root'); }));
    document.addEventListener('click', event => {
        if (!menu.hidden && !event.target.closest('#fbnhMenu, #fbnhAddFilter, .fbnh-pill-main')) closeMenu(false);
    });

    $('fbnhPurokFilter').addEventListener('input', event => {
        const term = event.target.value.trim().toLocaleLowerCase();
        let visible = 0;
        purokCheckboxes.forEach(checkbox => {
            const match = !term || purokName(checkbox).toLocaleLowerCase().includes(term);
            checkbox.closest('label').hidden = !match;
            if (match) visible++;
        });
        $('fbnhPurokNone').hidden = visible > 0;
    });

    function syncAll() {
        const puroks = purokCheckboxes.filter(c => c.checked).length;
        allPuroksCheckbox.checked = puroks === purokCheckboxes.length;
        allPuroksCheckbox.indeterminate = puroks > 0 && puroks < purokCheckboxes.length;
        const statuses = availableStatusCheckboxes.filter(c => c.checked).length;
        allStatusesCheckbox.checked = statuses === availableStatusCheckboxes.length;
        allStatusesCheckbox.indeterminate = statuses > 0 && statuses < availableStatusCheckboxes.length;
    }

    function valueText(cat, selected) {
        if (!selected.length) return 'None';
        if (selected.length <= 2) return selected.map(cat.nameOf).join(', ');
        return `${selected.length} selected`;
    }

    // A pill exists only for a filter that narrows the list. Click it to edit, or use the x to remove it.
    function renderPills() {
        const box = $('fbnhPills');
        box.replaceChildren();
        let active = 0;
        for (const [key, cat] of Object.entries(categories)) {
            const selected = cat.boxes.filter(c => c.checked);
            const isAll = selected.length === cat.boxes.length;
            cat.summary.textContent = isAll ? 'All' : valueText(cat, selected);
            if (isAll) continue;
            active++;
            const text = valueText(cat, selected);
            const pill = document.createElement('span');
            pill.className = 'fbnh-pill';
            const main = document.createElement('button');
            main.type = 'button';
            main.className = 'fbnh-pill-main';
            main.dataset.cat = key;
            main.setAttribute('aria-haspopup', 'true');
            main.setAttribute('aria-label', `${cat.label}: ${text}. Edit filter`);
            const k = document.createElement('span'); k.className = 'fbnh-pill-k'; k.textContent = cat.label;
            const v = document.createElement('span'); v.className = 'fbnh-pill-v'; v.textContent = text;
            main.append(k, v);
            main.addEventListener('click', () => (!menu.hidden && menuView === key ? closeMenu() : openMenu(key, main)));
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'fbnh-pill-x';
            remove.setAttribute('aria-label', `Remove ${cat.label} filter`);
            remove.innerHTML = '<svg viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M2 2l6 6M8 2l-6 6"/></svg>';
            remove.addEventListener('click', () => { cat.boxes.forEach(c => { c.checked = true; }); closeMenu(false); filtersChanged(); });
            pill.append(main, remove);
            box.append(pill);
        }
        $('fbnhReset').hidden = active === 0;
    }

    function updateFilterSummary() {
        const selectedPuroks = purokCheckboxes.filter(c => c.checked);
        const selectedStatuses = availableStatusCheckboxes.filter(c => c.checked);
        const purokText = selectedPuroks.length === purokCheckboxes.length ? 'all Puroks' : `${selectedPuroks.length} of ${purokCheckboxes.length} Puroks`;
        const statusText = selectedStatuses.length ? selectedStatuses.map(c => c.value).join(', ') : 'none selected';
        $('fbnhScope').textContent = hasSelection()
            ? `Using ${purokText}, Kalagayan: ${statusText}. Change the filters above to update this list.`
            : NO_SELECTION;
        setStatus('fbnhPageStatus', hasSelection() ? '' : NO_SELECTION);
        renderPills();
        updateDownloadState();
    }

    $('fbnhReset').addEventListener('click', () => {
        purokCheckboxes.forEach(c => { c.checked = true; });
        availableStatusCheckboxes.forEach(c => { c.checked = true; });
        closeMenu(false);
        filtersChanged();
    });

    // The UI updates at once; the data request waits a moment so rapid clicks send one request, not several.
    function filtersChanged() {
        currentPage = 1;
        syncAll();
        updateFilterSummary();
        markAllStale();
        closeModal(false);
        window.clearTimeout(filterTimer);
        filterTimer = window.setTimeout(() => ensureLoaded(activeTab), 200);
    }

    allPuroksCheckbox.addEventListener('change', () => { purokCheckboxes.forEach(c => { c.checked = allPuroksCheckbox.checked; }); filtersChanged(); });
    purokCheckboxes.forEach(c => c.addEventListener('change', filtersChanged));
    allStatusesCheckbox.addEventListener('change', () => { availableStatusCheckboxes.forEach(c => { c.checked = allStatusesCheckbox.checked; }); filtersChanged(); });
    availableStatusCheckboxes.forEach(c => c.addEventListener('change', filtersChanged));

    /* ---------------- Reload, search, paging, resumen mode ---------------- */
    $('fbnhReload').addEventListener('click', async () => {
        const button = $('fbnhReload');
        const label = $('fbnhReloadLabel');
        button.disabled = true;
        button.classList.add('is-loading');
        label.textContent = 'Reloading…';
        markAllStale();
        try { await ensureLoaded(activeTab); }
        finally {
            button.disabled = false;
            button.classList.remove('is-loading');
            label.textContent = 'Reload data';
        }
    });

    [showQualifiedRadio, showMinusesRadio, showBothRadio].forEach(radio => radio.addEventListener('change', renderCounts));

    searchInput.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => { currentPage = 1; stale.list = false; loadBnhRecords(); }, 250);
    });
    $('fbnhPrevious').addEventListener('click', () => { if (currentPage > 1) { currentPage--; loadBnhRecords(); } });
    $('fbnhNext').addEventListener('click', () => { if (currentPage < lastPage) { currentPage++; loadBnhRecords(); } });

    /* ---------------- Dialog behavior ---------------- */
    $('fbnhMemberClose').addEventListener('click', () => closeModal());
    modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
    $('fbnhMemberPrev').addEventListener('click', () => { if (memberState && memberState.page > 1) { memberState.page--; renderMembers(); } });
    $('fbnhMemberNext').addEventListener('click', () => { if (memberState) { memberState.page++; renderMembers(); } });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            if (!modal.hidden) { closeModal(); return; }
            if (!menu.hidden) closeMenu();
            return;
        }
        if (event.key === 'Tab' && !modal.hidden) {
            const focusable = Array.from(modal.querySelectorAll('button:not(:disabled), [href], input:not(:disabled)')).filter(node => node.offsetParent !== null);
            if (!focusable.length) return;
            const first = focusable[0], last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });

    /* ---------------- Start ---------------- */
    syncAll();
    updateFilterSummary();
    renderCountsHead(true, true);
    ensureLoaded('matrix');
})();
</script>
@endsection