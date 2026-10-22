@extends('layouts.app')

@section('title', 'Tarheta Status Checker')

@section('content')
<style>
    /* Monochrome by design: status is plain text, a mismatch is shown with a dashed outline,
       and color is kept only for save success / error feedback. */
    .tk-page {
        --card: var(--surface, #fff); --ink: var(--text, #000); --muted: var(--text-muted, #666); --line: var(--border, #eaeaea);
        --soft: color-mix(in srgb, var(--ink) 4%, var(--card));
        --hover: color-mix(in srgb, var(--ink) 5%, var(--card));
        --bad: var(--danger, #e5484d); --ok: #18a058;
        --r: 6px; --r-lg: 12px;
        max-width: 1200px; margin: 0 auto; color: var(--ink);
        font-family: 'Geist', 'Inter', -apple-system, 'Helvetica Neue', Arial, sans-serif;
        -webkit-font-smoothing: antialiased; font-variant-numeric: tabular-nums;
    }
    html[data-theme="dark"] .tk-page { --ok: #3ecf8e; --bad: var(--danger, #ff6369); }
    .tk-page *, .tk-page *::before, .tk-page *::after { box-sizing: border-box; }
    .tk-page h1, .tk-page h2, .tk-page p { margin: 0; }
    .tk-page [hidden] { display: none !important; }
    .tk-page input, .tk-page select { color-scheme: light; }
    html[data-theme="dark"] .tk-page input, html[data-theme="dark"] .tk-page select { color-scheme: dark; }
    .tk-page button:focus-visible, .tk-page a:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }
    .tk-sr { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

    /* Header */
    .tk-head { margin-bottom: 1.25rem; }
    .tk-head h1 { font-size: 1.5rem; font-weight: 600; letter-spacing: -.03em; line-height: 1.15; }
    .tk-head p { max-width: 70ch; margin-top: .35rem; color: var(--muted); font-size: .9rem; line-height: 1.55; }

    /* Summary: progress on top, the match conditions underneath */
    .tk-summary { margin-bottom: 1rem; border: 1px solid var(--line); border-radius: var(--r-lg); background: var(--card); overflow: hidden; }
    .tk-sum-top { padding: 1.15rem 1.25rem 1.25rem; }
    .tk-sum-row { display: flex; align-items: baseline; justify-content: space-between; flex-wrap: wrap; gap: .5rem 1.5rem; }
    .tk-sum-main { font-size: 1.6rem; font-weight: 600; letter-spacing: -.03em; line-height: 1.1; }
    .tk-sum-main small { margin-left: .4rem; color: var(--muted); font-size: .88rem; font-weight: 500; letter-spacing: 0; }
    .tk-sum-side { display: flex; gap: 1.25rem; color: var(--muted); font-size: .84rem; }
    .tk-sum-side strong { color: var(--ink); font-weight: 600; }
    .tk-progress { height: 4px; margin-top: 1rem; overflow: hidden; border-radius: 999px; background: var(--line); }
    .tk-progress > span { display: block; height: 100%; border-radius: inherit; background: var(--ink); transition: width .25s ease; }
    .tk-cond { padding: 1rem 1.25rem 1.1rem; border-top: 1px solid var(--line); background: var(--soft); }
    .tk-cond-title { font-size: .86rem; font-weight: 600; }
    .tk-cond-list { display: grid; gap: .6rem; margin-top: .75rem; }
    .tk-check { display: flex; align-items: center; gap: .6rem; font-size: .86rem; cursor: pointer; }
    .tk-check input { flex: none; display: grid; place-content: center; width: 16px; height: 16px; margin: 0; border: 1px solid var(--muted); border-radius: 4px; background: var(--card); -webkit-appearance: none; appearance: none; cursor: inherit; }
    .tk-check input:checked { border-color: var(--ink); background: var(--ink); }
    .tk-check input:checked::after { content: ""; width: 8px; height: 4px; margin-top: -1px; border-left: 2px solid var(--card); border-bottom: 2px solid var(--card); transform: rotate(-45deg); }
    .tk-check input:disabled { opacity: .55; }
    .tk-check input:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }
    .tk-check.is-locked { cursor: default; }
    .tk-tag { color: var(--muted); font-size: .76rem; }

    /* Search + labelled view controls */
    .tk-toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: .75rem 1.5rem; margin-bottom: 1rem; }
    .tk-search { position: relative; flex: 1 1 260px; min-width: 0; }
    .tk-search svg { position: absolute; left: .8rem; top: 50%; width: 16px; height: 16px; color: var(--muted); transform: translateY(-50%); pointer-events: none; }
    .tk-search input { width: 100%; height: 36px; padding: 0 .9rem 0 2.4rem; border: 1px solid var(--line); border-radius: var(--r); background: var(--card); color: var(--ink); font: inherit; font-size: .86rem; transition: border-color .12s ease, box-shadow .12s ease; }
    .tk-search input::placeholder { color: var(--muted); }
    .tk-search input:hover { border-color: var(--muted); }
    .tk-search input:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 1px var(--ink); }
    .tk-group { display: flex; align-items: center; gap: .55rem; }
    .tk-group-label { color: var(--muted); font-size: .8rem; }
    .tk-seg { display: inline-flex; padding: 2px; border: 1px solid var(--line); border-radius: var(--r); background: var(--card); }
    .tk-seg label { position: relative; cursor: pointer; }
    .tk-seg input { position: absolute; opacity: 0; width: 0; height: 0; }
    .tk-seg span { display: block; padding: 0 .8rem; border-radius: 4px; color: var(--muted); font-size: .82rem; font-weight: 500; line-height: 30px; white-space: nowrap; transition: background .12s ease, color .12s ease; }
    .tk-seg label:hover span { color: var(--ink); }
    .tk-seg input:checked + span { background: var(--ink); color: var(--card); }
    .tk-seg input:focus-visible + span { outline: 2px solid var(--ink); outline-offset: 1px; }

    /* Record list */
    .tk-card { border: 1px solid var(--line); border-radius: var(--r-lg); background: var(--card); overflow: hidden; }
    .tk-scroll { overflow-x: auto; }
    .tk-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
    .tk-table th { padding: .7rem 1rem; border-bottom: 1px solid var(--line); color: var(--muted); font-size: .76rem; font-weight: 500; text-align: left; white-space: nowrap; }
    .tk-table td { padding: .85rem 1rem; border-bottom: 1px solid var(--line); vertical-align: middle; }
    .tk-table tbody tr:last-child td { border-bottom: 0; }
    .tk-table tbody tr:hover td { background: var(--hover); }
    .tk-table tr[hidden] { display: none !important; }
    .tk-name { min-width: 200px; font-weight: 500; overflow-wrap: anywhere; }
    .tk-table .tk-status { width: 1%; white-space: nowrap; }
    .tk-table .tk-field { min-width: 150px; }
    .tk-table .tk-num { min-width: 200px; }
    .tk-table .tk-result { width: 1%; white-space: nowrap; }

    .tk-table select, .tk-table input { width: 100%; height: 36px; padding: 0 .7rem; border: 1px solid var(--line); border-radius: var(--r); background-color: var(--card); color: var(--ink); font: inherit; font-size: .85rem; -webkit-appearance: none; appearance: none; transition: border-color .12s ease, box-shadow .12s ease; }
    .tk-table select { padding-right: 1.9rem; text-overflow: ellipsis; background-repeat: no-repeat; background-position: right .65rem center; background-size: 10px 6px; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6' fill='none' stroke='%23888' stroke-width='1.5'%3E%3Cpath d='M1 1l4 4 4-4'/%3E%3C/svg%3E"); }
    .tk-table select:hover:not(:disabled), .tk-table input:hover:not(:disabled) { border-color: var(--muted); }
    .tk-table select:focus, .tk-table input:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 1px var(--ink); }
    .tk-table input::placeholder { color: var(--muted); }
    .tk-table select:disabled, .tk-table input:disabled { opacity: .55; cursor: wait; }
    /* Mismatch marker: a dashed outline, same ink color, no hue */
    .tk-table .is-mismatch { border: 1px dashed var(--ink); }

    /* Record status: plain text in a neutral pill */
    .tk-badge { display: inline-flex; align-items: center; height: 26px; padding: 0 .7rem; border: 1px solid var(--line); border-radius: 999px; background: var(--soft); color: var(--ink); font-size: .78rem; font-weight: 500; white-space: nowrap; }
    .tk-badge.is-none { border-style: dashed; background: transparent; color: var(--muted); }

    /* Result: one line. Reason lives in the tooltip. */
    .tk-res { display: inline-flex; align-items: center; gap: .5rem; font-size: .84rem; font-weight: 500; cursor: default; }
    .tk-res::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--ink); }
    .tk-res.is-review { color: var(--muted); }
    .tk-res.is-review::before { background: transparent; border: 1.5px dashed var(--muted); }

    .tk-msg { margin-top: .35rem; font-size: .77rem; line-height: 1.4; }
    .tk-msg:empty { display: none; }
    .tk-msg.is-saving { color: var(--muted); }
    .tk-msg.is-ok { color: var(--ok); }
    .tk-msg.is-error { color: var(--bad); }
    .tk-spin { display: inline-block; width: 10px; height: 10px; margin-right: .35rem; border: 2px solid var(--line); border-top-color: var(--ink); border-radius: 50%; vertical-align: -1px; animation: tk-spin .7s linear infinite; }
    @keyframes tk-spin { to { transform: rotate(360deg); } }
    .tk-row.is-error td:first-child { box-shadow: inset 2px 0 0 var(--bad); }

    /* Empty states + pager */
    .tk-empty { display: grid; gap: .3rem; padding: 2.5rem 1.2rem; text-align: center; color: var(--muted); font-size: .86rem; line-height: 1.6; }
    .tk-empty strong { color: var(--ink); font-size: .95rem; font-weight: 600; }
    .tk-pager { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .7rem 1rem; border-top: 1px solid var(--line); color: var(--muted); font-size: .8rem; }
    .tk-pager div { display: flex; gap: .4rem; }
    .tk-btn { height: 30px; padding: 0 .85rem; border: 1px solid var(--line); border-radius: var(--r); background: transparent; color: var(--ink); font: inherit; font-size: .78rem; font-weight: 500; cursor: pointer; transition: background .12s ease, border-color .12s ease; }
    .tk-btn:hover:not(:disabled) { border-color: var(--muted); background: var(--hover); }
    .tk-btn:disabled { opacity: .45; cursor: not-allowed; }

    @media (prefers-reduced-motion: reduce) { .tk-page *, .tk-page *::before { transition: none !important; animation: none !important; } }
    @media (max-width: 720px) {
        .tk-sum-main { font-size: 1.35rem; }
        .tk-table thead { display: none; }
        .tk-table, .tk-table tbody, .tk-table tr, .tk-table td { display: block; width: 100%; }
        .tk-table tr { padding: .6rem 1rem; border-bottom: 1px solid var(--line); }
        .tk-table tbody tr:last-child { border-bottom: 0; }
        .tk-table td { display: flex; align-items: center; justify-content: space-between; gap: .75rem; width: 100% !important; padding: .35rem 0; border: 0; }
        .tk-table tbody tr:hover td { background: transparent; }
        .tk-table td::before { content: attr(data-label); flex: none; width: 6.5rem; color: var(--muted); font-size: .74rem; }
        .tk-table td > * { flex: 1; min-width: 0; }
        .tk-table td.tk-name { display: block; min-width: 0; padding-bottom: .5rem; font-size: .95rem; }
        .tk-table td.tk-name::before { display: none; }
        .tk-table td[data-label="Status"] > .tk-badge, .tk-table td[data-label="Result"] > .tk-res { flex: 0 1 auto; margin-left: auto; }
    }
