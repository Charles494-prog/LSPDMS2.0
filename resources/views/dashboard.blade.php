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

    <!-- Hero: the one bold element -->
    <section class="hero" aria-labelledby="hero-label">
        <div class="hero-num">
            <span class="hero-value" id="hero-total">0</span>
            <span class="hero-label" id="hero-label">active records</span>
        </div>
        <div class="hero-split">
            <div class="split-bar" id="split-bar" role="img" aria-label="Active records by kalagayan"></div>
            <ul class="split-legend" id="split-legend"></ul>
        </div>
        <div class="hero-side">
            <span class="hero-side-value" id="hero-inactive">0</span>
            <span class="hero-side-label">inactive</span>
        </div>
    </section>

    <div class="grid">
        <section class="panel span-7" aria-labelledby="purok-title">
            <h2 id="purok-title">Active records by purok</h2>
            <div class="purok-bars" id="purok-chart-box" role="group" aria-label="Active records by Purok"></div>
            <p class="purok-chart-detail" id="purok-chart-detail" role="status" aria-live="polite">Select a Purok bar to see its MS, UWP, MK, and total counts.</p>
            <ul class="legend" id="status-legend"></ul>
        </section>

        <section class="panel span-5" aria-labelledby="monitor-title">
            <h2 id="monitor-title">Updating progress</h2>
            <div class="gauges">
                <figure class="gauge" data-value="{{ number_format($dashboardMetrics['r210Updating'], 2, '.', '') }}">
                    <svg viewBox="0 0 120 120" role="img" aria-label="R2-10 endorsement completion"><circle class="g-track" cx="60" cy="60" r="50"/><circle class="g-bar" cx="60" cy="60" r="50" transform="rotate(-90 60 60)"/><text class="g-text" x="60" y="60">0%</text></svg>
                    <figcaption>R2-10 Updating</figcaption>
                </figure>
                <figure class="gauge" data-value="{{ number_format($dashboardMetrics['registryCompletion'], 2, '.', '') }}">
                    <button type="button" class="gauge-btn" id="open-registry" aria-haspopup="dialog">
                        <svg viewBox="0 0 120 120" role="img" aria-label="Registry Updating"><circle class="g-track" cx="60" cy="60" r="50"/><circle class="g-bar" cx="60" cy="60" r="50" transform="rotate(-90 60 60)"/><text class="g-text" x="60" y="60">0%</text></svg>
                        <span class="cap">Registry Updating</span>
                        <span class="cap-hint">See what's missing</span>
                    </button>
                </figure>
                <figure class="gauge" data-value="{{ number_format($dashboardMetrics['localeActivePercentage'], 2, '.', '') }}">
                    <svg viewBox="0 0 120 120" role="img" aria-label="Active records as a percentage of Locale S Number"><circle class="g-track" cx="60" cy="60" r="50"/><circle class="g-bar" cx="60" cy="60" r="50" transform="rotate(-90 60 60)"/><text class="g-text" x="60" y="60">0%</text></svg>
                    <figcaption>LSP / Locale</figcaption>
                </figure>
            </div>
        </section>

        <section class="panel span-4" aria-labelledby="gender-title">
            <h2 id="gender-title">Active records by gender</h2>
            <div class="gender-row">
                <div class="gender-top"><button type="button" class="gender-drill" data-gender="female">Female <span aria-hidden="true">↗</span></button><strong id="pct-female">0%</strong></div>
                <div class="gbar" id="bar-female" role="img" aria-label="Female share of active records"></div>
            </div>
            <div class="gender-row">
                <div class="gender-top"><button type="button" class="gender-drill" data-gender="male">Male <span aria-hidden="true">↗</span></button><strong id="pct-male">0%</strong></div>
                <div class="gbar" id="bar-male" role="img" aria-label="Male share of active records"></div>
            </div>
            <ul class="legend" id="gender-legend"></ul>
        </section>

        <section class="panel span-4 cfo-panel" aria-labelledby="cfo-group-chart-title">
            <div class="panel-row">
                <h2 id="cfo-group-chart-title">Breakdown Per CFO Group</h2>
                <div class="seg" role="radiogroup" aria-label="Filter by Kalagayan" id="cfo-status-seg"></div>
            </div>
            <div class="chart-box chart-doughnut" style="height:260px">
                <canvas id="cfo-group-chart" role="img" aria-label="Breakdown per CFO group"></canvas>
                <div class="chart-center" aria-hidden="true"><strong id="cfo-group-total">0</strong><span>RECORDS</span></div>
            </div>
            <ul class="legend legend-values" id="cfo-group-legend"></ul>
        </section>

        <section class="panel span-4 cfo-panel" aria-labelledby="cfo-status-chart-title">
            <div class="panel-row">
                <h2 id="cfo-status-chart-title">Breakdown of CFO per Kalagayan</h2>
                <div class="seg" role="radiogroup" aria-label="Filter by CFO group" id="cfo-group-seg"></div>
            </div>
            <div class="chart-box chart-doughnut" style="height:260px">
                <canvas id="cfo-status-chart" role="img" aria-label="Breakdown of CFO per Kalagayan"></canvas>
                <div class="chart-center" aria-hidden="true"><strong id="cfo-status-total">0</strong><span>RECORDS</span></div>
            </div>
            <ul class="legend legend-values" id="cfo-status-legend"></ul>
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

        <section class="panel span-12" aria-labelledby="ot-title">
            <div class="panel-row">
                <div><h2 id="ot-title">Records over time</h2><p class="ot-help">Monthly MS, UWP, and MK snapshots. Select a point to view its movement codes.</p></div>
                <ul class="legend" id="ot-legend"></ul>
            </div>
            <p class="ot-current" id="ot-current" role="status" aria-live="polite"></p>
            <div class="ot">
                <div class="ot-chart">
                    <canvas id="ot-chart" role="img" aria-label="Records over time"></canvas>
                    <p class="ot-empty" id="ot-empty" hidden>No data for the selected period.</p>
                </div>
                <aside class="filters">
                    <div class="filter-head">
                        <span>Month</span>
                        <button type="button" class="link-btn" id="month-clear">All periods</button>
                    </div>
                    <div class="chips months" id="tl-months" role="group" aria-label="Month filter"></div>
                    <p class="hint" id="month-hint">Select a month, then another to pick a range.</p>
                    <div class="filter-head">
                        <span>Purok</span>
                        <button type="button" class="link-btn" id="purok-clear">All puroks</button>
                    </div>
                    <div class="chips" id="purok-list" role="group" aria-label="Purok filter"></div>
                    <p class="hint">Select several puroks to combine them.</p>
                </aside>
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
</div>
@endsection

