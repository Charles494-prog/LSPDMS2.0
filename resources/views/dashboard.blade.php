@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="dash" id="dashboardApp" data-can-edit-registry="{{ $canEditRegistry ? 'true' : 'false' }}">
    <header class="dash-head">
        <div>
            <h1>Dashboard</h1>
            <p class="dash-lede">{{ $globalVariables?->locale ?: 'Records' }} · Active records and updating progress</p>
        </div>
        <button type="button" class="primary-btn" id="open-baptisee" aria-haspopup="dialog">
            New Baptisee <span class="badge" id="bap-count"></span>
        </button>
    </header>

    {{-- Hero / summary --}}
    <div class="hero-wrap" id="hero-wrap" data-state="loading" aria-busy="true" aria-labelledby="hero-label">
        <div class="hero sk-hero" data-skeleton aria-hidden="true">
            <div><span class="sk" style="height:3.6rem;width:8rem"></span><span class="sk" style="height:.9rem;width:6rem;margin-top:.7rem"></span></div>
            <div><span class="sk" style="height:22px;width:100%"></span><span class="sk" style="height:.9rem;width:70%;margin-top:.9rem"></span></div>
            <div class="hero-side"><span class="sk" style="height:2rem;width:4rem"></span><span class="sk" style="height:.8rem;width:3.5rem;margin-top:.5rem"></span></div>
        </div>
        <section class="hero" data-content>
            <div class="hero-num">
                <span class="hero-value" id="hero-total">—</span>
                <span class="hero-label" id="hero-label">active records</span>
            </div>
            <div class="hero-split">
                <div class="split-bar" id="split-bar" role="img" aria-label="Active records by kalagayan"></div>
                <ul class="split-legend" id="split-legend"></ul>
            </div>
            <div class="hero-side">
                <span class="hero-side-value" id="hero-inactive">—</span>
                <span class="hero-side-label">inactive</span>
            </div>
        </section>
    </div>

    <div class="grid">
        <section class="panel area-purok" aria-labelledby="purok-title">
            <h2 id="purok-title">Active records by purok</h2>
            <div id="purok-host" data-state="loading" aria-busy="true">
                <div data-skeleton aria-hidden="true" class="sk-stack">
                    <span class="sk" style="height:28px"></span><span class="sk" style="height:28px;width:86%"></span><span class="sk" style="height:28px;width:72%"></span>
                    <span class="sk" style="height:28px;width:60%"></span><span class="sk" style="height:28px;width:44%"></span><span class="sk" style="height:28px;width:30%"></span>
                </div>
                <div data-content>
                    <div class="purok-bars" id="purok-chart-box" role="group" aria-label="Active records by Purok"></div>
                    <p class="purok-chart-detail" id="purok-chart-detail" role="status" aria-live="polite">Select a Purok bar to see its MS, UWP, MK, and total counts.</p>
                    <ul class="legend" id="status-legend"></ul>
                </div>
            </div>
        </section>

        <section class="panel area-progress" aria-labelledby="monitor-title">
            <h2 id="monitor-title">Updating progress</h2>

            <div id="gauges-host" data-state="loading" aria-busy="true">
                <div data-skeleton aria-hidden="true" class="gauges">
                    <div class="gauge"><span class="sk sk-round"></span><span class="sk" style="height:.85rem;width:70%;margin:.6rem auto 0"></span></div>
                    <div class="gauge"><span class="sk sk-round"></span><span class="sk" style="height:.85rem;width:70%;margin:.6rem auto 0"></span></div>
                    <div class="gauge"><span class="sk sk-round"></span><span class="sk" style="height:.85rem;width:70%;margin:.6rem auto 0"></span></div>
                </div>
                <div data-content class="gauges">
                    <figure class="gauge" data-value="{{ isset($dashboardMetrics['r210Updating']) ? number_format($dashboardMetrics['r210Updating'], 2, '.', '') : '' }}">
                        <svg viewBox="0 0 120 120" role="img" aria-label="R2-10 endorsement completion"><circle class="g-track" cx="60" cy="60" r="50"/><circle class="g-bar" cx="60" cy="60" r="50" transform="rotate(-90 60 60)"/><text class="g-text" x="60" y="60">—</text></svg>
                        <figcaption>R2-10 Updating</figcaption>
                    </figure>
                    <figure class="gauge" data-value="{{ isset($dashboardMetrics['registryCompletion']) ? number_format($dashboardMetrics['registryCompletion'], 2, '.', '') : '' }}">
                        <button type="button" class="gauge-btn" id="open-registry" aria-haspopup="dialog">
                            <svg viewBox="0 0 120 120" role="img" aria-label="Registry Updating"><circle class="g-track" cx="60" cy="60" r="50"/><circle class="g-bar" cx="60" cy="60" r="50" transform="rotate(-90 60 60)"/><text class="g-text" x="60" y="60">—</text></svg>
                            <span class="cap">Registry Updating</span>
                            <span class="cap-hint">See what's missing</span>
                        </button>
                    </figure>
                    <figure class="gauge" data-value="{{ isset($dashboardMetrics['localeActivePercentage']) ? number_format($dashboardMetrics['localeActivePercentage'], 2, '.', '') : '' }}">
                        <svg viewBox="0 0 120 120" role="img" aria-label="Active records as a percentage of Locale S Number"><circle class="g-track" cx="60" cy="60" r="50"/><circle class="g-bar" cx="60" cy="60" r="50" transform="rotate(-90 60 60)"/><text class="g-text" x="60" y="60">—</text></svg>
                        <figcaption>LSP / Locale</figcaption>
                    </figure>
                </div>
            </div>

            <div class="kap" id="cfo-a-host" data-state="loading" aria-busy="true">
                <div data-skeleton aria-hidden="true" class="sk-stack">
                    <span class="sk" style="height:1.6rem;width:100%"></span>
                    <span class="sk" style="height:.8rem;width:35%"></span>
                    <span class="sk" style="height:30px"></span><span class="sk" style="height:30px"></span><span class="sk" style="height:30px"></span>
                </div>
                <div data-content class="kap-inner">
                    <div class="kap-head">
                        <h3>Breakdown per CFO group</h3>
                        <div class="seg" role="radiogroup" aria-label="Filter by Kalagayan" id="cfo-status-seg"></div>
                    </div>
                    <p class="kap-total" id="cfo-group-total" role="status" aria-live="polite"></p>
                    <div class="kap-rows" id="cfo-group-rows"></div>
                </div>
            </div>

            <div class="kap" id="cfo-b-host" data-state="loading" aria-busy="true">
                <div data-skeleton aria-hidden="true" class="sk-stack">
                    <span class="sk" style="height:1.6rem;width:100%"></span>
                    <span class="sk" style="height:.8rem;width:35%"></span>
                    <div class="kap-stats"><span class="sk" style="height:76px"></span><span class="sk" style="height:76px"></span><span class="sk" style="height:76px"></span></div>
                </div>
                <div data-content class="kap-inner">
                    <div class="kap-head">
                        <h3>Kalagayan per CFO group</h3>
                        <div class="seg" role="radiogroup" aria-label="Filter by CFO group" id="cfo-group-seg"></div>
                    </div>
                    <p class="kap-total" id="cfo-status-total" role="status" aria-live="polite"></p>
                    <ul class="kap-stats" id="cfo-status-stats"></ul>
                </div>
            </div>
        </section>

        <section class="panel area-gender" aria-labelledby="gender-title">
            <h2 id="gender-title">Active records by gender</h2>
            <div id="gender-host" data-state="loading" aria-busy="true">
                <div data-skeleton aria-hidden="true" class="sk-stack">
                    <span class="sk" style="height:1.6rem;width:30%"></span><span class="sk" style="height:14px"></span>
                    <span class="sk" style="height:1.6rem;width:30%;margin-top:.5rem"></span><span class="sk" style="height:14px"></span>
                </div>
                <div data-content>
                    <div class="gender-row">
                        <div class="gender-top"><button type="button" class="gender-drill" data-gender="female">Female <span aria-hidden="true">↗</span></button><strong id="pct-female">—</strong></div>
                        <div class="gbar" id="bar-female" role="img" aria-label="Female share of active records"></div>
                    </div>
                    <div class="gender-row">
                        <div class="gender-top"><button type="button" class="gender-drill" data-gender="male">Male <span aria-hidden="true">↗</span></button><strong id="pct-male">—</strong></div>
                        <div class="gbar" id="bar-male" role="img" aria-label="Male share of active records"></div>
                    </div>
                    <ul class="legend" id="gender-legend"></ul>
                </div>
            </div>
        </section>

        <dialog class="modal" id="cfo-group-modal" aria-labelledby="cfo-group-title">
            <div class="modal-head">
                <div><h2 id="cfo-group-title">CFO group records</h2><p id="cfo-group-sub"></p></div>
                <button type="button" class="modal-x" data-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body" id="cfo-group-body"></div>
        </dialog>

        <dialog class="modal" id="gender-records-modal" aria-labelledby="gender-records-title">
            <div class="modal-head">
                <div><h2 id="gender-records-title">Active records</h2><p id="gender-records-sub"></p></div>
                <button type="button" class="modal-x" data-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body" id="gender-records-body"></div>
        </dialog>

        {{-- Records over time (rendered lazily when scrolled near) --}}
        <section class="panel span-12 ot-panel" id="ot-panel" data-state="loading" aria-busy="true" aria-labelledby="ot-title">
            <div data-skeleton aria-hidden="true">
                <div class="panel-row"><div><span class="sk" style="height:1.1rem;width:11rem"></span><span class="sk" style="height:.8rem;width:22rem;max-width:100%;margin-top:.5rem"></span></div></div>
                <div class="ot">
                    <span class="sk" style="height:360px"></span>
                    <div class="filters"><span class="sk" style="height:7rem"></span><span class="sk" style="height:7rem"></span></div>
                </div>
            </div>
            <div data-content>
                <div class="panel-row">
                    <div><h2 id="ot-title">Records over time</h2><p class="ot-help">Monthly MS, UWP, and MK snapshots. Select a point to view its movement codes.</p></div>
                    <ul class="legend" id="ot-legend"></ul>
                </div>
                <div class="ot-current-row">
                    <p class="ot-current" id="ot-current" role="status" aria-live="polite"></p>
                    <button type="button" class="link-btn" id="ot-reset" hidden>Reset filters</button>
                </div>
                <div class="ot">
                    <div class="ot-chart">
                        <canvas id="ot-chart" role="img" aria-label="Records over time"></canvas>
                        <p class="ot-empty" id="ot-empty" hidden>No data for the selected period.</p>
                    </div>
                    <aside class="filters">
                        <div class="filter-group">
                            <div class="filter-head">
                                <span>Month</span>
                                <button type="button" class="link-btn" id="month-clear">All periods</button>
                            </div>
                            <div class="chips months" id="tl-months" role="group" aria-label="Month filter"></div>
                            <p class="hint" id="month-hint">Select a month, then another to pick a range.</p>
                        </div>
                        <div class="filter-group">
                            <div class="filter-head">
                                <span>Purok</span>
                                <button type="button" class="link-btn" id="purok-clear">All puroks</button>
                            </div>
                            <div class="chips puroks" id="purok-list" role="group" aria-label="Purok filter"></div>
                            <p class="hint">Select several puroks to combine them.</p>
                        </div>
                    </aside>
                </div>
                <p class="hint ot-note" id="ot-note" role="status" hidden></p>
            </div>
        </section>

        <dialog class="modal movement-code-modal" id="movement-code-modal" aria-labelledby="movement-code-title">
            <div class="modal-head">
                <div><h2 id="movement-code-title">Monthly movement codes</h2><p id="movement-code-sub"></p></div>
                <button type="button" class="modal-x" data-close aria-label="Close">&times;</button>
            </div>
            <div class="modal-body" id="movement-code-body"></div>
        </dialog>
    </div>

    <dialog class="modal" id="baptisee-modal" aria-labelledby="bap-title">
        <div class="modal-head">
            <div><h2 id="bap-title">New baptisees</h2><p id="bap-sub"></p></div>
            <button type="button" class="modal-x" data-close aria-label="Close">&times;</button>
        </div>
        <div class="modal-body" id="bap-body"></div>
    </dialog>

    <dialog class="modal modal-wide" id="registry-modal" aria-labelledby="reg-title">
        <div class="modal-head">
            <div><h2 id="reg-title">Registry updating monitoring</h2><p>MS, UWP, and MK records only. Blank fields and Registry values of RFID are counted as missing. Click any count to view the records.@unless ($canEditRegistry) Editing is disabled for this account.@endunless</p></div>
            <button type="button" class="modal-x" data-close aria-label="Close">&times;</button>
        </div>
        <div class="modal-body" id="reg-body"></div>
    </dialog>

    <dialog class="modal" id="registry-details-modal" aria-labelledby="registry-details-title">
        <div class="modal-head">
            <div><h2 id="registry-details-title">Records missing data</h2><p id="registry-details-sub"></p></div>
            <button type="button" class="modal-x" data-close aria-label="Close">&times;</button>
        </div>
        <p class="registry-details-status" id="registry-details-status" role="status" aria-live="polite" hidden></p>
        <div class="modal-body" id="registry-details-body"></div>
    </dialog>

    <dialog class="modal registry-edit-modal" id="registry-edit-modal" aria-labelledby="registry-edit-title">
        <div class="modal-head">
            <div>
                <h2 id="registry-edit-title">Enter missing data</h2>
                <p id="registry-edit-sub"></p>
            </div>
            <button type="button" class="modal-x" data-close aria-label="Close">&times;</button>
        </div>
        <form class="modal-body registry-edit-form" id="registry-edit-form" novalidate>
            <label class="registry-edit-label" id="registry-edit-label" for="registry-edit-value"></label>
            <div id="registry-edit-control"></div>
            <p class="registry-edit-hint" id="registry-edit-hint"></p>
            <p class="registry-edit-error" id="registry-edit-error" role="alert" hidden></p>
            <div class="registry-edit-actions">
                <button type="button" class="btn btn-ghost" data-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="registry-edit-save">Save missing data</button>
            </div>
        </form>
    </dialog>

    <dialog class="modal registry-conflict-modal" id="registry-conflict-modal" aria-labelledby="registry-conflict-title">
        <div class="modal-head">
            <div>
                <h2 id="registry-conflict-title">Duplicate registry number</h2>
                <p id="registry-conflict-sub"></p>
            </div>
            <button type="button" class="modal-x" data-close aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <p class="registry-edit-hint">Change at least one of these so no two records share a registry number. Cancel discards your input.</p>
            <div class="conflict-list" id="registry-conflict-list"></div>
            <div class="registry-edit-actions">
                <button type="button" class="btn btn-ghost" id="registry-conflict-cancel" data-close>Cancel</button>
                <button type="button" class="btn btn-primary" id="registry-conflict-confirm" disabled>Confirm</button>
            </div>
        </div>
    </dialog>