</style>

<main class="tk-page">
    <header class="tk-head">
        <h1>Tarheta Status Checker</h1>
        <p>Compare each record’s status with its Tarheta and filing status. Add a filing number when one is issued. A record is complete when all monitored statuses match.</p>
    </header>

    <section class="tk-summary" aria-label="Summary">
        <div class="tk-sum-top">
            <div class="tk-sum-row">
                <div class="tk-sum-main"><span id="tarhetaMatched">{{ $matched }}</span><small>of <span id="tarhetaTotal">{{ $total }}</span> matched</small></div>
                <div class="tk-sum-side">
                    <span><strong id="tarhetaCount">{{ $total - $matched }}</strong> not matched</span>
                    <span><strong id="tarhetaPercent">{{ $percentage }}%</strong> complete</span>
                </div>
            </div>
            <div class="tk-progress" role="progressbar" aria-label="Records matching Tarheta status" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $matched }}">
                <span id="tarhetaProgress" style="width:{{ $percentage }}%"></span>
            </div>
        </div>
        <div class="tk-cond" role="group" aria-labelledby="tkCondTitle">
            <div class="tk-cond-title" id="tkCondTitle">A record is matched when</div>
            <div class="tk-cond-list">
                <label class="tk-check is-locked"><input type="checkbox" checked disabled><span>Tarheta status is the same as the record status</span><span class="tk-tag">Required</span></label>
                <label class="tk-check"><input id="monitorFiling" type="checkbox" checked><span>Filing status is the same as the record status</span><span class="tk-tag">Optional</span></label>
            </div>
        </div>
    </section>

    <div class="tk-toolbar">
        <label class="tk-search">
            <span class="tk-sr">Search Tarheta records</span>
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <input id="tarhetaSearch" type="search" placeholder="Search by member name or status" autocomplete="off">
        </label>
        <div class="tk-group" role="radiogroup" aria-label="Show records">
            <span class="tk-group-label">Show</span>
            <div class="tk-seg">
                <label><input type="radio" name="view" value="unmatched" checked><span>Not matched</span></label>
                <label><input type="radio" name="view" value="matched"><span>Matched</span></label>
                <label><input type="radio" name="view" value="all"><span>All</span></label>
            </div>
        </div>
        <div class="tk-group" role="radiogroup" aria-label="Filing number filter">
            <span class="tk-group-label">Filing number</span>
            <div class="tk-seg">
                <label><input type="radio" name="fnum" value="any" checked><span>Any</span></label>
                <label><input type="radio" name="fnum" value="missing"><span>Missing</span></label>
            </div>
        </div>
    </div>

    <section class="tk-card" aria-label="Records">
        @if (count($records) === 0)
            <div class="tk-empty">
                <strong>No records to evaluate</strong>
                <span>Records appear here once members have a status to compare.</span>
            </div>
        @else
            <div class="tk-scroll">
                <table class="tk-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Status</th>
                            <th scope="col">Tarheta status</th>
                            <th scope="col">Filing status</th>
                            <th scope="col">Filing number</th>
                            <th scope="col">Result</th>
                        </tr>
                    </thead>
                    <tbody id="tarhetaRows">
                        @foreach ($records as $record)
                            <tr class="tk-row" data-record-id="{{ $record['id'] }}" data-status="{{ $record['status'] }}" data-tarheta-status="{{ $record['tarheta_status'] }}" data-filing-status="{{ $record['filing_status'] }}" data-filing-number="{{ $record['filing_number'] }}" data-matched="{{ $record['matched'] ? '1' : '0' }}" @if ($record['matched']) hidden @endif>
                                <td class="tk-name" data-label="Name">{{ $record['name'] }}</td>
                                <td class="tk-status" data-label="Status"><span class="tk-badge tk-status-badge">{{ $record['status'] ?: 'Not set' }}</span></td>
                                <td class="tk-field" data-label="Tarheta status">
                                    <select class="tarheta-choice" aria-label="Tarheta status for {{ $record['name'] }}">
                                        <option value="">Not set</option>
                                        @foreach (['MS', 'UWP', 'MK', 'INACTIVE'] as $status)
                                            <option value="{{ $status }}" @selected($record['tarheta_status'] === $status)>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="tk-field" data-label="Filing status">
                                    <select class="filing-status-choice" aria-label="Filing status for {{ $record['name'] }}">
                                        <option value="">Not set</option>
                                        @foreach (['MS', 'UWP', 'MK', 'INACTIVE'] as $status)
                                            <option value="{{ $status }}" @selected($record['filing_status'] === $status)>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="tk-num" data-label="Filing number">
                                    <div>
                                        <input class="filing-number-input" type="text" maxlength="100" value="{{ $record['filing_number'] }}" aria-label="Filing number for {{ $record['name'] }}" placeholder="Enter filing number">
                                        <div class="tk-msg tarheta-message" role="status" aria-live="polite"></div>
                                    </div>
                                </td>
                                <td class="tk-result" data-label="Result">
                                    <span class="tk-res">Matched</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="tk-empty" id="tkNoResults" hidden>
                <strong id="tkNoResultsTitle"></strong>
                <span id="tkNoResultsText"></span>
            </div>

            <div class="tk-pager" id="tkPager" hidden>
                <span id="tkPagerInfo"></span>
                <div>
                    <button type="button" class="tk-btn" id="tkPrev">Previous</button>
                    <button type="button" class="tk-btn" id="tkNext">Next</button>
                </div>
            </div>
        @endif
    </section>