@push('styles')
<style>
    .dash {
        --bg: #eef1f4; --card: #fff; --ink: #10233a; --muted: #5b6b7c; --line: #dde3ea;
        --ms: #16a34a; --uwp: #f97316; --mk: #eab308; --off: #9aa5b1;
        max-width: 1500px; margin: 0 auto; padding: 1.25rem; border-radius: 18px;
        background: var(--bg); color: var(--ink); font-variant-numeric: tabular-nums;
    }
    .dash h1, .dash h2, .dash p, .dash ul, .dash figure { margin: 0; padding: 0; }
    .dash ul { list-style: none; }

    .dash-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .dash-head h1 { font-size: clamp(1.8rem, 4vw, 2.6rem); font-weight: 800; letter-spacing: -.03em; line-height: 1.05; }
    .dash-lede { margin-top: .3rem; color: var(--muted); font-size: .95rem; }
    .primary-btn { display: inline-flex; align-items: center; gap: .6rem; padding: .7rem 1.2rem; border: 0; border-radius: 10px; background: var(--ink); color: #fff; font: inherit; font-size: .92rem; font-weight: 700; cursor: pointer; }
    .primary-btn:hover { background: #1b3a5c; }
    .badge { min-width: 1.5rem; padding: .05rem .45rem; border-radius: 99px; background: #fff; color: var(--ink); font-size: .78rem; text-align: center; }
    .badge:empty { display: none; }

    .gauge-btn { display: block; width: 100%; padding: .4rem; border: 1px solid transparent; border-radius: 10px; background: none; color: inherit; font: inherit; cursor: pointer; }
    .gauge-btn:hover { border-color: var(--line); background: var(--bg); }
    .cap { display: block; margin-top: .5rem; font-size: .85rem; font-weight: 650; }
    .cap-hint { display: block; margin-top: .1rem; color: var(--ms); font-size: .75rem; font-weight: 600; }

    /* Modals */
    .modal { width: min(900px, calc(100vw - 2rem)); max-height: calc(100vh - 2rem); padding: 0; border: 0; border-radius: 14px; background: var(--card); color: var(--ink); box-shadow: 0 24px 60px rgba(16,35,58,.35); overflow: hidden; }
    .modal-wide { width: min(1140px, calc(100vw - 2rem)); }
    .movement-code-modal { width: min(760px, calc(100vw - 2rem)); max-height: calc(100dvh - 2rem); }
    .movement-code-modal .modal-head { padding:.9rem 1.1rem; }
    .movement-code-modal .modal-body { display:grid; gap:.8rem; padding:1rem 1.1rem; }
    .movement-code-modal .empty { padding:.8rem; border:1px solid var(--line); border-radius:9px; background:var(--bg); }
    .movement-count-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:.55rem; }
    .movement-purok-card { padding:.7rem .8rem; border:1px solid var(--line); border-radius:9px; background:var(--bg); }
    .movement-purok-card h3 { margin:0 0 .5rem; font-size:.82rem; font-weight:750; }
    .movement-purok-counts { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.35rem; }
    .movement-purok-count { display:flex; justify-content:space-between; gap:.35rem; color:var(--muted); font-size:.76rem; }
    .movement-purok-count strong { color:var(--ink); font-weight:750; }
    @media(max-width:520px) {
        .movement-code-modal .modal-head { padding:.75rem .85rem; }
        .movement-code-modal .modal-body { padding:.8rem; }
        .movement-count-grid { grid-template-columns:1fr; }
    }
    .modal[open] { display: flex; flex-direction: column; }
    .modal::backdrop { background: rgba(16,35,58,.55); }
    .modal-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: 1.1rem 1.4rem; border-bottom: 1px solid var(--line); }
    .modal-head h2 { font-size: 1.25rem; font-weight: 800; letter-spacing: -.02em; }
    .modal-head p { margin-top: .2rem; color: var(--muted); font-size: .85rem; }
    .modal-x { flex: none; width: 34px; height: 34px; border: 0; border-radius: 8px; background: var(--bg); color: var(--ink); font-size: 1.4rem; line-height: 1; cursor: pointer; }
    .modal-x:hover { background: var(--line); }
    .modal-body { padding: 1.1rem 1.4rem 1.4rem; overflow: auto; }
    .registry-edit-modal { width: min(480px, calc(100vw - 2rem)); }
    .registry-edit-form { display: grid; gap: .7rem; }
    .registry-edit-label { font-size: .85rem; font-weight: 750; }
    .registry-edit-form input, .registry-edit-form select {
        width: 100%; min-height: 2.7rem; padding: .55rem .7rem; border: 1px solid var(--line); border-radius: 8px;
        background: var(--card); color: var(--ink); font: inherit;
    }
    .registry-edit-hint { color: var(--muted); font-size: .78rem; line-height: 1.45; }
    .registry-edit-error { color: #b42318; font-size: .82rem; font-weight: 650; }
    .registry-edit-error[hidden] { display: none; }
    .registry-edit-actions { display: flex; justify-content: flex-end; gap: .6rem; margin-top: .3rem; }
    .registry-edit-actions .btn { padding: .6rem .9rem; border: 1px solid var(--line); border-radius: 8px; background: var(--card); color: var(--ink); font: inherit; font-weight: 700; cursor: pointer; }
    .registry-edit-actions .btn-primary { border-color: var(--ink); background: var(--ink); color: #fff; }

    .tbl { width: 100%; border-collapse: collapse; font-size: .88rem; }
    .tbl th { padding: .6rem .75rem; border-bottom: 1px solid var(--line); color: var(--muted); font-size: .78rem; font-weight: 700; text-align: left; white-space: nowrap; }
    .tbl td { padding: .7rem .75rem; border-bottom: 1px solid var(--line); }
    .tbl .name { font-weight: 700; }
    .tbl small { display: block; margin-top: .1rem; color: var(--muted); font-size: .75rem; }
    .tag { display: inline-block; padding: .15rem .6rem; border-radius: 99px; color: #fff; font-size: .75rem; font-weight: 700; }
    .empty { padding: 3rem 1rem; text-align: center; color: var(--muted); }
    .empty strong { display: block; margin-bottom: .3rem; color: var(--ink); font-size: 1.15rem; }

    .reg { display: block; }
    .tbl-wrap { overflow-x: auto; }
    .reg .tbl { min-width: 760px; table-layout: fixed; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
    .reg .tbl th, .reg .tbl td { width: auto; text-align: center; vertical-align: middle; }
    .reg .tbl th:first-child, .reg .tbl td:first-child { position: sticky; left: 0; width: 140px; background: var(--card); text-align: left; font-weight: 700; z-index: 1; }
    .reg .tbl thead th { position: sticky; top: 0; background: #f2f6fa; color: var(--ink); z-index: 2; }
    .reg .tbl thead th:first-child { background: #f2f6fa; z-index: 3; }
    .reg .tbl tbody tr:nth-child(even) td:not(.gap-cell):not(.ok-cell):not(.tot) { background: #fafbfd; }
    .reg .gap-cell { background: #fff4e8; color: #964508; font-weight: 700; }
    .reg .ok-cell { color: var(--off); }
    .reg-count { display: inline-grid; min-width: 2.2rem; min-height: 2rem; place-items: center; padding: .2rem .55rem; border: 1px solid #f3d4b6; border-radius: 999px; background: #fff; color: #964508; font: inherit; font-weight: 750; cursor: pointer; }
    .reg-count:hover { border-color: var(--ms); background: #eef5fb; color: var(--ms); }
    .reg-count.is-zero { border-color: transparent; background: transparent; color: #8995a2; }
    .reg-count.is-zero:hover { border-color: var(--line); background: #f5f8fb; color: var(--ms); }
    .reg .tot { background: #eef3f8; font-weight: 700; }
    .reg tfoot td { border-top: 2px solid #c8d3df; background: #f6f8fb; font-weight: 700; }
    .reg-summary { margin-top: 1.3rem; padding: 1rem; border: 1px solid var(--line); border-radius: 10px; background: #fafbfd; }
    .reg-summary h3 { margin: 0 0 .8rem; font-size: 1rem; font-weight: 800; }
    .reg-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
    .sum-item { min-width: 0; padding: .7rem; border: 1px solid var(--line); border-radius: 8px; background: var(--card); }
    .sum-top { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; font-size: .82rem; }
    .sum-top span:first-child { overflow-wrap: anywhere; }
    .sum-top span:last-child { flex: none; font-weight: 750; }
    .meter { height: 7px; margin-top: .55rem; border-radius: 99px; background: var(--bg); overflow: hidden; }
    .meter span { display: block; height: 100%; border-radius: inherit; }
    @media (max-width: 900px) { .reg-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 560px) { .reg-summary-grid { grid-template-columns: 1fr; } }

    /* Hero */
    .hero {
        display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 2rem;
        padding: 1.6rem 1.8rem; margin-bottom: .75rem; border-radius: 14px; background: var(--ink); color: #fff;
    }
    .hero-num { display: grid; }
    .hero-value { font-size: clamp(3.4rem, 8vw, 5.6rem); font-weight: 800; line-height: .95; letter-spacing: -.04em; }
    .hero-label { margin-top: .4rem; color: #a9bbcf; font-size: .95rem; }
    .split-bar { display: flex; height: 26px; gap: 3px; }
    .split-bar span { display: block; height: 100%; width: 0; border-radius: 3px; transition: width .9s cubic-bezier(.2,.8,.2,1); }
    .split-legend { display: flex; flex-wrap: wrap; gap: .4rem 1.4rem; margin-top: .8rem; font-size: .88rem; color: #d6e0ea; }
    .split-legend li { display: flex; align-items: center; gap: .4rem; }
    .split-legend strong { color: #fff; }
    .hero-side { display: grid; justify-items: end; padding-left: 2rem; border-left: 1px solid rgba(255,255,255,.18); }
    .hero-side-value { font-size: 2rem; font-weight: 750; line-height: 1; }
    .hero-side-label { margin-top: .3rem; color: #a9bbcf; font-size: .85rem; }

    /* Grid + panels */
    .grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: .75rem; }
    .span-4 { grid-column: span 4; } .span-5 { grid-column: span 5; } .span-6 { grid-column: span 6; } .span-7 { grid-column: span 7; } .span-12 { grid-column: span 12; }
    .panel { min-width: 0; padding: 1.15rem 1.25rem; border: 1px solid var(--line); border-radius: 12px; background: var(--card); }
    .panel h2 { font-size: 1rem; font-weight: 700; letter-spacing: -.01em; }
    .panel > h2 { margin-bottom: .9rem; }
    .panel-row { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; margin-bottom: .9rem; }
    .panel-row > h2 { min-width: 0; margin: 0; }
    .chart-box { position: relative; min-width: 0; }
    .cfo-panel { display:flex; min-height:0; flex-direction:column; }
    .cfo-panel .chart-box { flex:1 1 auto; }
    .chart-center { position: absolute; top: 50%; left: 50%; display: grid; justify-items: center; gap: 2px; min-width: 74px; padding: .15rem .3rem; transform: translate(-50%, -50%); border-radius: 8px; background: var(--card); pointer-events: none; }
    .chart-center strong { color: var(--ink); font-size: 1.3rem; font-weight: 800; line-height: 1.1; }
    .chart-center span { color: var(--muted); font-size: .58rem; font-weight: 750; letter-spacing: .04em; }
    #cfo-group-chart, #cfo-status-chart { cursor: pointer; }

    .legend { display: flex; flex-wrap: wrap; gap: .3rem 1rem; margin-top: .7rem; font-size: .8rem; color: var(--muted); }
    .legend li { display: flex; align-items: center; gap: .4rem; }
    .panel-row .legend { margin-top: 0; }
    .legend i, .split-legend i { width: 10px; height: 10px; border-radius: 2px; display: inline-block; flex: none; }
    .legend-values { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .legend-values li { justify-content: space-between; padding: .25rem 0; border-bottom: 1px solid var(--line); }
    .legend-values li span { display: flex; align-items: center; gap: .4rem; }
    .legend-values strong { color: var(--ink); }

    /* Gauges */
    .gauges { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; }
    .gauge { text-align: center; }
    .gauge svg { width: min(100%, 140px); height: auto; }
    .gauge figcaption { margin-top: .5rem; font-size: .85rem; font-weight: 650; }
    .g-track { fill: none; stroke: var(--line); stroke-width: 10; }
    .g-bar { fill: none; stroke: var(--ms); stroke-width: 10; stroke-linecap: round; stroke-dasharray: 314.16; stroke-dashoffset: 314.16; transition: stroke-dashoffset 1.1s cubic-bezier(.2,.8,.2,1); }
    .g-text { fill: var(--ink); font-size: 17px; font-weight: 750; text-anchor: middle; dominant-baseline: central; }

    /* Gender */
    .gender-row + .gender-row { margin-top: 1rem; }
    .gender-top { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: .35rem; font-size: .9rem; }
    .gender-top strong { font-size: 1.5rem; letter-spacing: -.02em; }
    .gender-drill { padding: 0; border: 0; background: transparent; color: var(--ink); font: inherit; cursor: pointer; }
    .gender-drill span { color: var(--ms); font-size: .75rem; opacity: 0; transition: opacity .15s ease; }
    .gender-drill:hover span,.gender-drill:focus-visible span { opacity: 1; }
    .gender-drill:hover { color: var(--ms); text-decoration: underline; }
    .gbar { display: flex; height: 16px; overflow: hidden; border-radius: 4px; background: var(--bg); }
    .gbar span { display: block; height: 100%; width: 0; transition: width .9s cubic-bezier(.2,.8,.2,1); }

    /* Segmented control + chips */
    .seg { display: inline-flex; flex-wrap: wrap; gap: 3px; max-width: 100%; padding: 4px; border: 1px solid var(--line); border-radius: 10px; background: #f4f7fa; }
    .seg button { min-width: 42px; padding: .38rem .72rem; border: 0; border-radius: 7px; background: transparent; color: var(--muted); font: inherit; font-size: .78rem; font-weight: 750; cursor: pointer; transition: background .18s ease, color .18s ease, box-shadow .18s ease; }
    .seg button:hover { color: var(--ink); background: rgba(255,255,255,.7); }
    .seg button[aria-checked="true"] { background: var(--card); color: var(--ms); box-shadow: 0 1px 3px rgba(16,35,58,.16); }

    .ot { display: grid; grid-template-columns: minmax(0, 1fr) 260px; gap: 1.25rem; }
    .ot-help { margin-top:.2rem; color:var(--muted); font-size:.78rem; }
    .ot-current { margin:.2rem 0 .9rem; padding:.65rem .8rem; border:1px solid var(--line); border-radius:8px; background:var(--bg); color:var(--ink); font-size:.83rem; font-weight:700; }
    .movement-count-summary { margin:0 0 .8rem; padding:.7rem .8rem; border-radius:8px; background:var(--bg); color:var(--ink); font-size:.83rem; line-height:1.6; }
    .ot-chart { position: relative; height: 340px; min-width: 0; }
    .ot-empty { position: absolute; inset: 0; display: grid; place-items: center; color: var(--muted); font-size: .9rem; }
    .ot-empty[hidden] { display: none; }
    .filters { padding-left: 1.25rem; border-left: 1px solid var(--line); }
    .filter-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: .5rem; font-size: .82rem; font-weight: 700; }
    .link-btn { padding: 0; border: 0; background: none; color: var(--ms); font: inherit; font-size: .78rem; font-weight: 650; cursor: pointer; }
    .link-btn:hover { text-decoration: underline; }
    .chips { display: flex; flex-wrap: wrap; gap: .3rem; }
    .chips.months { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .chip { padding: .38rem .5rem; border: 1px solid var(--line); border-radius: 6px; background: var(--card); color: var(--ink); font: inherit; font-size: .78rem; cursor: pointer; }
    .chip:hover { border-color: var(--ink); }
    .chip[aria-pressed="true"] { border-color: var(--ink); background: var(--ink); color: #fff; }
    .chip:disabled { opacity: .4; cursor: default; }
    .hint { margin: .5rem 0 1.1rem; color: var(--muted); font-size: .75rem; }

    .chart-error { position:absolute; z-index:2; inset:0; display:grid; place-items:center; margin:0; padding:1rem; background:var(--card); color:var(--muted); font-size:.83rem; text-align:center; }
    .chart-empty { display:grid; height:100%; place-items:center; padding:1rem; color:var(--muted); font-size:.9rem; text-align:center; }
    .purok-bars { display:grid; max-height:320px; gap:.32rem; overflow-y:auto; padding:.2rem .25rem .2rem 0; }
    .purok-row { display:grid; grid-template-columns:minmax(100px, 150px) minmax(0, 1fr) 42px; align-items:center; gap:.55rem; width:100%; min-height:28px; padding:.18rem .3rem; border:0; border-radius:6px; background:transparent; color:var(--ink); text-align:left; cursor:pointer; }
    .purok-row:hover,.purok-row:focus-visible { background:var(--bg); }
    .purok-row-name { overflow:hidden; font-size:.78rem; font-weight:650; line-height:1.2; text-align:left; text-overflow:ellipsis; white-space:nowrap; }
    .purok-row-track { display:flex; width:100%; min-width:0; height:20px; overflow:hidden; border-radius:4px; background:var(--bg); }
    .purok-row-segment { display:flex; min-width:0; height:100%; flex:0 0 auto; align-items:center; justify-content:center; overflow:hidden; font-size:.68rem; font-weight:750; white-space:nowrap; }
    .purok-row-total { color:var(--muted); font-size:.76rem; font-weight:750; text-align:right; }
    .purok-chart-detail { min-height:1.25rem; margin:.5rem 0 0; color:var(--muted); font-size:.82rem; font-weight:600; }
    .dash a:focus-visible, .dash button:focus-visible { outline: 3px solid #5b9dff; outline-offset: 2px; }
    @media (prefers-reduced-motion: reduce) { .dash *, .dash *::before { transition: none !important; } }

    @media (max-width: 1100px) {
        .span-4 { grid-column: span 6; } .span-5, .span-7, .cfo-panel { grid-column: span 12; }
    }
    @media (max-width: 800px) {
        .hero { grid-template-columns: 1fr; gap: 1.1rem; }
        .hero-side { justify-items: start; padding: 1rem 0 0; border-left: 0; border-top: 1px solid rgba(255,255,255,.18); }
        .ot { grid-template-columns: 1fr; }
        .filters { padding: 1rem 0 0; border-left: 0; border-top: 1px solid var(--line); }
    }
    @media (max-width: 640px) {
        .span-4, .span-6 { grid-column: span 12; }
        .gauges { grid-template-columns: 1fr; }
        .ot-chart { height: 280px; }
        .purok-row { grid-template-columns:minmax(88px, 112px) minmax(0, 1fr) 34px; gap:.35rem; }
        .purok-row-name { font-size:.72rem; }
        .purok-row-track { height:18px; }
        .purok-row-segment { font-size:.62rem; }
        .chart-doughnut { height: 230px !important; }
        .panel-row { align-items: flex-start; }
        .panel-row .seg { width: 100%; justify-content: space-between; }
        .panel-row .seg button { flex: 1 1 auto; }
    }
</style>
@endpush

@push('scripts')
@vite('resources/js/dashboard.js')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const hasChart = typeof Chart !== 'undefined';
    const $ = id => document.getElementById(id);
    const css = getComputedStyle($('hero-total').closest('.dash'));
    const INK = css.getPropertyValue('--ink').trim(), MUTED = css.getPropertyValue('--muted').trim(), LINE = css.getPropertyValue('--line').trim();
    if (hasChart) {
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.color = MUTED;
        Chart.defaults.animation = false;
    }

    /* ---------- Database-backed dashboard metrics ---------- */
    const DASHBOARD = @json($dashboardMetrics);
    const COLORS = { MS: '#16a34a', UWP: '#f97316', MK: '#eab308', INACTIVE: '#9aa5b1', UNSPECIFIED: '#c8ced5' };
    const EXTRA_COLORS = ['#60a5fa', '#3b82f6', '#93c5fd', '#2563eb', '#bfdbfe'];
    const colorFor = (code, index = 0) => COLORS[code] || EXTRA_COLORS[index % EXTRA_COLORS.length];
    const KEYS = DASHBOARD.activeStatuses;
    const NAMES = Object.fromEntries(KEYS.map(code => [code, code]));
    const statuses = DASHBOARD.statuses;
    const active = Object.fromEntries(KEYS.map(code => [code, statuses.find(status => status.code === code)?.count || 0]));
    const totalActive = DASHBOARD.activeCount;
    const inactive = DASHBOARD.inactiveCount;
    const puroks = DASHBOARD.puroks.map(p => ({
        id: p.id,
        name: p.name,
        ...Object.fromEntries(KEYS.map(code => [code, p.counts[code] || 0])),
        total: p.total,
        recordTotal: p.recordTotal,
    }));
    const gender = DASHBOARD.gender;
    const genderRecords = DASHBOARD.genderRecords;
    const CFO_GROUPS = ['Buklod', 'Kadiwa', 'Binhi'];
    const CFO_GROUP_COLORS = { Binhi: '#16a34a', Kadiwa: '#3b82f6', Buklod: '#ef4444' };
    const CFO_STATUS_KEYS = ['MS', 'UWP', 'MK'];
    const cfoGroupRecordsByStatus = DASHBOARD.groupRecords;
    const MONTHS = DASHBOARD.monthLabels;
    const DATA_MONTHS = MONTHS.length;
    const MONTH_KEYS = DASHBOARD.monthKeys;
    const overtime = DASHBOARD.monthlyPurokCounts;
    const overtimePuroks = DASHBOARD.movementPuroks;
    const REG_FIELDS = DASHBOARD.registryReport.map(field => [field.label, field.missing, field.details]);
    const add = a => a.reduce((x, y) => x + y, 0);
    const registryPuroks = DASHBOARD.registryPuroks;
    const regTotals = registryPuroks.map(p => p.recordTotal), regAll = DASHBOARD.registryRecordCount;
    const regOverall = regAll * REG_FIELDS.length;
    const regPct = DASHBOARD.registryCompletion;
    const regGauge = document.getElementById('open-registry').closest('.gauge');
    regGauge.dataset.value = regPct.toFixed(2);
    regGauge.querySelector('svg').setAttribute('aria-label', `Registry Updating ${regPct.toFixed(2)}%`);
    document.querySelectorAll('.gauge')[0].querySelector('svg').setAttribute('aria-label', `R2-10 Updating ${DASHBOARD.r210Updating.toFixed(2)}%`);
    document.querySelectorAll('.gauge')[2].querySelector('svg').setAttribute('aria-label', `LSP / Locale: ${DASHBOARD.activeCount} active records of ${DASHBOARD.localeSNumberTarget} S Number (${DASHBOARD.localeActivePercentage.toFixed(2)}%)`);
    document.querySelectorAll('.gauge')[2].dataset.value = DASHBOARD.localeActivePercentage.toFixed(2);
    document.querySelectorAll('.gauge')[0].dataset.value = DASHBOARD.r210Updating.toFixed(2);

    /* ---------- Helpers ---------- */
    const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };
    const swatch = c => { const i = el('i'); i.style.background = c; return i; };
    function legend(id, items) {
        $(id).replaceChildren(...items.map(([name, color]) => { const li = el('li'); li.append(swatch(color), name); return li; }));
    }
    function valueLegend(id, items, total) {
        $(id).replaceChildren(...items.map(([name, color, v]) => {
            const li = el('li'), l = el('span'); l.append(swatch(color), name);
            li.append(l, el('strong', '', total ? `${v} · ${Math.round(v / total * 100)}%` : v));
            return li;
        }));
    }
    function makeChart(id, config) {
        const canvas = $(id);
        try {
            if (!hasChart) throw new Error('Chart.js missing');
            if (!canvas) throw new Error(`Chart canvas "${id}" is missing`);
            Chart.getChart(canvas)?.destroy();
            return new Chart(canvas, config);
        } catch (err) {
            console.error(`Chart "${id}" failed:`, err);
            if (canvas?.parentElement) {
                const message = el('p', 'chart-error', "The chart couldn't load. Reload the page to try again.");
                message.setAttribute('role', 'status');
                canvas.hidden = true;
                canvas.setAttribute('aria-hidden', 'true');
                canvas.parentElement.querySelector('.chart-center')?.setAttribute('hidden', '');
                canvas.parentElement.append(message);
            }
            return null;
        }
    }
    function animateNumber(node, to, decimals, suffix) {
        if (reduceMotion) { node.textContent = to.toFixed(decimals) + suffix; return; }
        const t0 = performance.now();
        const tick = now => {
            const t = Math.min((now - t0) / 1000, 1);
            node.textContent = (to * (1 - Math.pow(1 - t, 3))).toFixed(decimals) + suffix;
            if (t < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    }
    function segments(container, parts, base) {
        const nodes = parts.map(([label, n, color]) => { const s = el('span'); s.style.background = color; s.title = `${label}: ${n}`; return [s, base ? n / base * 100 : 0]; });
        container.replaceChildren(...nodes.map(n => n[0]));
        requestAnimationFrame(() => requestAnimationFrame(() => nodes.forEach(([s, w]) => { s.style.width = w + '%'; })));
    }
    const statusItems = KEYS.map(k => [NAMES[k], colorFor(k)]);

    /* ---------- Hero ---------- */
    animateNumber($('hero-total'), totalActive, 0, '');
    animateNumber($('hero-inactive'), inactive, 0, '');
    segments($('split-bar'), KEYS.map(k => [NAMES[k], active[k], colorFor(k)]), totalActive);
    $('split-bar').setAttribute('aria-label', 'Active records: ' + KEYS.map(k => `${NAMES[k]} ${active[k]}`).join(', '));
    $('split-legend').replaceChildren(...KEYS.map(k => {
        const li = el('li'); li.append(swatch(colorFor(k)), NAMES[k] + ' ', el('strong', '', active[k])); return li;
    }));

    /* ---------- By purok ---------- */
    legend('status-legend', statusItems);
    if (!puroks.length) {
        $('purok-chart-box').replaceChildren(el('p', 'chart-empty', 'No Puroks are configured yet. Add Puroks in Security Settings to see active records by Purok.'));
        $('status-legend').replaceChildren();
        $('purok-chart-detail').hidden = true;
    }
    function showPurokDetail(purok) {
        $('purok-chart-detail').textContent =
            `${purok.name}: ${KEYS.map(status => `${NAMES[status]} ${purok[status]}`).join(' · ')} · Total ${purok.total}`;
    }
    const largestPurok = Math.max(1, ...puroks.map(purok => purok.total));
    if (puroks.length) {
        $('purok-chart-box').replaceChildren(...puroks.map(purok => {
            const row = el('button', 'purok-row');
            row.type = 'button';
            row.setAttribute('aria-label',
                `${purok.name}: ${KEYS.map(status => `${NAMES[status]} ${purok[status]}`).join(', ')}; total ${purok.total}`);
            row.title = `${purok.name}: ${KEYS.map(status => `${NAMES[status]} ${purok[status]}`).join(' · ')} · Total ${purok.total}`;
            const name = el('span', 'purok-row-name', purok.name);
            const track = el('span', 'purok-row-track');
            track.setAttribute('aria-hidden', 'true');
            KEYS.forEach(status => {
                const count = purok[status];
                if (!count) return;
                const segment = el('span', 'purok-row-segment', count / largestPurok >= .1 ? String(count) : '');
                segment.style.width = `${count / largestPurok * 100}%`;
                segment.style.background = colorFor(status);
                segment.style.color = status === 'MK' ? INK : '#fff';
                segment.title = `${status}: ${count}`;
                track.append(segment);
            });
            row.append(name, track, el('span', 'purok-row-total', String(purok.total)));
            row.addEventListener('mouseenter', () => showPurokDetail(purok));
            row.addEventListener('focus', () => showPurokDetail(purok));
            row.addEventListener('click', () => showPurokDetail(purok));
            return row;
        }));
    }

    /* ---------- Gauges ---------- */
    document.querySelectorAll('.gauge').forEach((g, i) => {
        const v = Number(g.dataset.value), c = 2 * Math.PI * 50;
        setTimeout(() => {
            g.querySelector('.g-bar').style.strokeDashoffset = String(c * (1 - Math.min(Math.max(v, 0), 100) / 100));
            animateNumber(g.querySelector('.g-text'), v, 2, '%');
        }, reduceMotion ? 0 : 150 + i * 120);
    });

    /* ---------- Gender ---------- */
    ['female', 'male'].forEach(key => {
        const g = gender[key], n = add(KEYS.map(k => g[k] || 0)), pct = totalActive ? n / totalActive * 100 : 0;
        segments($(`bar-${key}`), KEYS.map(k => [NAMES[k], g[k] || 0, colorFor(k)]), totalActive);
        $(`bar-${key}`).setAttribute('aria-label', `${key}: ${pct.toFixed(2)}% of active records (${KEYS.map(k => `${k} ${g[k] || 0}`).join(', ')})`);
        animateNumber($(`pct-${key}`), pct, 2, '%');
    });
    legend('gender-legend', statusItems);
    const genderRecordsModal = $('gender-records-modal');
    function showGenderRecords(genderKey) {
        const label = genderKey === 'female' ? 'Female' : 'Male';
        const records = [...(genderRecords[genderKey] || [])]
            .sort((left, right) => left.name.localeCompare(right.name));
        $('gender-records-title').textContent = `${label} active records`;
        $('gender-records-sub').textContent = `${records.length} record${records.length === 1 ? '' : 's'}`;
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
    document.querySelectorAll('.gender-drill').forEach(button => {
        button.addEventListener('click', () => showGenderRecords(button.dataset.gender));
    });
    genderRecordsModal.addEventListener('click', event => {
        if (event.target === genderRecordsModal || event.target.closest('[data-close]')) genderRecordsModal.close();
    });

    /* ---------- Doughnuts ---------- */
    const doughnutCenter = {
        id: 'doughnutCenter',
        afterDraw(chart) {
            if (['cfo-group-chart', 'cfo-status-chart'].includes(chart.canvas.id)) return;
            if (!chart.chartArea) return;
            const values = chart.data.datasets[0]?.data || [];
            const total = chart.$centerTotal ?? values.reduce((sum, value) => sum + (Number(value) || 0), 0);
            const { left, right, top, bottom } = chart.chartArea;
            const x = (left + right) / 2, y = (top + bottom) / 2;
            const { ctx } = chart;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = INK;
            ctx.font = `750 20px ${Chart.defaults.font.family}`;
            ctx.fillText(String(total), x, y - 5);
            ctx.fillStyle = MUTED;
            ctx.font = `700 9px ${Chart.defaults.font.family}`;
            ctx.fillText('RECORDS', x, y + 13);
            ctx.restore();
        },
    };
    const doughnut = (id, labels, data, colors, onClick = undefined) => makeChart(id, {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: colors, borderColor: '#fff', borderWidth: 3, borderRadius: 4, hoverOffset: 6, spacing: 2 }] },
        options: {
            cutout: '68%',
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            onClick,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const value = Number(context.raw) || 0;
                            const total = context.dataset.data.reduce((sum, item) => sum + (Number(item) || 0), 0);
                            const percent = total ? ` (${(value / total * 100).toFixed(1)}%)` : '';
                            return `${context.label}: ${value}${percent}`;
                        },
                    },
                },
            },
        },
        plugins: [doughnutCenter],
    });

    const cfoGroupModal = $('cfo-group-modal');
    function showCfoRecords(records, title, description) {
        $('cfo-group-title').textContent = title;
        $('cfo-group-sub').textContent = `${records.length} ${description} record${records.length === 1 ? '' : 's'}`;
        const body = $('cfo-group-body');
        if (!records.length) {
            body.replaceChildren(el('div', 'empty', `No ${description} records found.`));
            cfoGroupModal.showModal();
            return;
        }
        const table = el('table', 'tbl'), head = el('tr');
        ['Name', 'Kalagayan'].forEach(label => head.append(el('th', '', label)));
        const thead = el('thead'); thead.append(head); table.append(thead);
        const tbody = el('tbody');
        records.sort((left, right) => left.name.localeCompare(right.name)).forEach(record => {
            const row = el('tr');
            row.append(el('td', 'name', record.name), el('td', '', record.status));
            tbody.append(row);
        });
        table.append(tbody);
        const wrap = el('div', 'tbl-wrap'); wrap.append(table);
        body.replaceChildren(wrap);
        cfoGroupModal.showModal();
    }

    const selectedStatusesForGroupChart = () => document.querySelector('#cfo-status-seg button[aria-checked="true"]')?.dataset.k || 'ALL';
    const selectedGroupForStatusChart = () => document.querySelector('#cfo-group-seg button[aria-checked="true"]')?.dataset.k || 'ALL';
    const groupChartData = kind => CFO_GROUPS.map(group => {
        const statuses = kind === 'ALL' ? CFO_STATUS_KEYS : [kind];
        return statuses.reduce((total, status) => total + (DASHBOARD.groupCounts[status]?.[group] || 0), 0);
    });
    const statusChartData = kind => CFO_STATUS_KEYS.map(status => {
        const groups = kind === 'ALL' ? CFO_GROUPS : [kind];
        return groups.reduce((total, group) => total + (DASHBOARD.groupCounts[status]?.[group] || 0), 0);
    });

    const cfoGroupChart = doughnut('cfo-group-chart', CFO_GROUPS, groupChartData('ALL'), CFO_GROUPS.map(group => CFO_GROUP_COLORS[group]), (event, elements, chart) => {
        const [slice] = chart.getElementsAtEventForMode(event, 'nearest', { intersect: true }, true);
        if (!slice) return;
        const group = CFO_GROUPS[slice.index];
        const kind = selectedStatusesForGroupChart();
        const statuses = kind === 'ALL' ? CFO_STATUS_KEYS : [kind];
        const records = statuses.flatMap(status => cfoGroupRecordsByStatus[status]?.[group] || []);
        const statusLabel = kind === 'ALL' ? 'all MS, UWP, and MK' : kind;
        showCfoRecords(records, `${group} records`, `${statusLabel} ${group}`);
    });
    const cfoStatusChart = doughnut('cfo-status-chart', CFO_STATUS_KEYS, statusChartData('ALL'), CFO_STATUS_KEYS.map(status => colorFor(status)), (event, elements, chart) => {
        const [slice] = chart.getElementsAtEventForMode(event, 'nearest', { intersect: true }, true);
        if (!slice) return;
        const status = CFO_STATUS_KEYS[slice.index];
        const kind = selectedGroupForStatusChart();
        const groups = kind === 'ALL' ? CFO_GROUPS : [kind];
        const records = groups.flatMap(group => cfoGroupRecordsByStatus[status]?.[group] || []);
        const groupLabel = kind === 'ALL' ? 'all CFO groups' : kind;
        showCfoRecords(records, `${status} records`, `${status} ${groupLabel}`);
    });

    function updateCfoGroupChart(kind) {
        const data = groupChartData(kind);
        const total = add(data);
        $('cfo-group-total').textContent = String(total);
        cfoGroupChart?.stop();
        if (cfoGroupChart) {
            cfoGroupChart.data.datasets[0].data = data;
            cfoGroupChart.update('none');
            cfoGroupChart.canvas.setAttribute('aria-label', `${kind === 'ALL' ? 'All MS, UWP, and MK' : kind} breakdown per CFO group: `
                + CFO_GROUPS.map((group, index) => `${group} ${data[index]}`).join(', '));
        }
        valueLegend('cfo-group-legend', CFO_GROUPS.map((group, index) => [
            group, CFO_GROUP_COLORS[group], data[index],
        ]), total);
        document.querySelectorAll('#cfo-status-seg button').forEach(button => {
            button.setAttribute('aria-checked', String(button.dataset.k === kind));
        });
    }

    function updateCfoStatusChart(kind) {
        const data = statusChartData(kind);
        const total = add(data);
        $('cfo-status-total').textContent = String(total);
        cfoStatusChart?.stop();
        if (cfoStatusChart) {
            cfoStatusChart.data.datasets[0].data = data;
            cfoStatusChart.update('none');
            cfoStatusChart.canvas.setAttribute('aria-label', `${kind === 'ALL' ? 'All CFO groups' : kind} breakdown per Kalagayan: `
                + CFO_STATUS_KEYS.map((status, index) => `${status} ${data[index]}`).join(', '));
        }
        valueLegend('cfo-status-legend', CFO_STATUS_KEYS.map((status, index) => [
            status, colorFor(status), data[index],
        ]), total);
        document.querySelectorAll('#cfo-group-seg button').forEach(button => {
            button.setAttribute('aria-checked', String(button.dataset.k === kind));
        });
    }

    $('cfo-status-seg').replaceChildren(...['ALL', ...CFO_STATUS_KEYS].map(kind => {
        const button = el('button', '', kind);
        button.type = 'button';
        button.dataset.k = kind;
        button.setAttribute('role', 'radio');
        button.addEventListener('click', () => updateCfoGroupChart(kind));
        return button;
    }));
    $('cfo-group-seg').replaceChildren(...['ALL', ...CFO_GROUPS].map(group => {
        const button = el('button', '', group);
        button.type = 'button';
        button.dataset.k = group;
        button.setAttribute('role', 'radio');
        button.addEventListener('click', () => updateCfoStatusChart(group));
        return button;
    }));
    updateCfoGroupChart('ALL');
    updateCfoStatusChart('ALL');

    /* ---------- Over time ---------- */
    const historyStatuses = ['MS', 'UWP', 'MK'];
    const lastMonthIndex = Math.max(DATA_MONTHS - 1, 0);
    const state = { s: 0, e: lastMonthIndex, puroks: new Set() };
    const allIds = overtimePuroks.map(String);
    let idxs = [];
    let visiblePuroks = allIds;
    const dataset = status => ({
        label: status, data: [], borderColor: colorFor(status), backgroundColor: colorFor(status), borderWidth: 2.5, tension: .25,
        pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: '#fff', pointBorderWidth: 2,
    });
    const otChart = makeChart('ot-chart', {
        type: 'line',
        data: { labels: [], datasets: historyStatuses.map(dataset) },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            layout: { padding: { top: 14, bottom: 14 } },
            onClick(event, elements, chart) {
                const point = chart.getElementsAtEventForMode(
                    event,
                    'nearest',
                    { intersect: false, axis: 'x' },
                    true
                )[0] || elements[0];
                const monthIndex = point ? idxs[point.index] : undefined;
                if (monthIndex !== undefined) showMovementCodes(MONTH_KEYS[monthIndex], visiblePuroks);
            },
            scales: {
                x: { min: 0, max: Math.max(0, DATA_MONTHS - 1), grid: { display: false }, border: { color: LINE } },
                y: { min: 0, grid: { color: LINE }, border: { display: false }, title: { display: true, text: 'Records' } },
            },
            plugins: { legend: { display: false }, tooltip: { padding: 10, boxPadding: 4 } },
        },
    });

    function renderOvertime() {
        const ids = state.puroks.size ? [...state.puroks] : allIds;
        const isAllPuroks = ids.length === allIds.length;
        visiblePuroks = ids;
        idxs = MONTH_KEYS
            .map((_, monthIndex) => monthIndex)
            .filter(monthIndex => monthIndex >= state.s && monthIndex <= state.e);
        $('ot-empty').hidden = idxs.length > 0;
        const selectedPuroks = ids.length
            ? ids.map(id => `Purok ${id}`).join(', ')
            : 'No Puroks';
        const selectedMonths = idxs.map(index => MONTHS[index]);
        const period = selectedMonths.length === 0
            ? 'no periods'
            : state.s === 0 && state.e === lastMonthIndex
                ? `All periods (${selectedMonths[0]}–${selectedMonths[selectedMonths.length - 1]})`
            : selectedMonths.length === 1
                ? selectedMonths[0]
                : `${selectedMonths[0]}–${selectedMonths[selectedMonths.length - 1]}`;
        $('ot-current').textContent = `Now showing trend graph for ${isAllPuroks ? 'All Puroks' : selectedPuroks} · ${period}`;
        if (!otChart) return;
        const labels = idxs.map(m => MONTHS[m]);
        const statusTotals = Object.fromEntries(historyStatuses.map(status => [
            status,
            idxs.map(monthIndex => ids.reduce(
                (sum, id) => sum + (overtime[monthIndex]?.[id]?.[status] || 0),
                0
            )),
        ]));
        otChart.stop();
        otChart.data.labels = labels;
        historyStatuses.forEach((status, index) => {
            otChart.data.datasets[index].data = statusTotals[status];
        });
        legend('ot-legend', historyStatuses.map(status => [status, colorFor(status)]));
        otChart.options.scales.x.min = 0;
        otChart.options.scales.x.max = Math.max(0, labels.length - 1);
        otChart.update('none');
        otChart.canvas.setAttribute('aria-label',
            `Records over time for ${ids.length === allIds.length ? 'all puroks' : 'Purok '+ids.join(', ')}: `
            +idxs.map((monthIndex, index) => `${MONTHS[monthIndex]} ${historyStatuses.map(status => `${status} ${statusTotals[status][index]}`).join(', ')}`).join('; '));
    }

    const movementCodeModal = $('movement-code-modal');
    function showMovementCodes(monthKey, selectedPuroks) {
        const monthIndex = MONTH_KEYS.indexOf(monthKey);
        const details = DASHBOARD.movementDetails
            .filter(item => item.month === monthKey && selectedPuroks.includes(String(item.purok)))
            .sort((left, right) => left.purok - right.purok
                || left.status.localeCompare(right.status)
                || left.code.localeCompare(right.code));
        const monthLabel = MONTHS[monthIndex];
        $('movement-code-title').textContent = `${monthLabel} movement codes`;
        const selectedPurokLabel = selectedPuroks.length === allIds.length
            ? 'All Puroks'
            : selectedPuroks.map(id => `Purok ${id}`).join(', ');
        $('movement-code-sub').textContent = `${selectedPurokLabel} · ${details.length} recorded movement entr${details.length === 1 ? 'y' : 'ies'}`;
        const body = $('movement-code-body');
        const summary = el('div', 'movement-count-grid');
        selectedPuroks.forEach(purok => {
            const counts = overtime[monthIndex]?.[purok] || {};
            const card = el('section', 'movement-purok-card');
            card.append(el('h3', '', `Purok ${purok}`));
            const statusCounts = el('div', 'movement-purok-counts');
            [['MS', counts.MS], ['UWP', counts.UWP], ['MK', counts.MK]].forEach(([status, count]) => {
                const item = el('div', 'movement-purok-count');
                item.append(el('span', '', status), el('strong', '', String(count || 0)));
                statusCounts.append(item);
            });
            card.append(statusCounts);
            summary.append(card);
        });
        if (!details.length) {
            body.replaceChildren(
                el('div', 'empty', `No movement codes were entered for ${monthLabel} in ${selectedPurokLabel}.`),
                summary
            );
        } else {
            body.replaceChildren(summary);
            const table = el('table', 'tbl'), head = el('tr');
            ['Purok', 'Kalagayan', 'Code', 'Count'].forEach(column => head.append(el('th', '', column)));
            const thead = el('thead'); thead.append(head); table.append(thead);
            const tbody = el('tbody');
            details.forEach(item => {
                const row = el('tr');
                row.append(el('td', '', `Purok ${item.purok}`), el('td', '', item.status), el('td', '', item.code), el('td', '', String(item.count)));
                tbody.append(row);
            });
            table.append(tbody);
            const wrap = el('div', 'tbl-wrap'); wrap.append(table);
            body.append(wrap);
        }
        movementCodeModal.showModal();
    }
    movementCodeModal.addEventListener('click', event => {
        if (event.target === movementCodeModal || event.target.closest('[data-close]')) movementCodeModal.close();
    });

    const monthsEl = $('tl-months');
    monthsEl.replaceChildren(...MONTHS.map((name, m) => {
        const b = el('button', 'chip', name); b.type = 'button';
        b.setAttribute('aria-label', name);
        b.addEventListener('click', () => {
            const all = state.s === 0 && state.e === lastMonthIndex, single = state.s === state.e;
            if (all) state.s = state.e = m;
            else if (single && state.s === m) { state.s = 0; state.e = lastMonthIndex; }
            else if (single) { const a = state.s; state.s = Math.min(a, m); state.e = Math.max(a, m); }
            else state.s = state.e = m;
            renderMonths(); renderOvertime();
        });
        return b;
    }));
    function renderMonths() {
        const all = state.s === 0 && state.e === lastMonthIndex;
        monthsEl.querySelectorAll('.chip').forEach((b, m) => b.setAttribute('aria-pressed', String(!all && m >= state.s && m <= state.e)));
        $('month-hint').textContent = all ? 'Select a month, then another to pick a range.'
            : state.s === state.e ? `Showing ${MONTHS[state.s]}. Select another month to make a range.`
            : `Showing ${MONTHS[state.s]} to ${MONTHS[state.e]}.`;
    }
    $('month-clear').addEventListener('click', () => { state.s = 0; state.e = lastMonthIndex; renderMonths(); renderOvertime(); });

    function renderPuroks() {
        $('purok-list').replaceChildren(...overtimePuroks.map(purokNumber => {
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
    $('purok-clear').addEventListener('click', () => { state.puroks = new Set(); renderPuroks(); renderOvertime(); });

    /* ---------- New baptisees (baptized less than 1 year ago) ---------- */
    // Pass $newBaptisees from the controller: [{name, purok, grupo, status, evangelist, baptized_at: 'YYYY-MM-DD'}]
    const serverBaptisees = @json($newBaptisees);
    function since(from) {
        const a = new Date(from + 'T00:00:00'), now = new Date(), b = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        let months = (b.getFullYear() - a.getFullYear()) * 12 + b.getMonth() - a.getMonth();
        let anchor = new Date(a.getFullYear(), a.getMonth() + months, a.getDate());
        if (anchor > b) { months--; anchor = new Date(a.getFullYear(), a.getMonth() + months, a.getDate()); }
        return { months, days: Math.round((b - anchor) / 86400000) };
    }
    const plural = (n, w) => `${n} ${w}${n === 1 ? '' : 's'}`;
    const spanText = ({ months, days }) => months && days ? `${plural(months, 'month')} and ${plural(days, 'day')}` : months ? plural(months, 'month') : plural(days, 'day');

    function renderBaptisees() {
        const list = serverBaptisees
            .map(b => ({ ...b, span: since(b.baptized_at) }))
            .sort((x, y) => y.baptized_at.localeCompare(x.baptized_at));
        $('bap-count').textContent = list.length || '';
        $('bap-sub').textContent = list.length ? `Baptized within the last year: ${list.length} in the system.` : 'Baptized within the last year.';
        const body = $('bap-body');
        if (!list.length) {
            const box = el('div', 'empty');
            box.append(el('strong', '', 'No newly baptized records were found in the past year.'), el('span', '', 'Filtered by kalagayan: MS, UWP, and MK.'));
            body.replaceChildren(box);
            return;
        }
        const table = el('table', 'tbl');
        const head = el('tr');
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

    /* ---------- Registry updating monitoring ---------- */
    const registryDetailsDialog = $('registry-details-modal');
    const registryEditDialog = $('registry-edit-modal');
    const registryEditForm = $('registry-edit-form');
    const registryEditSave = $('registry-edit-save');
    const RECORD_UPDATE_URL = @json(route('records.update', ['record' => '__RECORD_ID__']));
    const CSRF_TOKEN = @json(csrf_token());
    const CAN_EDIT_REGISTRY = $('dashboardApp').dataset.canEditRegistry === 'true';
    let registryEditTarget = null;
    const registryFieldConfig = {
        registry: { label: 'Registry number', key: 'registry_number', type: 'text', hint: 'Enter exactly 13 letters or numbers, or RFID.' },
        gender: { label: 'Gender', key: 'gender', type: 'select', options: ['Male', 'Female'] },
        purok: { label: 'Purok', key: 'purok', type: 'locked', hint: 'Purok assignments are not changed from Registry Updating.' },
        grupo: { label: 'Grupo', key: 'grupo', type: 'number', hint: 'Grupo can only be changed within this member’s current Purok.' },
        kapisanan: { label: 'Kapisanan', key: 'kapisanan', type: 'select', options: ['Binhi', 'Kadiwa', 'Buklod'] },
        date_of_baptism: { label: 'Date of baptism', key: 'date_of_baptism', type: 'date' },
        evangelist: { label: 'Evangelist', key: 'evangelist', type: 'text' },
        date_of_birth: { label: 'Birthday', key: 'date_of_birth', type: 'date' },
    };

    function openRegistryEdit(entry) {
        const config = registryFieldConfig[entry.field];
        if (!CAN_EDIT_REGISTRY || !config || config.type === 'locked' || (entry.field === 'grupo' && !entry.can_edit_grupo)) return;
        registryEditTarget = { entry, config };
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

    registryEditForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!registryEditTarget) return;
        const { entry, config } = registryEditTarget;
        const input = $('registry-edit-value');
        const value = input.value.trim();
        const error = $('registry-edit-error');
        if (!value || !input.reportValidity()) {
            error.textContent = 'Enter a valid value before saving.';
            error.hidden = false;
            return;
        }

        registryEditSave.disabled = true;
        registryEditSave.textContent = 'Saving…';
        error.hidden = true;
        try {
            const response = await fetch(RECORD_UPDATE_URL.replace('__RECORD_ID__', encodeURIComponent(entry.id)), {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ [config.key]: entry.field === 'registry' ? value.toUpperCase() : value }),
            });
            const result = await response.json();
            if (!response.ok) {
                const validationMessage = result.errors ? Object.values(result.errors).flat()[0] : null;
                throw new Error(validationMessage || result.message || 'Unable to save this record.');
            }
            registryEditSave.textContent = 'Saved. Refreshing…';
            window.location.reload();
        } catch (exception) {
            error.textContent = exception.message || 'Unable to save this record.';
            error.hidden = false;
            registryEditSave.disabled = false;
            registryEditSave.textContent = 'Save missing data';
        }
    });
    registryEditDialog.addEventListener('close', () => { registryEditTarget = null; });

    function showRegistryDetails(label, group, entries) {
        $('registry-details-title').textContent = `Missing ${label} - ${group}`;
        $('registry-details-sub').textContent = `${entries.length} record${entries.length === 1 ? '' : 's'}`;
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
                const config = registryFieldConfig[entry.field];
                const edit = el('button', 'reg-count', !CAN_EDIT_REGISTRY ? 'No edit access' : (config?.type === 'locked' ? 'Monthly portal' : 'Enter data'));
                edit.type = 'button';
                edit.disabled = !CAN_EDIT_REGISTRY || !config || config.type === 'locked' || (entry.field === 'grupo' && !entry.can_edit_grupo);
                edit.title = !CAN_EDIT_REGISTRY
                    ? 'Editing is disabled for this account.'
                    : (entry.field === 'grupo' && !entry.can_edit_grupo
                        ? 'Assign this member to a Purok before entering a Grupo.'
                        : (config?.type === 'locked' ? config.hint : `Enter missing ${config?.label?.toLowerCase() || 'data'}`));
                edit.addEventListener('click', () => openRegistryEdit(entry));
                action.append(edit);
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
        const table = el('table', 'tbl'), head = el('tr');
        ['Data', ...registryPuroks.map(p => p.id === 'Unassigned' ? 'Unassigned' : `Purok ${p.id}`), 'Total'].forEach(h => head.append(el('th', '', h)));
        const thead = el('thead'); thead.append(head); table.append(thead);
        const tbody = el('tbody');
        const countButton = (value, label, group, entries) => {
            const button = el('button', `reg-count${value ? '' : ' is-zero'}`, String(value));
            button.type = 'button';
            button.setAttribute('aria-label', `${value} records missing ${label} in ${group}`);
            button.addEventListener('click', () => showRegistryDetails(label, group, entries));
            return button;
        };
        REG_FIELDS.forEach(([label, miss, details]) => {
            const tr = el('tr', '', null);
            tr.append(el('td', '', label));
            miss.forEach((value, index) => {
                const cell = el('td', value ? 'gap-cell' : 'ok-cell');
                const group = registryPuroks[index].id === 'Unassigned' ? 'Unassigned' : `Purok ${registryPuroks[index].id}`;
                cell.append(countButton(value, label, group, details[index].map(entry => ({ ...entry, purok: group }))));
                tr.append(cell);
            });
            const allEntries = details.flatMap((entries, index) => {
                const group = registryPuroks[index].id === 'Unassigned' ? 'Unassigned' : `Purok ${registryPuroks[index].id}`;
                return entries.map(entry => ({ ...entry, purok: group }));
            });
            const totalCell = el('td', 'tot');
            totalCell.append(countButton(add(miss), label, 'all puroks', allEntries));
            tr.append(totalCell);
            tbody.append(tr);
        });
        const foot = el('tfoot'), fr = el('tr');
        fr.append(el('td', '', 'Encoded / active data'));
        regTotals.forEach((t, i) => fr.append(el('td', '', `${t * REG_FIELDS.length - add(REG_FIELDS.map(f => f[1][i]))} / ${t * REG_FIELDS.length}`)));
        fr.append(el('td', 'tot', `${DASHBOARD.registryEncoded} / ${regOverall}`));
        foot.append(fr);
        table.append(tbody, foot);
        const wrap = el('div', 'tbl-wrap'); wrap.append(table);

        const summary = el('div', 'reg-summary'), summaryGrid = el('div', 'reg-summary-grid');
        summary.append(el('h3', '', 'Summary'));
        REG_FIELDS.forEach(([label, miss]) => {
            const done = regAll - add(miss), pct = regAll ? done / regAll * 100 : 0;
            const item = el('div', 'sum-item'), top = el('div', 'sum-top'), meter = el('div', 'meter'), fill = el('span');
            fill.style.width = pct + '%';
            fill.style.background = pct >= 95 ? colorFor('MK') : colorFor('UWP');
            meter.append(fill);
            top.append(el('span', '', `${label}: ${done} of ${regAll}`), el('span', '', pct.toFixed(2) + '%'));
            item.append(top, meter);
            summaryGrid.append(item);
        });
        summary.append(summaryGrid);
        const layout = el('div', 'reg'); layout.append(wrap, summary);
        $('reg-body').replaceChildren(layout);
    }

    [['open-baptisee', 'baptisee-modal'], ['open-registry', 'registry-modal']].forEach(([btn, dlgId]) => {
        const dlg = $(dlgId);
        $(btn).addEventListener('click', () => dlg.showModal());
        dlg.addEventListener('click', e => { if (e.target === dlg || e.target.closest('[data-close]')) dlg.close(); });
    });
    registryDetailsDialog.addEventListener('click', e => {
        if (e.target === registryDetailsDialog || e.target.closest('[data-close]')) registryDetailsDialog.close();
    });
    registryEditDialog.addEventListener('click', e => {
        if (e.target === registryEditDialog || e.target.closest('[data-close]')) registryEditDialog.close();
    });
    cfoGroupModal.addEventListener('click', e => {
        if (e.target === cfoGroupModal || e.target.closest('[data-close]')) cfoGroupModal.close();
    });
    renderBaptisees();
    renderRegistry();

    renderMonths(); renderPuroks(); renderOvertime();
});
</script>
@endpush