</div>
@endsection

@push('styles')
<style>
    /* Theme: inherits --bg / --surface / --border / --text / --text-muted / --accent / --danger from the layout,
       so it follows the navbar's light/dark switch (html[data-theme]). */
    .dash {
        --card: var(--surface); --ink: var(--text); --muted: var(--text-muted); --line: var(--border);
        --soft: #f5f5f5;
        --binhi: #15803d; --kadiwa: #2563eb; --buklod: #dc2626;
        --ot-bg: var(--card);
        --ms: #16a34a; --uwp: #f97316; --mk: #eab308; --off: #9aa5b1;
        --g-good: #16a34a; --g-mid: #eab308; --g-low: #dc2626;
        max-width: 1500px; margin: 0 auto; padding: 0;
        background: none; color: var(--ink); font-variant-numeric: tabular-nums;
    }
    html[data-theme="dark"] .dash {
        --soft: #171717;
        --off: #6b7280;
        --binhi: #4ade80; --kadiwa: #60a5fa; --buklod: #f87171;
        --g-good: #22c55e; --g-mid: #facc15; --g-low: #f87171;
    }
    .dash h1, .dash h2, .dash p, .dash ul, .dash figure { margin: 0; padding: 0; }
    .dash ul { list-style: none; }

    /* ---------- Section states: loading / ready / error are mutually exclusive ---------- */
    [data-state="loading"] > [data-content], [data-state="loading"] > [data-error] { display: none; }
    [data-state="ready"] > [data-skeleton], [data-state="ready"] > [data-error] { display: none; }
    [data-state="error"] > [data-skeleton], [data-state="error"] > [data-content] { display: none; }
    .state-err { display: grid; justify-items: start; gap: .5rem; padding: 1rem; border: 1px dashed var(--line); border-radius: 8px; color: var(--muted); font-size: .85rem; }
    .state-err strong { color: var(--ink); font-size: .9rem; }
    .retry { padding: .35rem .8rem; border: 1px solid var(--ink); border-radius: 6px; background: transparent; color: var(--ink); font: inherit; font-size: .8rem; font-weight: 600; cursor: pointer; transition: background .15s ease, color .15s ease; }
    .retry:hover { background: var(--ink); color: var(--card); }

    /* Skeletons: same dimensions as the real components, subtle shimmer */
    .sk { display: block; border-radius: 6px; background: linear-gradient(90deg, var(--soft) 25%, var(--line) 37%, var(--soft) 63%); background-size: 400% 100%; animation: sk-shimmer 1.4s ease infinite; }
    .sk-round { width: min(100%, 140px); aspect-ratio: 1; margin: 0 auto; border-radius: 50%; }
    .sk-stack { display: grid; gap: .5rem; }
    @keyframes sk-shimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }

    .dash-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.1rem; }
    .dash-head h1 { font-size: clamp(1.7rem, 3.5vw, 2.3rem); font-weight: 700; letter-spacing: -.035em; line-height: 1.05; }
    .dash-lede { margin-top: .3rem; color: var(--muted); font-size: .92rem; }
    .primary-btn { display: inline-flex; align-items: center; gap: .6rem; height: 38px; padding: 0 1rem; border: 1px solid var(--ink); border-radius: 6px; background: var(--ink); color: var(--card); font: inherit; font-size: .875rem; font-weight: 600; cursor: pointer; transition: opacity .15s ease; }
    .primary-btn:hover { opacity: .85; }
    .badge { min-width: 1.4rem; padding: .05rem .45rem; border-radius: 99px; background: var(--card); color: var(--ink); font-size: .75rem; font-weight: 700; text-align: center; }
    .badge:empty { display: none; }

    .gauge-btn { display: block; width: 100%; padding: .4rem; border: 1px solid transparent; border-radius: 8px; background: none; color: inherit; font: inherit; cursor: pointer; transition: background .15s ease, border-color .15s ease; }
    .gauge-btn:hover { border-color: var(--line); background: var(--soft); }
    .cap { display: block; margin-top: .5rem; font-size: .85rem; font-weight: 600; }
    .cap-hint { display: block; margin-top: .1rem; color: var(--ink); font-size: .75rem; font-weight: 600; text-decoration: underline; text-underline-offset: 3px; }

    /* Modals */
    .modal { width: min(900px, calc(100vw - 2rem)); max-height: calc(100vh - 2rem); padding: 0; border: 1px solid var(--line); border-radius: 10px; background: var(--card); color: var(--ink); box-shadow: 0 24px 60px rgba(0,0,0,.35); overflow: hidden; }
    .modal-wide { width: min(1140px, calc(100vw - 2rem)); }
    .movement-code-modal { width: min(760px, calc(100vw - 2rem)); max-height: calc(100dvh - 2rem); }
    .movement-code-modal .modal-head { padding: .9rem 1.1rem; }
    .movement-code-modal .modal-body { display: grid; gap: .8rem; padding: 1rem 1.1rem; }
    .movement-code-modal .empty { padding: .8rem; border: 1px solid var(--line); border-radius: 8px; background: var(--soft); }
    .movement-count-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .55rem; }
    .movement-purok-card { padding: .7rem .8rem; border: 1px solid var(--line); border-radius: 8px; background: var(--soft); }
    .movement-purok-card h3 { margin: 0 0 .5rem; font-size: .82rem; font-weight: 700; }
    .movement-purok-counts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .35rem; }
    .movement-purok-count { display: flex; justify-content: space-between; gap: .35rem; color: var(--muted); font-size: .76rem; }
    .movement-purok-count strong { color: var(--ink); font-weight: 700; }
    @media (max-width: 520px) {
        .movement-code-modal .modal-head { padding: .75rem .85rem; }
        .movement-code-modal .modal-body { padding: .8rem; }
        .movement-count-grid { grid-template-columns: 1fr; }
    }
    .modal[open] { display: flex; flex-direction: column; }
    .modal::backdrop { background: rgba(0,0,0,.6); }
    .modal-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: 1.1rem 1.4rem; border-bottom: 1px solid var(--line); }
    .modal-head h2 { font-size: 1.15rem; font-weight: 700; letter-spacing: -.02em; }
    .modal-head p { margin-top: .2rem; color: var(--muted); font-size: .85rem; }
    .modal-x { flex: none; width: 32px; height: 32px; border: 1px solid var(--line); border-radius: 6px; background: transparent; color: var(--muted); font-size: 1.3rem; line-height: 1; cursor: pointer; transition: background .15s ease, color .15s ease; }
    .modal-x:hover { background: var(--soft); color: var(--ink); }
    .modal-body { padding: 1.1rem 1.4rem 1.4rem; overflow: auto; }
    .registry-edit-modal { width: min(480px, calc(100vw - 2rem)); }
    .registry-edit-form { display: grid; gap: .7rem; }
    .registry-edit-label { font-size: .85rem; font-weight: 700; }
    .registry-edit-form input, .registry-edit-form select {
        width: 100%; min-height: 2.6rem; padding: .5rem .7rem; border: 1px solid var(--line); border-radius: 6px;
        background: var(--card); color: var(--ink); font: inherit;
    }
    .registry-edit-form input:focus, .registry-edit-form select:focus { outline: 2px solid var(--accent); outline-offset: 0; }
    .registry-edit-hint { color: var(--muted); font-size: .78rem; line-height: 1.45; }
    .registry-edit-error { color: var(--danger); font-size: .82rem; font-weight: 650; }
    .registry-edit-error[hidden] { display: none; }
    .registry-edit-actions { display: flex; justify-content: flex-end; gap: .6rem; margin-top: .3rem; }
    .registry-edit-actions .btn { padding: .55rem .9rem; border: 1px solid var(--line); border-radius: 6px; background: var(--card); color: var(--ink); font: inherit; font-weight: 600; cursor: pointer; transition: opacity .15s ease, background .15s ease; }
    .registry-edit-actions .btn:hover { background: var(--soft); }
    .registry-edit-actions .btn-primary { border-color: var(--ink); background: var(--ink); color: var(--card); }
    .registry-edit-actions .btn-primary:hover { background: var(--ink); opacity: .85; }
    .registry-edit-actions .btn:disabled { opacity: .5; cursor: default; }
    .registry-conflict-modal { width: min(520px, calc(100vw - 2rem)); }
    .registry-conflict-modal .modal-body { display: grid; gap: .9rem; }
    .conflict-list { display: grid; gap: .8rem; }
    .conflict-row { display: grid; gap: .3rem; }
    .conflict-row label { font-size: .85rem; font-weight: 700; }
    .conflict-row input { width: 100%; min-height: 2.6rem; padding: .5rem .7rem; border: 1px solid var(--line); border-radius: 6px; background: var(--card); color: var(--ink); font: inherit; letter-spacing: .02em; }
    .conflict-row input:focus { outline: 2px solid var(--accent); outline-offset: 0; }
    .conflict-msg { min-height: 0; color: var(--danger); font-size: .78rem; }
    .dash .registry-details-status { margin: 0; padding: .65rem 1.4rem; border-bottom: 1px solid var(--line); background: var(--soft); color: var(--ink); font-size: .85rem; font-weight: 600; }
    .dash .registry-details-status[hidden] { display: none; }
    .saved-cell { display: flex; align-items: center; gap: .7rem; flex-wrap: wrap; color: var(--g-good); font-size: .85rem; font-weight: 600; }
    .saved-cell .link-btn { font-size: .78rem; }
    .tbl tr.is-saved td { background: var(--soft); }

    .tbl { width: 100%; border-collapse: collapse; font-size: .88rem; }
    .tbl th { padding: .6rem .75rem; border-bottom: 1px solid var(--line); color: var(--muted); font-size: .78rem; font-weight: 600; text-align: left; white-space: nowrap; }
    .tbl td { padding: .7rem .75rem; border-bottom: 1px solid var(--line); }
    .tbl .name { font-weight: 600; }
    .tbl small { display: block; margin-top: .1rem; color: var(--muted); font-size: .75rem; }
    .tag { display: inline-block; padding: .15rem .6rem; border-radius: 99px; color: #fff; font-size: .75rem; font-weight: 700; }
    .empty { padding: 3rem 1rem; text-align: center; color: var(--muted); }
    .empty strong { display: block; margin-bottom: .3rem; color: var(--ink); font-size: 1.1rem; }

    .reg { display: block; }
    .tbl-wrap { overflow-x: auto; }

    .reg .tbl { min-width: 760px; table-layout: fixed; border: 0; border-radius: 0; }
    .reg .tbl th, .reg .tbl td { padding: 0; text-align: center; vertical-align: middle; border-bottom: 1px solid var(--line); background: transparent; }
    .reg .tbl th { padding: .7rem .5rem; color: var(--muted); font-size: .78rem; font-weight: 600; border-bottom: 2px solid var(--ink); }
    .reg .tbl th:first-child, .reg .tbl td:first-child { position: sticky; left: 0; z-index: 1; width: 150px; padding: 0 .75rem; background: var(--card); text-align: left; font-weight: 600; }
    .reg .tbl thead th { position: sticky; top: 0; z-index: 2; background: var(--card); }
    .reg .tbl thead th:first-child { z-index: 3; }
    .reg .gap-cell, .reg .ok-cell { background: transparent; color: var(--ink); }

    .reg-count { display: block; width: 100%; min-height: 2.6rem; padding: 0; border: 0; border-radius: 0; background: transparent; color: var(--ink); font: inherit; font-weight: 700; cursor: pointer; transition: background .12s ease, color .12s ease; }
    .reg-count:not(.is-zero):hover, .reg-count:not(.is-zero):focus-visible { background: var(--ink); color: var(--card); }
    .reg-count.is-zero { color: var(--muted); font-weight: 400; cursor: default; }
    .reg-count:disabled { opacity: .5; cursor: default; }

    #registry-details-body .reg-count { display: inline-block; width: auto; min-height: 0; padding: .35rem .8rem; border: 1px solid var(--line); border-radius: 6px; }
    #registry-details-body .reg-count:not(:disabled):hover { background: var(--ink); color: var(--card); }

    .reg .tbl thead th:last-child { background: var(--ink); color: var(--card); border-bottom-color: var(--ink); }
    .reg .tbl td.tot { border-left: 2px solid var(--ink); background: var(--soft); }
    .reg .tbl td.tot .reg-count { font-size: 1.05rem; font-weight: 800; }
    .reg tfoot td { padding: .8rem .5rem; border-top: 2px solid var(--ink); border-bottom: 0; background: transparent; font-weight: 700; }
    .reg tfoot td:first-child { background: var(--card); }
    .reg tfoot td.tot { background: var(--soft); }

    .reg-summary { margin-top: 1.5rem; padding: 1rem 0 0; border: 0; border-top: 2px solid var(--ink); border-radius: 0; background: transparent; }
    .reg-summary h3 { margin: 0 0 .8rem; font-size: 1rem; font-weight: 700; }
    .reg-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
    .sum-item { min-width: 0; padding: .75rem; border: 1px solid var(--line); border-radius: 8px; background: transparent; }
    .sum-top { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; font-size: .8rem; color: var(--muted); }
    .sum-top span:first-child { overflow-wrap: anywhere; }
    .sum-top span:last-child { flex: none; color: var(--ink); font-size: 1.1rem; font-weight: 800; letter-spacing: -.02em; }
    .meter { height: 4px; margin-top: .6rem; border-radius: 99px; background: var(--line); overflow: hidden; }
    .meter span { display: block; height: 100%; border-radius: inherit; background: var(--ink); }
    @media (max-width: 900px) { .reg-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 560px) { .reg-summary-grid { grid-template-columns: 1fr; } }

    /* Hero */
    .hero {
        display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 2rem;
        min-height: 150px; padding: 1.5rem 1.7rem; margin-bottom: .75rem; border: 1px solid var(--line); border-radius: 8px; background: var(--card); color: var(--ink);
    }
    .hero-num { display: grid; }
    .hero-value { font-size: clamp(3.2rem, 7vw, 5rem); font-weight: 700; line-height: .95; letter-spacing: -.045em; }
    .hero-label { margin-top: .4rem; color: var(--muted); font-size: .92rem; }
    .split-bar { display: flex; height: 22px; gap: 3px; border-radius: 4px; background: var(--soft); }
    .split-bar span { display: block; height: 100%; width: 0; border-radius: 3px; transition: width .9s cubic-bezier(.2,.8,.2,1); }
    .split-legend { display: flex; flex-wrap: wrap; gap: .4rem 1.4rem; margin-top: .8rem; font-size: .86rem; color: var(--muted); }
    .split-legend li { display: flex; align-items: center; gap: .4rem; }
    .split-legend strong { color: var(--ink); }
    .hero-side { display: grid; justify-items: end; padding-left: 2rem; border-left: 1px solid var(--line); }
    .hero-side-value { font-size: 2rem; font-weight: 700; line-height: 1; }
    .hero-side-label { margin-top: .3rem; color: var(--muted); font-size: .85rem; }
    .sk-hero > div { display: grid; align-content: center; }
    .sk-hero .hero-side { border-left-color: transparent; }

    /* Grid + panels */
    .grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: .75rem; }
    .span-12 { grid-column: span 12; }
    .panel { min-width: 0; padding: 1.15rem 1.25rem; border: 1px solid var(--line); border-radius: 8px; background: var(--card); }
    .panel h2 { font-size: .95rem; font-weight: 600; letter-spacing: -.01em; }
    .panel > h2 { margin-bottom: .9rem; }
    .panel-row { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; margin-bottom: .9rem; }
    .panel-row > h2 { min-width: 0; margin: 0; }
    .kap { margin-top: 1.1rem; padding-top: 1.1rem; border-top: 1px solid var(--line); }
    .kap-inner { display: grid; gap: .65rem; }
    .kap-head { display: flex; justify-content: space-between; align-items: center; gap: .6rem; flex-wrap: wrap; }
    .kap-head h3 { margin: 0; font-size: .88rem; font-weight: 600; }
    .kap-total { color: var(--muted); font-size: .78rem; }
    .kap-rows { display: grid; gap: .3rem; }
    .kap-row { display: grid; grid-template-columns: 88px minmax(0, 1fr) 82px; align-items: center; gap: .6rem; width: 100%; padding: .35rem .4rem; border: 0; border-radius: 6px; background: transparent; color: var(--ink); font: inherit; text-align: left; cursor: pointer; transition: background .15s ease; }
    .kap-row:hover { background: var(--soft); }
    .kap-name { display: flex; align-items: center; gap: .45rem; font-size: .84rem; font-weight: 600; }
    .kap-name i { width: 10px; height: 10px; border-radius: 3px; flex: none; }
    .kap-track { height: 12px; border-radius: 4px; background: var(--soft); overflow: hidden; }
    .kap-row:hover .kap-track { background: var(--line); }
    .kap-fill { display: block; height: 100%; width: 0; border-radius: 4px; transition: width .6s cubic-bezier(.2,.8,.2,1); }
    .kap-value { font-size: .8rem; font-weight: 600; text-align: right; }
    .kap-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; }
    .kap-stat { display: grid; gap: .1rem; width: 100%; padding: .65rem .75rem; border: 1px solid var(--line); border-radius: 8px; background: var(--card); color: var(--ink); font: inherit; text-align: left; cursor: pointer; transition: border-color .15s ease; }
    .kap-stat:hover { border-color: var(--ink); }
    .kap-stat-name { display: flex; align-items: center; gap: .4rem; color: var(--muted); font-size: .75rem; font-weight: 600; }
    .kap-stat-name i { width: 9px; height: 9px; border-radius: 2px; flex: none; }
    .kap-stat strong { font-size: 1.3rem; font-weight: 700; letter-spacing: -.02em; }
    .kap-stat small { color: var(--muted); font-size: .75rem; }

    .legend { display: flex; flex-wrap: wrap; gap: .3rem 1rem; margin-top: .7rem; font-size: .8rem; color: var(--muted); }
    .legend li { display: flex; align-items: center; gap: .4rem; }
    .panel-row .legend { margin-top: 0; }
    .legend i, .split-legend i { width: 10px; height: 10px; border-radius: 2px; display: inline-block; flex: none; }

    /* Gauges: green when near full, yellow when mid, red when low */
    .gauges { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; }
    .gauge { text-align: center; }
    .gauge svg { width: min(100%, 140px); height: auto; }
    .gauge figcaption { margin-top: .5rem; font-size: .85rem; font-weight: 600; }
    .g-track { fill: none; stroke: var(--line); stroke-width: 10; }
    .g-bar { fill: none; stroke: var(--g-color, var(--g-good)); stroke-width: 10; stroke-linecap: round; stroke-dasharray: 314.16; stroke-dashoffset: 314.16; transition: stroke-dashoffset 1.1s cubic-bezier(.2,.8,.2,1); }
    .gauge[data-unavailable] .g-bar { stroke: none; }
    .g-text { fill: var(--ink); font-size: 17px; font-weight: 700; text-anchor: middle; dominant-baseline: central; }

    /* Gender */
    .gender-row + .gender-row { margin-top: 1rem; }
    .gender-top { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: .35rem; font-size: .9rem; }
    .gender-top strong { font-size: 1.5rem; letter-spacing: -.02em; }
    .gender-drill { padding: 0; border: 0; background: transparent; color: var(--ink); font: inherit; cursor: pointer; }
    .gender-drill span { color: var(--muted); font-size: .75rem; opacity: 0; transition: opacity .15s ease; }
    .gender-drill:hover span, .gender-drill:focus-visible span { opacity: 1; }
    .gender-drill:hover { text-decoration: underline; text-underline-offset: 3px; }
    .gbar { display: flex; height: 14px; overflow: hidden; border-radius: 4px; background: var(--soft); }
    .gbar span { display: block; height: 100%; width: 0; transition: width .9s cubic-bezier(.2,.8,.2,1); }

    /* Segmented control + chips */
    .seg { display: inline-flex; flex-wrap: wrap; gap: 3px; max-width: 100%; padding: 3px; border: 1px solid var(--line); border-radius: 8px; background: var(--soft); }
    .seg button { min-width: 42px; padding: .35rem .7rem; border: 0; border-radius: 6px; background: transparent; color: var(--muted); font: inherit; font-size: .78rem; font-weight: 600; cursor: pointer; transition: background .18s ease, color .18s ease, box-shadow .18s ease; }
    .seg button:hover { color: var(--ink); background: var(--card); }
    .seg button[aria-checked="true"] { background: var(--card); color: var(--ink); box-shadow: 0 0 0 1px var(--line); }

    /* Records over time */
    .ot-panel { padding: 1.4rem 1.5rem 1.5rem; }
    .ot-panel .panel-row { align-items: flex-start; margin-bottom: .9rem; }
    .ot-help { margin-top: .25rem; color: var(--muted); font-size: .78rem; }
    #ot-legend { gap: .4rem; }
    #ot-legend li { gap: .45rem; padding: .3rem .75rem; border: 1px solid var(--line); border-radius: 99px; font-size: .8rem; font-weight: 600; }
    #ot-legend strong { color: var(--ink); font-weight: 700; }
    .ot-current-row { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; margin: 0 0 1.1rem; }
    .ot-current { display: flex; align-items: center; gap: .55rem; color: var(--muted); font-size: .82rem; font-weight: 500; }
    .ot-current::before { content: ""; flex: none; width: 6px; height: 6px; border-radius: 50%; background: var(--ink); }
    .ot-note { margin-top: .8rem; }
    .ot-note[hidden], #ot-reset[hidden] { display: none; }
    .movement-count-summary { margin: 0 0 .8rem; padding: .7rem .8rem; border-radius: 6px; background: var(--soft); color: var(--ink); font-size: .83rem; line-height: 1.6; }
    .ot { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 1.5rem; }
    .ot-chart { position: relative; height: 360px; min-width: 0; }
    .ot-empty { position: absolute; inset: 0; display: grid; place-items: center; color: var(--muted); font-size: .9rem; }
    .ot-empty[hidden] { display: none; }

    .filters { display: grid; align-content: start; gap: 1.5rem; padding-left: 1.5rem; border-left: 1px solid var(--line); }
    .filter-group { display: grid; gap: .6rem; }
    .filter-head { display: flex; justify-content: space-between; align-items: center; font-size: .82rem; font-weight: 600; }
    .link-btn { padding: 0; border: 0; background: none; color: var(--muted); font: inherit; font-size: .76rem; font-weight: 600; cursor: pointer; }
    .link-btn:hover { color: var(--ink); text-decoration: underline; text-underline-offset: 3px; }
    .chips { display: flex; flex-wrap: wrap; gap: .35rem; }
    .chips.months { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .chips.puroks { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .chip { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .05rem; min-height: 2.35rem; padding: .35rem .25rem; border: 1px solid var(--line); border-radius: 6px; background: transparent; color: var(--ink); font: inherit; font-size: .78rem; cursor: pointer; transition: border-color .15s ease, background .15s ease, color .15s ease; }
    .chip b { font-weight: 600; }
    .chip small { color: var(--muted); font-size: .66rem; line-height: 1; }
    .chip:hover { border-color: var(--ink); }
    .chip[aria-pressed="true"] { border-color: var(--ink); background: var(--ink); color: var(--card); }
    .chip[aria-pressed="true"] small { color: inherit; opacity: .7; }
    .chip:disabled { opacity: .4; cursor: default; }
    .hint { margin: 0; color: var(--muted); font-size: .74rem; line-height: 1.4; }

    .chart-empty { display: grid; height: 100%; place-items: center; padding: 1rem; color: var(--muted); font-size: .9rem; text-align: center; }
    .purok-bars { display: grid; max-height: 320px; gap: .32rem; overflow-y: auto; padding: .2rem .25rem .2rem 0; }
    .purok-row { display: grid; grid-template-columns: minmax(100px, 150px) minmax(0, 1fr) 42px; align-items: center; gap: .55rem; width: 100%; min-height: 28px; padding: .18rem .3rem; border: 0; border-radius: 6px; background: transparent; color: var(--ink); text-align: left; cursor: pointer; transition: background .15s ease; }
    .purok-row:hover, .purok-row:focus-visible { background: var(--soft); }
    .purok-row-name { overflow: hidden; font-size: .78rem; font-weight: 600; line-height: 1.2; text-align: left; text-overflow: ellipsis; white-space: nowrap; }
    .purok-row-track { display: flex; width: 100%; min-width: 0; height: 20px; overflow: hidden; border-radius: 4px; background: var(--soft); }
    .purok-row-segment { display: flex; min-width: 0; height: 100%; flex: 0 0 auto; align-items: center; justify-content: center; overflow: hidden; font-size: .68rem; font-weight: 700; white-space: nowrap; }
    .purok-row-total { color: var(--muted); font-size: .76rem; font-weight: 700; text-align: right; }
    .purok-chart-detail { min-height: 1.25rem; margin: .5rem 0 0; color: var(--muted); font-size: .82rem; font-weight: 600; }
    .dash a:focus-visible, .dash button:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    @media (prefers-reduced-motion: reduce) {
        .dash *, .dash *::before { transition: none !important; }
        .sk { animation: none; }
    }

    @media (min-width: 1101px) {
        .area-purok { grid-column: 1 / span 7; grid-row: 1; }
        .area-gender { grid-column: 1 / span 7; grid-row: 2; }
        .area-progress { grid-column: 8 / span 5; grid-row: 1 / span 2; }
    }
    @media (max-width: 1100px) {
        .area-purok, .area-gender, .area-progress { grid-column: span 12; }
    }
    @media (max-width: 800px) {
        .hero { grid-template-columns: 1fr; gap: 1.1rem; }
        .hero-side { justify-items: start; padding: 1rem 0 0; border-left: 0; border-top: 1px solid var(--line); }
        .ot { grid-template-columns: 1fr; }
        .filters { padding: 1rem 0 0; border-left: 0; border-top: 1px solid var(--line); }
    }
    @media (max-width: 640px) {
        .gauges { grid-template-columns: 1fr; }
        .ot-chart { height: 280px; }
        .purok-row { grid-template-columns: minmax(88px, 112px) minmax(0, 1fr) 34px; gap: .35rem; }
        .purok-row-name { font-size: .72rem; }
        .purok-row-track { height: 18px; }
        .purok-row-segment { font-size: .62rem; }
        .kap-head .seg { width: 100%; justify-content: space-between; }
        .kap-head .seg button { flex: 1 1 auto; }
        .kap-row { grid-template-columns: 78px minmax(0, 1fr) 70px; }
        .kap-stats { grid-template-columns: 1fr; }
        .panel-row { align-items: flex-start; }
    }
</style>
@endpush

@push('scripts')
@vite('resources/js/dashboard.js')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const $ = id => document.getElementById(id);
    const dashRoot = $('dashboardApp');
    const nf = new Intl.NumberFormat('en-PH');
    const isNum = n => n !== null && n !== undefined && n !== '' && Number.isFinite(Number(n));
    // Unknown values render as an em dash, never as 0.
    const fmt = n => isNum(n) ? nf.format(Number(n)) : '—';

    let INK, MUTED, LINE, CARD;
    const readTheme = () => {
        const css = getComputedStyle(dashRoot);
        INK = css.getPropertyValue('--ink').trim();
        MUTED = css.getPropertyValue('--muted').trim();
        LINE = css.getPropertyValue('--line').trim();
        CARD = css.getPropertyValue('--ot-bg').trim();
    };
    readTheme();
    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.color = MUTED;
        Chart.defaults.animation = false;
    }

    /* ---------- Helpers ---------- */
    const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };
    const swatch = c => { const i = el('i'); i.style.background = c; return i; };
    const add = a => a.reduce((x, y) => x + y, 0);
    const legend = (id, items) => $(id).replaceChildren(...items.map(([name, color]) => { const li = el('li'); li.append(swatch(color), name); return li; }));
    function animateNumber(node, to, decimals, suffix) {
        if (!isNum(to)) { node.textContent = '—'; return; }
        to = Number(to);
        const done = v => decimals ? v.toFixed(decimals) + suffix : fmt(Math.round(v)) + suffix;
        if (reduceMotion) { node.textContent = done(to); return; }
        const t0 = performance.now();
        const tick = now => {
            const t = Math.min((now - t0) / 800, 1);
            node.textContent = done(to * (1 - Math.pow(1 - t, 3)));
            if (t < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    }
    function segments(container, parts, base) {
        const nodes = parts.map(([label, n, color]) => { const s = el('span'); s.style.background = color; s.title = `${label}: ${fmt(n)}`; return [s, base ? n / base * 100 : 0]; });
        container.replaceChildren(...nodes.map(n => n[0]));
        requestAnimationFrame(() => requestAnimationFrame(() => nodes.forEach(([s, w]) => { s.style.width = w + '%'; })));
    }
    function wireDialog(dlg) {
        dlg.addEventListener('click', e => { if (e.target === dlg || e.target.closest('[data-close]')) dlg.close(); });
    }
    document.querySelectorAll('dialog.modal').forEach(wireDialog);

    /* ---------- Per-section state machine (loading → ready | error) ----------
       Each section renders independently. A failure shows a retry block for that section only
       and leaves every other section untouched. */
    function mount(id, render) {
        const host = $(id);
        const err = el('div', 'state-err');
        err.dataset.error = '';
        err.setAttribute('role', 'alert');
        const title = el('strong', '', "This section couldn't be displayed.");
        const retry = el('button', 'retry', 'Retry');
        retry.type = 'button';
        err.append(title, retry);
        host.append(err);
        const setState = s => { host.dataset.state = s; host.setAttribute('aria-busy', s === 'loading' ? 'true' : 'false'); };
        const run = () => {
            setState('ready'); // reveal content first so charts can measure their container
            try { render(); }
            catch (exception) { console.error(`Dashboard section "${id}" failed:`, exception); setState('error'); }
        };
        retry.addEventListener('click', () => { data = null; setState('loading'); requestAnimationFrame(run); });
        return run;
    }

    /* ---------- Data (server-rendered, prepared once, re-prepared on retry) ---------- */
    // NOTE: all figures come from the controller payload. No new endpoints are used.
    const DASHBOARD = @json($dashboardMetrics);
    const COLORS = { MS: '#16a34a', UWP: '#f97316', MK: '#eab308', INACTIVE: '#9aa5b1', UNSPECIFIED: '#c8ced5' };
    const EXTRA_COLORS = ['#60a5fa', '#3b82f6', '#93c5fd', '#2563eb', '#bfdbfe'];
    const colorFor = (code, index = 0) => COLORS[code] || EXTRA_COLORS[index % EXTRA_COLORS.length];
    const CFO_GROUPS = ['Buklod', 'Kadiwa', 'Binhi'];
    const CFO_STATUS_KEYS = ['MS', 'UWP', 'MK'];
    const GROUP_VARS = { Binhi: 'var(--binhi)', Kadiwa: 'var(--kadiwa)', Buklod: 'var(--buklod)' };
    const historyStatuses = ['MS', 'UWP', 'MK'];
    let data = null;
    const ensure = () => data || (data = prepare());

    function prepare() {
        const D = DASHBOARD;
        const KEYS = D.activeStatuses;
        const NAMES = Object.fromEntries(KEYS.map(code => [code, code]));
        const active = Object.fromEntries(KEYS.map(code => [code, D.statuses.find(status => status.code === code)?.count || 0]));
        const puroks = D.puroks.map(p => ({
            id: p.id, name: p.name,
            ...Object.fromEntries(KEYS.map(code => [code, p.counts[code] || 0])),
            total: p.total, recordTotal: p.recordTotal,
        }));
        const MONTHS = D.monthLabels;
        const REG_FIELDS = D.registryReport.map(field => [field.label, field.missing, field.details]);
        const registryPuroks = D.registryPuroks;
        return {
            D, KEYS, NAMES, active, puroks,
            totalActive: D.activeCount, inactive: D.inactiveCount,
            gender: D.gender, genderRecords: D.genderRecords,
            groupRecords: D.groupRecords,
            MONTHS, MONTH_KEYS: D.monthKeys, DATA_MONTHS: MONTHS.length,
            overtime: D.monthlyPurokCounts, overtimePuroks: D.movementPuroks,
            REG_FIELDS, registryPuroks,
            regTotals: registryPuroks.map(p => p.recordTotal), regAll: D.registryRecordCount,
            statusItems: KEYS.map(k => [k, colorFor(k)]),
        };
    }

    /* ---------- Hero / summary ---------- */
    const runHero = mount('hero-wrap', () => {
        const { KEYS, NAMES, active, totalActive, inactive } = ensure();
        animateNumber($('hero-total'), totalActive, 0, '');
        animateNumber($('hero-inactive'), inactive, 0, '');
        segments($('split-bar'), KEYS.map(k => [NAMES[k], active[k], colorFor(k)]), totalActive);
        $('split-bar').setAttribute('aria-label', 'Active records: ' + KEYS.map(k => `${NAMES[k]} ${fmt(active[k])}`).join(', '));
        $('split-legend').replaceChildren(...KEYS.map(k => {
            const li = el('li');
            const pct = totalActive ? ` (${(active[k] / totalActive * 100).toFixed(1)}%)` : '';
            li.append(swatch(colorFor(k)), NAMES[k] + ' ', el('strong', '', fmt(active[k])), pct);
            return li;
        }));
    });

    /* ---------- Active records by purok ---------- */
    const runPurok = mount('purok-host', () => {
        const { puroks, KEYS, NAMES, statusItems } = ensure();
        legend('status-legend', statusItems);
        if (!puroks.length) {
            $('purok-chart-box').replaceChildren(el('p', 'chart-empty', 'No Puroks are configured yet. Add Puroks in Security Settings to see active records by Purok.'));
            $('status-legend').replaceChildren();
            $('purok-chart-detail').hidden = true;
            return;
        }
        $('purok-chart-detail').hidden = false;
        const showDetail = purok => {
            $('purok-chart-detail').textContent =
                `${purok.name}: ${KEYS.map(status => `${NAMES[status]} ${fmt(purok[status])}`).join(' · ')} · Total ${fmt(purok.total)}`;
        };
        const largest = Math.max(1, ...puroks.map(purok => purok.total));
        $('purok-chart-box').replaceChildren(...puroks.map(purok => {
            const row = el('button', 'purok-row');
            row.type = 'button';
            const summary = KEYS.map(status => `${NAMES[status]} ${fmt(purok[status])}`);
            row.setAttribute('aria-label', `${purok.name}: ${summary.join(', ')}; total ${fmt(purok.total)}`);
            row.title = `${purok.name}: ${summary.join(' · ')} · Total ${fmt(purok.total)}`;
            const track = el('span', 'purok-row-track');
            track.setAttribute('aria-hidden', 'true');
            KEYS.forEach(status => {
                const count = purok[status];
                if (!count) return;
                const segment = el('span', 'purok-row-segment', count / largest >= .1 ? String(count) : '');
                segment.style.width = `${count / largest * 100}%`;
                segment.style.background = colorFor(status);
                segment.style.color = status === 'MK' ? '#171717' : '#fff';
                segment.title = `${status}: ${fmt(count)}`;
                track.append(segment);
            });
            row.append(el('span', 'purok-row-name', purok.name), track, el('span', 'purok-row-total', fmt(purok.total)));
            ['mouseenter', 'focus', 'click'].forEach(evt => row.addEventListener(evt, () => showDetail(purok)));
            return row;
        }));
    });

    /* ---------- Gauges ---------- */
    const runGauges = mount('gauges-host', () => {
        const { D } = ensure();
        const gauges = [...$('gauges-host').querySelectorAll('[data-content] .gauge')];
        const values = [D.r210Updating, D.registryCompletion, D.localeActivePercentage];
        const labels = [
            v => `R2-10 Updating ${v}%`,
            v => `Registry Updating ${v}%`,
            v => `LSP / Locale: ${fmt(D.activeCount)} active records of ${fmt(D.localeSNumberTarget)} S Number (${v}%)`,
        ];
        gauges.forEach((g, i) => {
            const known = isNum(values[i]);
            const v = known ? Number(values[i]) : null;
            g.toggleAttribute('data-unavailable', !known);
            g.querySelector('svg').setAttribute('aria-label', known ? labels[i](v.toFixed(2)) : 'Unavailable');
            const c = 2 * Math.PI * 50;
            g.style.setProperty('--g-color', v === null ? 'var(--line)' : v >= 80 ? 'var(--g-good)' : v >= 50 ? 'var(--g-mid)' : 'var(--g-low)');
            setTimeout(() => {
                g.querySelector('.g-bar').style.strokeDashoffset = String(c * (1 - (known ? Math.min(Math.max(v, 0), 100) : 0) / 100));
                animateNumber(g.querySelector('.g-text'), v, 2, '%');
            }, reduceMotion ? 0 : 100 + i * 100);
        });
    });

    /* ---------- CFO modal (shared) ---------- */
    const cfoGroupModal = $('cfo-group-modal');
    function showCfoRecords(records, title, description) {
        $('cfo-group-title').textContent = title;
        $('cfo-group-sub').textContent = `${fmt(records.length)} ${description} record${records.length === 1 ? '' : 's'}`;
        const body = $('cfo-group-body');
        if (!records.length) {
            body.replaceChildren(el('div', 'empty', `No ${description} records found.`));
        } else {
            const table = el('table', 'tbl'), head = el('tr');
            ['Name', 'Kalagayan'].forEach(label => head.append(el('th', '', label)));
            const thead = el('thead'); thead.append(head); table.append(thead);
            const tbody = el('tbody');
            [...records].sort((l, r) => l.name.localeCompare(r.name)).forEach(record => {
                const row = el('tr');
                row.append(el('td', 'name', record.name), el('td', '', record.status));
                tbody.append(row);
            });
            table.append(tbody);
            const wrap = el('div', 'tbl-wrap'); wrap.append(table);
            body.replaceChildren(wrap);
        }
        cfoGroupModal.showModal();
    }
    const groupCount = (status, group) => ensure().D.groupCounts[status]?.[group] || 0;
    const cfoRecords = (statuses, groups) => statuses.flatMap(status => groups.flatMap(group => ensure().groupRecords[status]?.[group] || []));

    function updateCfoGroupRows(kind) {
        const shown = kind === 'ALL' ? CFO_STATUS_KEYS : [kind];
        const statusLabel = kind === 'ALL' ? 'MS, UWP, and MK' : kind;
        const counts = CFO_GROUPS.map(group => add(shown.map(status => groupCount(status, group))));
        const total = add(counts);
        $('cfo-group-total').textContent = `${fmt(total)} ${statusLabel} record${total === 1 ? '' : 's'}`;
        $('cfo-group-rows').replaceChildren(...CFO_GROUPS.map((group, index) => {
            const value = counts[index], pct = total ? value / total * 100 : 0;
            const row = el('button', 'kap-row');
            row.type = 'button';
            row.setAttribute('aria-label', `${group}: ${fmt(value)} ${statusLabel} records (${Math.round(pct)}%). Open records`);
            const name = el('span', 'kap-name'); name.append(swatch(GROUP_VARS[group]), group);
            const track = el('span', 'kap-track'), fill = el('span', 'kap-fill');
            fill.style.background = GROUP_VARS[group];
            track.append(fill);
            row.append(name, track, el('span', 'kap-value', `${fmt(value)} · ${Math.round(pct)}%`));
            row.addEventListener('click', () => showCfoRecords(cfoRecords(shown, [group]), `${group} records`, `${kind === 'ALL' ? 'all MS, UWP, and MK' : kind} ${group}`));
            requestAnimationFrame(() => requestAnimationFrame(() => { fill.style.width = `${pct}%`; }));
            return row;
        }));
        $('cfo-status-seg').querySelectorAll('button').forEach(b => b.setAttribute('aria-checked', String(b.dataset.k === kind)));
    }
    function updateCfoStatusStats(kind) {
        const groups = kind === 'ALL' ? CFO_GROUPS : [kind];
        const groupLabel = kind === 'ALL' ? 'all CFO groups' : kind;
        const counts = CFO_STATUS_KEYS.map(status => add(groups.map(group => groupCount(status, group))));
        const total = add(counts);
        $('cfo-status-total').textContent = `${fmt(total)} record${total === 1 ? '' : 's'} · ${groupLabel}`;
        $('cfo-status-stats').replaceChildren(...CFO_STATUS_KEYS.map((status, index) => {
            const item = el('li'), button = el('button', 'kap-stat');
            button.type = 'button';
            button.setAttribute('aria-label', `${status}: ${fmt(counts[index])} records in ${groupLabel}. Open records`);
            const label = el('span', 'kap-stat-name'); label.append(swatch(colorFor(status)), status);
            button.append(label, el('strong', '', fmt(counts[index])), el('small', '', `${total ? Math.round(counts[index] / total * 100) : 0}%`));
            button.addEventListener('click', () => showCfoRecords(cfoRecords([status], groups), `${status} records`, `${status} ${groupLabel}`));
            item.append(button);
            return item;
        }));
        $('cfo-group-seg').querySelectorAll('button').forEach(b => b.setAttribute('aria-checked', String(b.dataset.k === kind)));
    }
    const segButtons = (kinds, onPick) => kinds.map(kind => {
        const button = el('button', '', kind);
        button.type = 'button';
        button.dataset.k = kind;
        button.setAttribute('role', 'radio');
        button.addEventListener('click', () => onPick(kind));
        return button;
    });
    const runCfoA = mount('cfo-a-host', () => {
        ensure();
        $('cfo-status-seg').replaceChildren(...segButtons(['ALL', ...CFO_STATUS_KEYS], updateCfoGroupRows));
        updateCfoGroupRows('ALL');
    });
    const runCfoB = mount('cfo-b-host', () => {
        ensure();
        $('cfo-group-seg').replaceChildren(...segButtons(['ALL', ...CFO_GROUPS], updateCfoStatusStats));
        updateCfoStatusStats('ALL');
    });

    /* ---------- Gender ---------- */
    const genderRecordsModal = $('gender-records-modal');
    function showGenderRecords(genderKey) {
        const { genderRecords } = ensure();
        const label = genderKey === 'female' ? 'Female' : 'Male';
        const records = [...(genderRecords[genderKey] || [])].sort((l, r) => l.name.localeCompare(r.name));
        $('gender-records-title').textContent = `${label} active records`;
        $('gender-records-sub').textContent = `${fmt(records.length)} record${records.length === 1 ? '' : 's'}`;
        const body = $('gender-records-body');
        if (!records.length) {
            body.replaceChildren(el('div', 'empty', `No active ${label.toLowerCase()} records found.`));
        } else {
            const table = el('table', 'tbl'), head = el('tr');
            ['Name', 'Kalagayan', 'Purok'].forEach(column => head.append(el('th', '', column)));
            const thead = el('thead'); thead.append(head); table.append(thead);
            const tbody = el('tbody');
            records.forEach(record => {
                const row = el('tr');
                row.append(el('td', 'name', record.name), el('td', '', record.status), el('td', '', record.purok));
                tbody.append(row);
            });
            table.append(tbody);
            const wrap = el('div', 'tbl-wrap'); wrap.append(table);
            body.replaceChildren(wrap);
        }
        genderRecordsModal.showModal();
    }
    const runGender = mount('gender-host', () => {
        const { gender, KEYS, NAMES, totalActive, statusItems } = ensure();
        ['female', 'male'].forEach(key => {
            const g = gender[key], n = add(KEYS.map(k => g[k] || 0)), pct = totalActive ? n / totalActive * 100 : 0;
            segments($(`bar-${key}`), KEYS.map(k => [NAMES[k], g[k] || 0, colorFor(k)]), totalActive);
            $(`bar-${key}`).setAttribute('aria-label', `${key}: ${pct.toFixed(2)}% of active records (${KEYS.map(k => `${k} ${fmt(g[k] || 0)}`).join(', ')})`);
            animateNumber($(`pct-${key}`), pct, 2, '%');
        });
        legend('gender-legend', statusItems);
    });
    document.querySelectorAll('.gender-drill').forEach(button => {
        button.addEventListener('click', () => { try { showGenderRecords(button.dataset.gender); } catch (e) { console.error(e); } });
    });

    /* ---------- Records over time (rendered when scrolled near) ---------- */
    let otChart = null, idxs = [], visiblePuroks = [], lastSig = null;
    const state = { s: 0, e: 0, puroks: new Set() };
    const lastMonthIndex = () => Math.max(ensure().DATA_MONTHS - 1, 0);
    const isAllMonths = () => state.s === 0 && state.e === lastMonthIndex();
    const movementCodeModal = $('movement-code-modal');

    // Missing snapshot (no entry for the month) → null (gap in the line), never 0.
    // An entry that exists but lacks a purok/status is counted as 0, as before.
    function seriesFor(status, ids) {
        const { overtime } = ensure();
        return idxs.map(monthIndex => {
            const cell = overtime[monthIndex];
            if (cell === undefined || cell === null) return null;
            return ids.reduce((sum, id) => sum + (cell[id]?.[status] || 0), 0);
        });
    }

    function renderOvertime() {
        const { MONTHS, overtimePuroks, DATA_MONTHS } = ensure();
        const allIds = overtimePuroks.map(String);
        const ids = state.puroks.size ? [...state.puroks].sort((a, b) => Number(a) - Number(b)) : allIds;
        const isAllPuroks = ids.length === allIds.length;
        $('ot-reset').hidden = isAllMonths() && state.puroks.size === 0;
        const sig = `${state.s}|${state.e}|${ids.join(',')}`;
        if (sig === lastSig) return;
        lastSig = sig;

        visiblePuroks = ids;
        idxs = [];
        for (let m = state.s; m <= state.e && m < DATA_MONTHS; m++) idxs.push(m);
        $('ot-empty').hidden = idxs.length > 0;

        const selectedMonths = idxs.map(i => MONTHS[i]);
        const period = !selectedMonths.length ? 'no periods'
            : isAllMonths() ? `All periods (${selectedMonths[0]}–${selectedMonths[selectedMonths.length - 1]})`
            : selectedMonths.length === 1 ? selectedMonths[0]
            : `${selectedMonths[0]}–${selectedMonths[selectedMonths.length - 1]}`;
        $('ot-current').textContent = `Now showing trend graph for ${isAllPuroks ? 'All Puroks' : ids.map(id => `Purok ${id}`).join(', ')} · ${period}`;

        const totals = Object.fromEntries(historyStatuses.map(status => [status, seriesFor(status, ids)]));
        const missingMonths = idxs.filter((_, i) => totals.MS[i] === null).length;
        const note = $('ot-note');
        note.hidden = missingMonths === 0;
        if (missingMonths) note.textContent = `${missingMonths} month${missingMonths === 1 ? '' : 's'} in this period ${missingMonths === 1 ? 'has' : 'have'} no snapshot. They appear as gaps, not as zero.`;

        $('ot-legend').replaceChildren(...historyStatuses.map(status => {
            const known = totals[status].filter(v => v !== null);
            const latest = known.length ? known[known.length - 1] : null;
            const li = el('li'); li.title = `${status} at the latest snapshot in the selected period`;
            li.append(swatch(colorFor(status)), status + ' ', el('strong', '', fmt(latest)));
            return li;
        }));

        if (!otChart) return;
        otChart.stop();
        otChart.data.labels = idxs.map(m => MONTHS[m]);
        historyStatuses.forEach((status, i) => { otChart.data.datasets[i].data = totals[status]; });
        otChart.options.scales.x.min = 0;
        otChart.options.scales.x.max = Math.max(0, idxs.length - 1);
        otChart.update('none');
        otChart.canvas.setAttribute('aria-label',
            `Records over time for ${isAllPuroks ? 'all puroks' : 'Purok ' + ids.join(', ')}: ` +
            idxs.map((m, i) => `${MONTHS[m]} ${historyStatuses.map(s => `${s} ${totals[s][i] === null ? 'no snapshot' : fmt(totals[s][i])}`).join(', ')}`).join('; '));
    }

    function showMovementCodes(monthKey, selectedPuroks) {
        const { MONTHS, MONTH_KEYS, overtime, overtimePuroks, D } = ensure();
        const monthIndex = MONTH_KEYS.indexOf(monthKey);
        const details = D.movementDetails
            .filter(item => item.month === monthKey && selectedPuroks.includes(String(item.purok)))
            .sort((l, r) => l.purok - r.purok || l.status.localeCompare(r.status) || l.code.localeCompare(r.code));
        const monthLabel = MONTHS[monthIndex];
        $('movement-code-title').textContent = `${monthLabel} movement codes`;
        const purokLabel = selectedPuroks.length === overtimePuroks.length ? 'All Puroks' : selectedPuroks.map(id => `Purok ${id}`).join(', ');
        $('movement-code-sub').textContent = `${purokLabel} · ${fmt(details.length)} recorded movement entr${details.length === 1 ? 'y' : 'ies'}`;
        const body = $('movement-code-body');
        const summary = el('div', 'movement-count-grid');
        selectedPuroks.forEach(purok => {
            const counts = overtime[monthIndex]?.[purok] || {};
            const card = el('section', 'movement-purok-card');
            card.append(el('h3', '', `Purok ${purok}`));
            const statusCounts = el('div', 'movement-purok-counts');
            historyStatuses.forEach(status => {
                const item = el('div', 'movement-purok-count');
                item.append(el('span', '', status), el('strong', '', overtime[monthIndex] ? fmt(counts[status] || 0) : '—'));
                statusCounts.append(item);
            });
            card.append(statusCounts);
            summary.append(card);
        });
        if (!details.length) {
            body.replaceChildren(el('div', 'empty', `No movement codes were entered for ${monthLabel} in ${purokLabel}.`), summary);
        } else {
            body.replaceChildren(summary);
            const table = el('table', 'tbl'), head = el('tr');
            ['Purok', 'Kalagayan', 'Code', 'Count'].forEach(column => head.append(el('th', '', column)));
            const thead = el('thead'); thead.append(head); table.append(thead);
            const tbody = el('tbody');
            details.forEach(item => {
                const row = el('tr');
                row.append(el('td', '', `Purok ${item.purok}`), el('td', '', item.status), el('td', '', item.code), el('td', '', fmt(item.count)));
                tbody.append(row);
            });
            table.append(tbody);
            const wrap = el('div', 'tbl-wrap'); wrap.append(table);
            body.append(wrap);
        }
        movementCodeModal.showModal();
    }

    function renderMonths() {
        const { MONTHS } = ensure();
        const all = isAllMonths();
        $('tl-months').querySelectorAll('.chip').forEach((b, m) => b.setAttribute('aria-pressed', String(!all && m >= state.s && m <= state.e)));
        $('month-hint').textContent = all ? 'Select a month, then another to pick a range.'
            : state.s === state.e ? `Showing ${MONTHS[state.s]}. Select another month to make a range.`
            : `Showing ${MONTHS[state.s]} to ${MONTHS[state.e]}.`;
    }
    function renderPuroks() {
        $('purok-list').replaceChildren(...ensure().overtimePuroks.map(purokNumber => {
            const id = String(purokNumber);
            const b = el('button', 'chip', `Purok ${id}`); b.type = 'button';
            b.setAttribute('aria-pressed', String(state.puroks.has(id)));
            b.addEventListener('click', () => {
                if (state.puroks.has(id)) state.puroks.delete(id); else state.puroks.add(id);
                renderPuroks(); renderOvertime();
            });
            return b;
        }));
    }
    function resetMonths() { state.s = 0; state.e = lastMonthIndex(); renderMonths(); renderOvertime(); }
    function resetPuroks() { state.puroks = new Set(); renderPuroks(); renderOvertime(); }

    const runOt = mount('ot-panel', () => {
        const { MONTHS, MONTH_KEYS } = ensure();
        if (typeof Chart === 'undefined') throw new Error('Chart.js is not loaded');
        lastSig = null;
        state.s = 0; state.e = lastMonthIndex(); state.puroks = new Set();
        const canvas = $('ot-chart');
        Chart.getChart(canvas)?.destroy();
        const dataset = status => ({
            label: status, data: [], borderColor: colorFor(status), backgroundColor: colorFor(status), borderWidth: 2, tension: .25,
            pointRadius: 3, pointHoverRadius: 5, pointBackgroundColor: CARD, pointBorderWidth: 2, spanGaps: false,
        });
        otChart = new Chart(canvas, {
            type: 'line',
            data: { labels: [], datasets: historyStatuses.map(dataset) },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                layout: { padding: { top: 14, bottom: 14 } },
                onClick(event, elements, chart) {
                    const point = chart.getElementsAtEventForMode(event, 'nearest', { intersect: false, axis: 'x' }, true)[0] || elements[0];
                    const monthIndex = point ? idxs[point.index] : undefined;
                    if (monthIndex !== undefined) showMovementCodes(MONTH_KEYS[monthIndex], visiblePuroks);
                },
                scales: {
                    x: { min: 0, max: Math.max(0, MONTHS.length - 1), grid: { display: false }, border: { color: LINE }, ticks: { color: MUTED, padding: 8, autoSkip: true, maxRotation: 0, maxTicksLimit: 12 } },
                    y: { min: 0, grid: { color: LINE, drawTicks: false }, border: { display: false }, ticks: { color: MUTED, padding: 10, maxTicksLimit: 6, callback: v => nf.format(v) }, title: { display: false, text: 'Records', color: MUTED } },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 10, boxPadding: 4, cornerRadius: 6, usePointStyle: true,
                        backgroundColor: INK, titleColor: CARD, bodyColor: CARD,
                        callbacks: { label: c => `${c.dataset.label}: ${c.parsed.y === null ? 'No snapshot' : nf.format(c.parsed.y)}` },
                    },
                },
            },
        });
        $('tl-months').replaceChildren(...MONTHS.map((name, m) => {
            const [mon, yr] = name.split(' ');
            const b = el('button', 'chip'); b.type = 'button';
            b.append(el('b', '', mon), el('small', '', yr || ''));
            b.setAttribute('aria-label', name);
            b.addEventListener('click', () => {
                const all = isAllMonths(), single = state.s === state.e;
                if (all) state.s = state.e = m;
                else if (single && state.s === m) { state.s = 0; state.e = lastMonthIndex(); }
                else if (single) { const a = state.s; state.s = Math.min(a, m); state.e = Math.max(a, m); }
                else state.s = state.e = m;
                renderMonths(); renderOvertime();
            });
            return b;
        }));
        renderMonths(); renderPuroks(); renderOvertime();
    });
    $('month-clear').addEventListener('click', resetMonths);
    $('purok-clear').addEventListener('click', resetPuroks);
    $('ot-reset').addEventListener('click', () => { state.puroks = new Set(); state.s = 0; state.e = lastMonthIndex(); renderMonths(); renderPuroks(); renderOvertime(); });

    /* ---------- New baptisees (body rendered on first open) ---------- */
    // $newBaptisees from the controller: [{name, purok, grupo, status, evangelist, baptized_at: 'YYYY-MM-DD'}]
    const serverBaptisees = @json($newBaptisees);
    const plural = (n, w) => `${n} ${w}${n === 1 ? '' : 's'}`;
    function since(from) {
        const a = new Date(from + 'T00:00:00'), now = new Date(), b = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        let months = (b.getFullYear() - a.getFullYear()) * 12 + b.getMonth() - a.getMonth();
        let anchor = new Date(a.getFullYear(), a.getMonth() + months, a.getDate());
        if (anchor > b) { months--; anchor = new Date(a.getFullYear(), a.getMonth() + months, a.getDate()); }
        return { months, days: Math.round((b - anchor) / 86400000) };
    }
    const spanText = ({ months, days }) => months && days ? `${plural(months, 'month')} and ${plural(days, 'day')}` : months ? plural(months, 'month') : plural(days, 'day');
    $('bap-count').textContent = serverBaptisees.length || '';

    function renderBaptisees() {
        const list = serverBaptisees
            .map(b => ({ ...b, span: since(b.baptized_at) }))
            .sort((x, y) => y.baptized_at.localeCompare(x.baptized_at));
        $('bap-sub').textContent = list.length ? `Baptized within the last year: ${fmt(list.length)} in the system.` : 'Baptized within the last year.';
        const body = $('bap-body');
        if (!list.length) {
            const box = el('div', 'empty');
            box.append(el('strong', '', 'No newly baptized records were found in the past year.'), el('span', '', 'Filtered by kalagayan: MS, UWP, and MK.'));
            body.replaceChildren(box);
            return;
        }
        const table = el('table', 'tbl'), head = el('tr');
        ['Name', 'Purok / Grupo', 'Status', 'Evangelist', 'Days baptized'].forEach(h => head.append(el('th', '', h)));
        const thead = el('thead'); thead.append(head); table.append(thead);
        const tbody = el('tbody');
        list.forEach(b => {
            const tr = el('tr'), status = el('td'), tag = el('span', 'tag', b.status), days = el('td', '', spanText(b.span));
            tag.style.background = colorFor(String(b.status).toUpperCase());
            status.append(tag);
            days.append(el('small', '', 'Baptized ' + new Date(b.baptized_at + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })));
            tr.append(el('td', 'name', b.name), el('td', '', `Purok ${b.purok} / ${b.grupo}`), status, el('td', '', b.evangelist || 'None'), days);
            tbody.append(tr);
        });
        table.append(tbody);
        const wrap = el('div', 'tbl-wrap'); wrap.append(table);
        body.replaceChildren(wrap);
    }

    /* ---------- Registry updating monitoring (body rendered on first open) ---------- */
    const registryDetailsDialog = $('registry-details-modal');
    const registryEditDialog = $('registry-edit-modal');
    const registryEditForm = $('registry-edit-form');
    const registryEditSave = $('registry-edit-save');
    const RECORD_UPDATE_URL = @json(route('records.update', ['record' => '__RECORD_ID__']));
    const CSRF_TOKEN = @json(csrf_token());
    const CAN_EDIT_REGISTRY = $('dashboardApp').dataset.canEditRegistry === 'true';
    let registryEditTarget = null;
    const registryFieldConfig = {
        registry: { label: 'Registry number', key: 'registry_number', type: 'text', hint: 'Enter exactly 13 letters or numbers. RFID and blank values are not accepted.' },
        gender: { label: 'Gender', key: 'gender', type: 'select', options: ['Male', 'Female'] },
        purok: { label: 'Purok', key: 'purok', type: 'locked', hint: 'Purok assignments are not changed from Registry Updating.' },
        grupo: { label: 'Grupo', key: 'grupo', type: 'number', hint: 'Grupo can only be changed within this member’s current Purok.' },
        kapisanan: { label: 'Kapisanan', key: 'kapisanan', type: 'select', options: ['Binhi', 'Kadiwa', 'Buklod'] },
        date_of_baptism: { label: 'Date of baptism', key: 'date_of_baptism', type: 'date' },
        evangelist: { label: 'Evangelist', key: 'evangelist', type: 'text' },
        date_of_birth: { label: 'Birthday', key: 'date_of_birth', type: 'date' },
    };

    function openRegistryEdit(entry, onSaved) {
        const config = registryFieldConfig[entry.field];
        if (!CAN_EDIT_REGISTRY || !config || config.type === 'locked' || (entry.field === 'grupo' && !entry.can_edit_grupo)) return;
        registryEditTarget = { entry, config, onSaved };
        $('registry-edit-title').textContent = `Enter missing ${config.label.toLowerCase()}`;
        $('registry-edit-sub').textContent = `${entry.name} · ${entry.purok} · ${entry.status}`;
        $('registry-edit-label').textContent = config.label;
        $('registry-edit-hint').textContent = entry.field === 'grupo'
            ? `${config.hint} Allowed range for this Purok: ${entry.grupo_min}–${entry.grupo_max}. Purok cannot be changed here.`
            : (config.hint || '');
        $('registry-edit-error').hidden = true;

        let input;
        if (config.type === 'select') {
            input = el('select');
            input.required = true;
            input.id = 'registry-edit-value';
            input.append(new Option(`Select ${config.label.toLowerCase()}`, '', true, true));
            input.options[0].disabled = true;
            config.options.forEach(value => input.append(new Option(value, value)));
        } else {
            input = el('input');
            input.id = 'registry-edit-value';
            input.type = config.type;
            input.required = true;
            if (entry.field === 'registry') input.maxLength = 13;
            if (entry.field === 'grupo') {
                input.min = String(entry.grupo_min);
                input.max = String(entry.grupo_max);
                input.step = '1';
            }
            if (config.type === 'date') {
                const today = new Date();
                input.max = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            }
        }
        $('registry-edit-control').replaceChildren(input);
        registryEditDialog.showModal();
        input.focus();
    }

    const REGISTRY_LOOKUP_URL = @json(\Illuminate\Support\Facades\Route::has('records.registry-lookup') ? route('records.registry-lookup') : null);
    const REGISTRY_PATTERN = /^[A-Za-z0-9]{13}$/;
    const recordUrl = id => RECORD_UPDATE_URL.replace('__RECORD_ID__', encodeURIComponent(id));

    function registryFormatError(value) {
        if (!value) return 'Registry number cannot be blank.';
        if (value.toUpperCase() === 'RFID') return 'RFID is not accepted. Enter the 13-character registry number.';
        if (!REGISTRY_PATTERN.test(value)) return 'Registry number must be exactly 13 letters or numbers.';
        return null;
    }

    async function saveRecordField(id, key, value) {
        const response = await fetch(recordUrl(id), {
            method: 'PUT',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ [key]: value }),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validationMessage = result.errors ? Object.values(result.errors).flat()[0] : null;
            const failure = new Error(validationMessage || result.message || 'Unable to save this record.');
            // The server already rejects duplicates with: "...assigned to: NAME (record #ID, registry NUMBER)".
            const found = /assigned to:\s*(.+?)\s*\(record #(\d+),\s*registry\s*([^)\s]+)\)/i.exec(failure.message);
            if (found) failure.duplicate = { id: found[2], name: found[1], registry_number: found[3] };
            throw failure;
        }
        return result;
    }

    // Other records (never RFID) that already use this registry number. Needs the lookup route (see notes).
    async function findRegistryDuplicates(value, excludeId) {
        if (!REGISTRY_LOOKUP_URL) return [];
        const url = new URL(REGISTRY_LOOKUP_URL, window.location.origin);
        url.searchParams.set('registry_number', value);
        url.searchParams.set('exclude', String(excludeId));
        const response = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) throw new Error('Could not check for duplicate registry numbers. Nothing was saved.');
        const body = await response.json();
        return body.matches || [];
    }

    // Resolves to the list of changes to save, or null if cancelled. Confirm stays disabled until
    // every value is valid, unique, and at least one was changed.
    function askRegistryConflict(entry, value, matches) {
        return new Promise(resolve => {
            const dlg = $('registry-conflict-modal');
            const confirmBtn = $('registry-conflict-confirm');
            const list = $('registry-conflict-list');
            const rows = [
                ...matches.map(m => ({ id: m.id, name: m.name, original: String(m.registry_number).toUpperCase(), isNew: false })),
                { id: entry.id, name: entry.name, original: value, isNew: true },
            ];
            $('registry-conflict-sub').textContent = `There are now ${rows.length === 2 ? 'two' : rows.length} records with ${value}`;
            let settled = false, touched = false;
            const finish = result => { if (settled) return; settled = true; resolve(result); dlg.close(); };
            const currentValues = () => rows.map(r => r.input.value.trim().toUpperCase());
            const validate = () => {
                const values = currentValues();
                let ok = true;
                rows.forEach((row, i) => {
                    const v = values[i];
                    let message = registryFormatError(v);
                    if (!message && values.filter(x => x === v).length > 1) message = 'Still the same as another record.';
                    row.msg.textContent = touched && message ? message : '';
                    if (message) ok = false;
                });
                confirmBtn.disabled = !(ok && rows.some((row, i) => values[i] !== row.original));
            };
            list.replaceChildren(...rows.map((row, i) => {
                const wrap = el('div', 'conflict-row');
                const input = el('input');
                input.type = 'text'; input.maxLength = 13; input.autocomplete = 'off'; input.value = row.original;
                input.id = `conflict-input-${i}`;
                const label = el('label', '', `${i + 1}. ${row.name}${row.isNew ? ' (your input)' : ''}`);
                label.htmlFor = input.id;
                const msg = el('p', 'conflict-msg');
                msg.setAttribute('role', 'status');
                row.input = input; row.msg = msg;
                input.addEventListener('input', () => { touched = true; validate(); });
                wrap.append(label, input, msg);
                return wrap;
            }));
            confirmBtn.disabled = true;
            confirmBtn.onclick = () => {
                const values = currentValues();
                finish(rows.map((row, i) => ({ id: row.id, isNew: row.isNew, original: row.original, value: values[i] }))
                    .filter(change => change.isNew || change.value !== change.original));
            };
            dlg.onclose = () => finish(null); // Cancel, X, backdrop, and Esc all land here
            dlg.showModal();
            rows[0].input.focus();
        });
    }

    registryEditForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!registryEditTarget) return;
        const { entry, config, onSaved } = registryEditTarget;
        const input = $('registry-edit-value');
        const error = $('registry-edit-error');
        let value = input.value.trim();
        const fail = message => { error.textContent = message; error.hidden = false; };
        if (!value || !input.reportValidity()) return fail('Enter a valid value before saving.');
        if (entry.field === 'registry') {
            value = value.toUpperCase();
            const formatError = registryFormatError(value);
            if (formatError) return fail(formatError);
        }

        registryEditSave.disabled = true;
        registryEditSave.textContent = entry.field === 'registry' ? 'Checking…' : 'Saving…';
        error.hidden = true;
        let otherUpdated = false;
        try {
            let finalValue = value, saved = false;
            let matches = entry.field === 'registry' ? await findRegistryDuplicates(value, entry.id) : [];
            if (!matches.length) {
                try { await saveRecordField(entry.id, config.key, value); saved = true; }
                catch (exception) {
                    // No lookup route needed: the server's own duplicate rejection tells us who conflicts.
                    if (entry.field === 'registry' && exception.duplicate) matches = [exception.duplicate];
                    else throw exception;
                }
            }
            if (!saved) {
                const changes = await askRegistryConflict(entry, value, matches);
                if (!changes) return; // cancelled: nothing is saved, the input stays for editing
                registryEditSave.textContent = 'Saving…';
                for (const change of changes.filter(c => !c.isNew)) {
                    await saveRecordField(change.id, config.key, change.value);
                    otherUpdated = true;
                }
                finalValue = changes.find(c => c.isNew).value;
                await saveRecordField(entry.id, config.key, finalValue);
            }
            registryEditDialog.close();
            if (onSaved) onSaved(entry, config, finalValue);
        } catch (exception) {
            fail((exception.message || 'Unable to save this record.') + (otherUpdated ? ' The other record was already updated.' : ''));
        } finally {
            registryEditSave.disabled = false;
            registryEditSave.textContent = 'Save missing data';
        }
    });
    registryEditDialog.addEventListener('close', () => { registryEditTarget = null; });

    // Values saved this session, so reopening a list still shows them as done (charts stay stale until reload).
    const registrySaved = new Map();
    const entryKey = entry => `${entry.id}:${entry.field}`;

    function renderRegistryAction(action, row, entry, fieldLabel) {
        const config = registryFieldConfig[entry.field];
        const onSaved = (savedEntry, savedConfig, value) => {
            registrySaved.set(entryKey(savedEntry), value);
            renderRegistryAction(action, row, savedEntry, fieldLabel);
            const status = $('registry-details-status');
            status.textContent = `Saved ${savedConfig.label.toLowerCase()} for ${savedEntry.name}: ${value}. Charts update on the next page reload.`;
            status.hidden = false;
            let next = row.nextElementSibling;
            while (next) {
                const button = next.querySelector('button.reg-count:not(:disabled)');
                if (button) { button.focus(); break; }
                next = next.nextElementSibling;
            }
        };
        const savedValue = registrySaved.get(entryKey(entry));
        if (savedValue !== undefined) {
            row.classList.add('is-saved');
            const box = el('div', 'saved-cell');
            const again = el('button', 'link-btn', 'Edit again');
            again.type = 'button';
            again.addEventListener('click', () => openRegistryEdit(entry, onSaved));
            box.append(el('span', '', `✓ Saved: ${savedValue}`), again);
            action.replaceChildren(box);
            return;
        }
        const edit = el('button', 'reg-count', !CAN_EDIT_REGISTRY ? 'No edit access' : (config?.type === 'locked' ? 'Monthly portal' : 'Enter data'));
        edit.type = 'button';
        edit.disabled = !CAN_EDIT_REGISTRY || !config || config.type === 'locked' || (entry.field === 'grupo' && !entry.can_edit_grupo);
        edit.title = !CAN_EDIT_REGISTRY
            ? 'Editing is disabled for this account.'
            : (entry.field === 'grupo' && !entry.can_edit_grupo
                ? 'Assign this member to a Purok before entering a Grupo.'
                : (config?.type === 'locked' ? config.hint : `Enter missing ${config?.label?.toLowerCase() || 'data'}`));
        edit.addEventListener('click', () => openRegistryEdit(entry, onSaved));
        action.replaceChildren(edit);
    }

    function showRegistryDetails(label, group, entries) {
        $('registry-details-status').hidden = true;
        $('registry-details-title').textContent = `Missing ${label} - ${group}`;
        $('registry-details-sub').textContent = `${fmt(entries.length)} record${entries.length === 1 ? '' : 's'}`;
        const body = $('registry-details-body');
        if (!entries.length) {
            body.replaceChildren(el('div', 'empty', 'No records are missing this field in this group.'));
        } else {
            const table = el('table', 'tbl'), head = el('tr');
            ['Name', 'Purok', 'Kalagayan', 'Action'].forEach(value => head.append(el('th', '', value)));
            const thead = el('thead'); thead.append(head); table.append(thead);
            const tbody = el('tbody');
            entries.forEach(entry => {
                const row = el('tr');
                row.append(el('td', 'name', entry.name), el('td', '', entry.purok), el('td', '', entry.status));
                const action = el('td');
                renderRegistryAction(action, row, entry, label);
                row.append(action);
                tbody.append(row);
            });
            table.append(tbody);
            const wrap = el('div', 'tbl-wrap'); wrap.append(table);
            body.replaceChildren(wrap);
        }
        registryDetailsDialog.showModal();
    }

    function renderRegistry() {
        const { REG_FIELDS, registryPuroks, regTotals, regAll, D } = ensure();
        const regOverall = regAll * REG_FIELDS.length;
        const groupName = p => p.id === 'Unassigned' ? 'Unassigned' : `Purok ${p.id}`;
        const table = el('table', 'tbl'), head = el('tr');
        ['Data', ...registryPuroks.map(groupName), 'Total'].forEach(h => head.append(el('th', '', h)));
        const thead = el('thead'); thead.append(head); table.append(thead);
        const tbody = el('tbody');
        const countButton = (value, label, group, entries) => {
            const button = el('button', `reg-count${value ? '' : ' is-zero'}`, fmt(value));
            button.type = 'button';
            button.setAttribute('aria-label', `${value} records missing ${label} in ${group}`);
            button.addEventListener('click', () => showRegistryDetails(label, group, entries));
            return button;
        };
        REG_FIELDS.forEach(([label, miss, details]) => {
            const tr = el('tr');
            tr.append(el('td', '', label));
            miss.forEach((value, index) => {
                const cell = el('td', value ? 'gap-cell' : 'ok-cell');
                const group = groupName(registryPuroks[index]);
                cell.append(countButton(value, label, group, details[index].map(entry => ({ ...entry, purok: group }))));
                tr.append(cell);
            });
            const allEntries = details.flatMap((entries, index) => {
                const group = groupName(registryPuroks[index]);
                return entries.map(entry => ({ ...entry, purok: group }));
            });
            const totalCell = el('td', 'tot');
            totalCell.append(countButton(add(miss), label, 'all puroks', allEntries));
            tr.append(totalCell);
            tbody.append(tr);
        });
        const foot = el('tfoot'), fr = el('tr');
        fr.append(el('td', '', 'Encoded / active data'));
        regTotals.forEach((t, i) => fr.append(el('td', '', `${fmt(t * REG_FIELDS.length - add(REG_FIELDS.map(f => f[1][i])))} / ${fmt(t * REG_FIELDS.length)}`)));
        fr.append(el('td', 'tot', `${fmt(D.registryEncoded)} / ${fmt(regOverall)}`));
        foot.append(fr);
        table.append(tbody, foot);
        const wrap = el('div', 'tbl-wrap'); wrap.append(table);

        const summary = el('div', 'reg-summary'), summaryGrid = el('div', 'reg-summary-grid');
        summary.append(el('h3', '', 'Summary'));
        REG_FIELDS.forEach(([label, miss]) => {
            const done = regAll - add(miss), pct = regAll ? done / regAll * 100 : 0;
            const item = el('div', 'sum-item'), top = el('div', 'sum-top'), meter = el('div', 'meter'), fill = el('span');
            fill.style.width = pct + '%';
            meter.append(fill);
            top.append(el('span', '', `${label}: ${fmt(done)} of ${fmt(regAll)}`), el('span', '', pct.toFixed(2) + '%'));
            item.append(top, meter);
            summaryGrid.append(item);
        });
        summary.append(summaryGrid);
        const layout = el('div', 'reg'); layout.append(wrap, summary);
        $('reg-body').replaceChildren(layout);
    }

    // Modal bodies render on first open; a failure shows a retry inside the modal.
    function lazyModal(buttonId, dialogId, bodyId, render) {
        const dlg = $(dialogId), body = $(bodyId);
        let done = false;
        const attempt = () => {
            try { render(); done = true; }
            catch (exception) {
                console.error(`Dashboard dialog "${dialogId}" failed:`, exception);
                const box = el('div', 'state-err', null);
                const retry = el('button', 'retry', 'Retry');
                retry.type = 'button';
                retry.addEventListener('click', () => { data = null; attempt(); });
                box.setAttribute('role', 'alert');
                box.append(el('strong', '', "This content couldn't be displayed."), retry);
                body.replaceChildren(box);
            }
        };
        $(buttonId).addEventListener('click', () => { if (!done) attempt(); dlg.showModal(); });
    }
    lazyModal('open-baptisee', 'baptisee-modal', 'bap-body', renderBaptisees);
    lazyModal('open-registry', 'registry-modal', 'reg-body', renderRegistry);

    /* ---------- Boot: summary first, secondary sections on the next frames, chart when near ---------- */
    runHero();
    requestAnimationFrame(() => {
        runGauges();
        runPurok();
        requestAnimationFrame(() => { runCfoA(); runCfoB(); runGender(); });
    });
    const otHost = $('ot-panel');
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(entries => {
            if (entries.some(e => e.isIntersecting)) { io.disconnect(); runOt(); }
        }, { rootMargin: '300px' });
        io.observe(otHost);
    } else {
        runOt();
    }

    /* ---------- Re-theme chart when the navbar's light/dark switch changes ---------- */
    function applyChartTheme() {
        readTheme();
        if (typeof Chart === 'undefined' || !otChart) return;
        Chart.defaults.color = MUTED;
        otChart.data.datasets.forEach(set => { set.pointBackgroundColor = CARD; });
        const { x, y } = otChart.options.scales;
        x.border.color = LINE; x.ticks.color = MUTED;
        y.grid.color = LINE; y.ticks.color = MUTED; y.title.color = MUTED;
        const tip = otChart.options.plugins.tooltip;
        tip.backgroundColor = INK; tip.titleColor = CARD; tip.bodyColor = CARD;
        otChart.update('none');
    }
    new MutationObserver(applyChartTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
});
</script>
@endpush