</main>

<script>
(() => {
    const rowsEl = document.getElementById('tarhetaRows');
    if (!rowsEl) return; // no records to work with

    const PAGE_SIZE = 25;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const UPDATE_URL = @json(route('tarheta-status.update', ['record' => '__RECORD__']));
    const $ = id => document.getElementById(id);
    const monitorFiling = $('monitorFiling'), search = $('tarhetaSearch');
    const radio = name => document.querySelector(`input[name="${name}"]:checked`).value;
    const allRows = () => [...rowsEl.querySelectorAll('tr[data-record-id]')];

    let page = 1;
    const pinned = new Set();       // rows just saved stay visible until the next filter/search change
    const clearTimers = new Map();  // per-row timers that dismiss the "saved" message

    function paint(badge, value) {
        badge.textContent = value || 'Not set';
        badge.className = 'tk-badge tk-status-badge' + (value ? '' : ' is-none');
    }

    // Tarheta (and filing, when monitored) must equal the record status.
    const isMatched = row => row.dataset.status === row.dataset.tarhetaStatus
        && (!monitorFiling.checked || row.dataset.status === row.dataset.filingStatus);

    // The reason is kept for the tooltip, not shown in the row.
    function explain(row) {
        const s = row.dataset.status, t = row.dataset.tarhetaStatus, f = row.dataset.filingStatus;
        const reasons = [];
        if (!t) reasons.push('Tarheta status is not set.');
        else if (t !== s) reasons.push(`Tarheta status is ${t}, record is ${s || 'not set'}.`);
        if (monitorFiling.checked) {
            if (!f) reasons.push('Filing status is not set.');
            else if (f !== s) reasons.push(`Filing status is ${f}, record is ${s || 'not set'}.`);
        }
        return reasons.length ? reasons.join(' ') : 'Statuses do not match the record status.';
    }

    function decorate(row) {
        const matched = row.dataset.matched === '1';
        paint(row.querySelector('.tk-status-badge'), row.dataset.status);
        const res = row.querySelector('.tk-res');
        res.textContent = matched ? 'Matched' : 'Not matched';
        res.classList.toggle('is-review', !matched);
        res.title = matched ? '' : explain(row);
        row.querySelector('.tarheta-choice').classList.toggle('is-mismatch', !matched && row.dataset.tarhetaStatus !== row.dataset.status);
        row.querySelector('.filing-status-choice').classList.toggle('is-mismatch', !matched && monitorFiling.checked && row.dataset.filingStatus !== row.dataset.status);
    }

    function render() {
        const term = search.value.trim().toLocaleLowerCase();
        const rows = allRows();
        rows.forEach(row => { row.dataset.matched = isMatched(row) ? '1' : '0'; decorate(row); });

        const total = rows.length;
        const matched = rows.filter(row => row.dataset.matched === '1').length;
        const percentage = total === 0 ? 0 : Math.round((matched / total) * 1000) / 10;
        $('tarhetaMatched').textContent = matched;
        $('tarhetaTotal').textContent = total;
        $('tarhetaCount').textContent = total - matched;
        $('tarhetaPercent').textContent = `${percentage}%`;
        $('tarhetaProgress').style.width = `${percentage}%`;
        const bar = document.querySelector('.tk-progress');
        bar.setAttribute('aria-valuemax', total);
        bar.setAttribute('aria-valuenow', matched);

        // Search the name and record status only. (Searching the whole row would also match every dropdown option.)
        const visible = rows.filter(row => {
            const hay = `${row.querySelector('.tk-name').textContent} ${row.dataset.status}`.toLocaleLowerCase();
            if (term && !hay.includes(term)) return false;
            const justSaved = pinned.has(row.dataset.recordId);
            if (radio('fnum') === 'missing' && (row.dataset.filingNumber || '').trim() !== '' && !justSaved) return false;
            const view = radio('view');
            if (view === 'all' || justSaved) return true;
            return view === 'matched' ? row.dataset.matched === '1' : row.dataset.matched !== '1';
        });

        const pages = Math.max(1, Math.ceil(visible.length / PAGE_SIZE));
        page = Math.min(page, pages);
        const start = (page - 1) * PAGE_SIZE;
        const shown = new Set(visible.slice(start, start + PAGE_SIZE));
        rows.forEach(row => { row.hidden = !shown.has(row); });

        const none = $('tkNoResults');
        none.hidden = visible.length > 0;
        if (!visible.length) {
            if (term) {
                $('tkNoResultsTitle').textContent = 'No matching records';
                $('tkNoResultsText').textContent = `Nothing matches "${search.value.trim()}". Try a different name or a status such as MS, UWP, MK or INACTIVE.`;
            } else if (radio('fnum') === 'missing') {
                $('tkNoResultsTitle').textContent = 'No records without a filing number';
                $('tkNoResultsText').textContent = radio('view') === 'unmatched'
                    ? 'Every not-matched record has a filing number. Switch "Show" to All to include matched records.'
                    : 'Every record in this view has a filing number.';
            } else if (radio('view') === 'unmatched' && matched === total) {
                $('tkNoResultsTitle').textContent = 'Every record is matched';
                $('tkNoResultsText').textContent = 'Switch "Show" to Matched or All to see them.';
            } else {
                $('tkNoResultsTitle').textContent = 'No records to show';
                $('tkNoResultsText').textContent = 'Change the filters above to see more records.';
            }
        }
        rowsEl.closest('.tk-scroll').hidden = visible.length === 0;

        $('tkPager').hidden = visible.length <= PAGE_SIZE;
        $('tkPagerInfo').textContent = visible.length ? `Showing ${start + 1}-${Math.min(start + PAGE_SIZE, visible.length)} of ${visible.length}` : '';
        $('tkPrev').disabled = page <= 1;
        $('tkNext').disabled = page >= pages;
    }

    const resetView = () => { pinned.clear(); page = 1; render(); };
    let searchTimer;
    search.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(resetView, 150); });
    monitorFiling.addEventListener('change', resetView);
    document.querySelectorAll('input[name="view"], input[name="fnum"]').forEach(input => input.addEventListener('change', resetView));
    $('tkPrev').addEventListener('click', () => { page--; render(); });
    $('tkNext').addEventListener('click', () => { page++; render(); });

    /* ---------------- Saving (same endpoint and payload as before) ---------------- */
    function setMessage(row, text, type) {
        const message = row.querySelector('.tarheta-message');
        message.className = 'tk-msg tarheta-message' + (type ? ' is-' + type : '');
        message.replaceChildren();
        if (type === 'saving') { const spin = document.createElement('span'); spin.className = 'tk-spin'; message.append(spin); }
        message.append(document.createTextNode(text));
        row.classList.toggle('is-error', type === 'error');
        clearTimeout(clearTimers.get(row));
        if (type === 'ok') clearTimers.set(row, setTimeout(() => { if (row.isConnected) { message.textContent = ''; message.className = 'tk-msg tarheta-message'; } }, 4000));
    }

    function restoreSaved(row) {
        row.querySelector('.tarheta-choice').value = row.dataset.tarhetaStatus || '';
        row.querySelector('.filing-status-choice').value = row.dataset.filingStatus || '';
        row.querySelector('.filing-number-input').value = row.dataset.filingNumber || '';
    }

    rowsEl.addEventListener('change', async event => {
        const control = event.target.closest('.tarheta-choice, .filing-status-choice, .filing-number-input');
        if (!control) return;
        const row = control.closest('tr[data-record-id]');
        const next = {
            tarheta_status: row.querySelector('.tarheta-choice').value || null,
            filing_status: row.querySelector('.filing-status-choice').value || null,
            filing_number: row.querySelector('.filing-number-input').value,
        };
        // Nothing changed from what is already saved: skip the request.
        if ((next.tarheta_status || '') === (row.dataset.tarhetaStatus || '')
            && (next.filing_status || '') === (row.dataset.filingStatus || '')
            && next.filing_number === (row.dataset.filingNumber || '')) return;

        const controls = [...row.querySelectorAll('.tarheta-choice, .filing-status-choice, .filing-number-input')];
        controls.forEach(input => { input.disabled = true; });
        setMessage(row, 'Saving...', 'saving');
        try {
            const response = await fetch(UPDATE_URL.replace('__RECORD__', encodeURIComponent(row.dataset.recordId)), {
                method: 'PUT',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(next),
            });
            let result = {};
            try { result = await response.json(); } catch (error) { throw new Error('The server returned an unreadable response.'); }
            if (!response.ok) {
                const errors = Object.values(result.errors || {}).flat();
                throw new Error(errors.join(' ') || result.message || 'Could not save Tarheta status.');
            }
            row.dataset.status = result.status ?? '';
            row.dataset.tarhetaStatus = result.tarheta_status ?? '';
            row.dataset.filingStatus = result.filing_status ?? '';
            row.dataset.filingNumber = result.filing_number ?? '';
            restoreSaved(row);
            pinned.add(row.dataset.recordId);
            setMessage(row, 'Saved.', 'ok');
            render();
        } catch (error) {
            // Show what is actually stored, not the value that failed to save.
            restoreSaved(row);
            setMessage(row, `${error.message || 'Could not save Tarheta status.'} Your change was not saved.`, 'error');
            render();
        } finally {
            if (row.isConnected) controls.forEach(input => { input.disabled = false; });
        }
    });

    render();
})();
</script>
@endsection