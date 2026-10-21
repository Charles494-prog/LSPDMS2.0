@extends('layouts.app')

@section('title', 'Filter & BNH Helper')

@push('styles')
<style>
    .fbnh-page { --fbnh-accent: var(--accent, #1769d2); display:grid; gap:1.1rem; color:var(--text); }
    .fbnh-card { min-width:0; padding:1.4rem; border:1px solid var(--border); border-radius:16px; background:var(--surface); box-shadow:0 5px 18px rgba(15,23,42,.045); }
    .fbnh-card h2 { margin:0; font-size:1.15rem; line-height:1.35; letter-spacing:-.015em; }
    .fbnh-subtitle { margin:.35rem 0 0; color:var(--text-muted); font-size:.9rem; line-height:1.5; }
    .fbnh-filter-card { padding:clamp(1.15rem,2.4vw,1.8rem); background:var(--surface); }
    .fbnh-page-heading { align-items:center; margin-bottom:1.35rem; }
    .fbnh-eyebrow { display:inline-flex; align-items:center; gap:.4rem; margin-bottom:.45rem; color:var(--fbnh-accent); font-size:.72rem; font-weight:800; letter-spacing:.11em; text-transform:uppercase; }
    .fbnh-page-title { margin:0; font-size:clamp(1.35rem,2.5vw,1.75rem)!important; }
    .fbnh-filter-heading { display:flex; align-items:center; gap:.55rem; margin:0 0 .65rem; font-size:.91rem; font-weight:800; }
    .fbnh-filter-heading small { margin-left:auto; color:var(--text-muted); font-size:.73rem; font-weight:600; }
    .fbnh-filters { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; max-width:1060px; }
    .fbnh-field { min-width:0; }
    .fbnh-field label { font-size:.84rem; font-weight:700; }
    .fbnh-field input { color:var(--text); font:inherit; }
    .fbnh-purok-picker { display:grid; gap:.55rem; padding:.8rem; border:1px solid var(--border); border-radius:12px; background:color-mix(in srgb,var(--bg) 68%,var(--surface)); }
    .fbnh-purok-picker > label { display:flex; align-items:center; gap:.65rem; margin:0; padding:.25rem .35rem .65rem; border-bottom:1px solid var(--border); cursor:pointer; font-size:.9rem; }
    .fbnh-purok-picker > label:first-child { font-weight:800; }
    .fbnh-purok-picker input { flex:none; width:18px; height:18px; margin:0; accent-color:var(--fbnh-accent); }
    .fbnh-purok-options { display:grid; grid-template-columns:repeat(auto-fit,minmax(135px,1fr)); gap:.3rem .5rem; max-height:200px; overflow:auto; padding-top:.15rem; }
    .fbnh-purok-options label { display:flex; align-items:center; gap:.6rem; min-height:38px; margin:0; padding:.35rem .45rem; border:1px solid transparent; border-radius:8px; cursor:pointer; font-size:.85rem; transition:background .15s,border-color .15s; }
    .fbnh-purok-options label:hover { border-color:color-mix(in srgb,var(--fbnh-accent) 22%,var(--border)); background:color-mix(in srgb,var(--fbnh-accent) 5%,var(--surface)); }
    .fbnh-purok-options label:has(input:disabled) { color:var(--text-muted); cursor:not-allowed; }
    .fbnh-purok-options label:has(input:disabled):hover { border-color:transparent; background:transparent; }
    .fbnh-locked-label { margin-left:auto; font-size:.69rem; font-weight:600; }
    .fbnh-head { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:1rem; }
    .fbnh-actions { display:flex; flex-wrap:wrap; align-items:center; gap:.6rem; }
    .fbnh-button { display:inline-flex; align-items:center; justify-content:center; gap:.55rem; min-height:40px; padding:.58rem .85rem; border:1px solid var(--border); border-radius:9px; background:var(--surface); color:var(--text); font:inherit; font-size:.84rem; font-weight:750; text-decoration:none; cursor:pointer; transition:transform .15s,box-shadow .15s,background .15s; }
    .fbnh-button:hover:not(:disabled) { transform:translateY(-1px); box-shadow:0 5px 12px rgba(15,23,42,.1); }
    .fbnh-button:focus-visible,.fbnh-tab:focus-visible,.fbnh-count-link:focus-visible { outline:3px solid color-mix(in srgb,var(--fbnh-accent) 42%,transparent); outline-offset:2px; }
    .fbnh-button:disabled { opacity:.65; cursor:wait; }
    .fbnh-button-primary { background:var(--fbnh-accent); color:#fff; border-color:var(--fbnh-accent); }
    .fbnh-reload-button { min-height:46px; padding:.7rem 1rem; background:var(--fbnh-accent); border:1px solid var(--fbnh-accent); color:#fff; box-shadow:0 5px 14px color-mix(in srgb,var(--fbnh-accent) 25%,transparent); }
    .fbnh-reload-button:hover:not(:disabled) { background:color-mix(in srgb,var(--fbnh-accent) 88%,#000); }
    .fbnh-reload-icon { display:block; width:18px; height:18px; }
    .fbnh-reload-button.is-loading .fbnh-reload-icon { animation:fbnh-spin .8s linear infinite; }
    @keyframes fbnh-spin { to { transform:rotate(360deg); } }
    .fbnh-table-wrap { width:100%; overflow:auto; border:1px solid var(--border); border-radius:12px; }
    .fbnh-table { width:100%; border-collapse:separate; border-spacing:0; min-width:540px; }
    .fbnh-table th,.fbnh-table td { padding:.76rem .9rem; border-bottom:1px solid var(--border); text-align:left; white-space:nowrap; }
    .fbnh-table th { position:sticky; top:0; z-index:1; background:color-mix(in srgb,var(--bg) 84%,var(--surface)); color:var(--text-muted); font-size:.72rem; font-weight:800; letter-spacing:.055em; text-transform:uppercase; }
    .fbnh-table tbody tr:last-child td { border-bottom:0; }
    .fbnh-table tbody tr:not(.fbnh-total-row):not(.fbnh-purok-total-row):hover { background:color-mix(in srgb,var(--fbnh-accent) 4%,var(--surface)); }
    .fbnh-table .number { text-align:center; }
    .fbnh-count-link { min-width:3rem; padding:.38rem .6rem; border:0; border-radius:7px; background:transparent; color:var(--fbnh-accent); font:inherit; font-weight:800; cursor:pointer; }
    .fbnh-count-link:hover { background:color-mix(in srgb,var(--fbnh-accent) 10%,transparent); }
    .fbnh-total-row { font-weight:800; background:color-mix(in srgb,var(--fbnh-accent) 8%,var(--surface)); }
    .fbnh-purok-total-row { font-weight:750; background:color-mix(in srgb,var(--bg) 62%,var(--surface)); }
    .fbnh-resumen-toolbar { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem; margin:1.25rem 0 .85rem; padding:.9rem 1rem; border:1px solid var(--border); border-radius:12px; background:color-mix(in srgb,var(--bg) 55%,var(--surface)); }
    .fbnh-resumen-options { display:flex; flex-wrap:wrap; align-items:center; gap:.55rem 1.1rem; margin:0; padding:0; border:0; }
    .fbnh-resumen-options legend { float:left; margin-right:1rem; color:var(--text-muted); font-size:.75rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
    .fbnh-resumen-options label { display:inline-flex; align-items:center; gap:.5rem; min-height:32px; font-size:.84rem; font-weight:700; cursor:pointer; }
    .fbnh-resumen-options input { width:17px; height:17px; margin:0; accent-color:var(--fbnh-accent); }
    .fbnh-resumen-options input:focus-visible { outline:3px solid color-mix(in srgb,var(--fbnh-accent) 35%,transparent); outline-offset:2px; }
    .fbnh-counts-table { width:28rem; min-width:28rem; table-layout:fixed; }
    .fbnh-counts-table[data-count-mode="both"] { width:40rem; min-width:40rem; }
    .fbnh-counts-table th:first-child,.fbnh-counts-table td:first-child { width:9.5rem; }
    .fbnh-counts-table th,.fbnh-counts-table td { padding:.65rem .5rem; }
    .fbnh-counts-table thead tr:first-child th { text-align:center; }
    .fbnh-counts-table thead tr:first-child th:first-child { text-align:left; }
    .fbnh-counts-table .fbnh-summary-group { color:var(--fbnh-accent); background:color-mix(in srgb,var(--fbnh-accent) 7%,var(--surface)); }
    .fbnh-counts-table .fbnh-summary-minus { color:var(--text-muted); background:color-mix(in srgb,var(--bg) 84%,var(--surface)); }
    .fbnh-status { min-height:1.2rem; margin-top:.65rem; color:var(--text-muted); font-size:.82rem; }
    .fbnh-status[data-error="true"] { color:var(--danger); }
    .fbnh-check { width:18px; height:18px; accent-color:var(--fbnh-accent); cursor:pointer; }
    .fbnh-verdict { display:inline-block; padding:.35rem .6rem; border-radius:999px; font-size:.72rem; font-weight:850; letter-spacing:.025em; }
    .fbnh-verdict.qualified { color:#067647; background:#dcfae6; }
    .fbnh-verdict.not-qualified { color:#b42318; background:#fee4e2; }
    .fbnh-empty { padding:1.5rem!important; text-align:center!important; color:var(--text-muted); }
    .fbnh-pagination { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-top:.9rem; color:var(--text-muted); font-size:.85rem; }
    .fbnh-pagination-controls { display:flex; gap:.5rem; }
    .fbnh-tabs { display:flex; flex-wrap:wrap; gap:.5rem; padding:.45rem; border:1px solid var(--border); border-radius:13px; background:color-mix(in srgb,var(--bg) 66%,var(--surface)); }
    .fbnh-tab { display:inline-flex; align-items:center; min-height:42px; padding:.65rem 1rem; border:1px solid transparent; border-radius:9px; background:transparent; color:var(--text-muted); font:inherit; font-size:.85rem; font-weight:750; cursor:pointer; transition:background .15s,color .15s,box-shadow .15s; }
    .fbnh-tab:hover:not([aria-selected="true"]) { background:var(--surface); color:var(--text); }
    .fbnh-tab[aria-selected="true"] { background:var(--surface); border-color:var(--border); color:var(--fbnh-accent); box-shadow:0 2px 6px rgba(15,23,42,.08); }
    .fbnh-tab-panel[hidden] { display:none; }
    .fbnh-modal-overlay { position:fixed; inset:0; z-index:1000; display:grid; place-items:center; padding:1rem; background:rgba(15,23,42,.58); backdrop-filter:blur(3px); }
    .fbnh-modal-overlay[hidden] { display:none; }
    .fbnh-modal { width:min(720px,100%); max-height:min(82vh,800px); overflow:auto; padding:1.4rem; border:1px solid var(--border); border-radius:16px; background:var(--surface); color:var(--text); box-shadow:0 24px 70px rgba(0,0,0,.24); }
    .fbnh-modal-header { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1rem; }
    .fbnh-modal-header h2 { margin:0; font-size:1.15rem; }
    .fbnh-modal-header p { margin:.35rem 0 0; color:var(--text-muted); font-size:.85rem; }
    #fbnhSearch { width:min(100%,420px); padding:.68rem .8rem; border:1px solid var(--border); border-radius:9px; background:var(--surface); font:inherit; }
    #fbnhSearch:focus { border-color:var(--fbnh-accent); outline:3px solid color-mix(in srgb,var(--fbnh-accent) 15%,transparent); }
    @media (max-width:700px) { .fbnh-filters { grid-template-columns:1fr; } .fbnh-card { padding:1rem; } .fbnh-page-heading { align-items:flex-start; } .fbnh-reload-button { width:100%; } }
    @media (max-width:480px) { .fbnh-tabs { display:grid; grid-template-columns:1fr; } .fbnh-tab { justify-content:center; } .fbnh-pagination { align-items:flex-start; flex-direction:column; } }
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

    <section class="fbnh-card fbnh-filter-card">
        <div class="fbnh-head fbnh-page-heading">
            <div>
                <span class="fbnh-eyebrow">Records workspace</span>
                <h1 class="fbnh-page-title">Filter &amp; BNH Helper</h1>
                <p class="fbnh-subtitle">Explore active records, update BNH checks, and prepare your resumen. Inactive records are excluded.</p>
            </div>
            <button type="button" class="fbnh-button fbnh-reload-button" id="fbnhReload" aria-label="Reload results without changing filters">
                <svg class="fbnh-reload-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M20 7v5h-5M4 17v-5h5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M5.6 9a7 7 0 0 1 11.7-2L20 12M4 12l2.7 5a7 7 0 0 0 11.7-2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span id="fbnhReloadLabel">Reload Data</span>
            </button>
        </div>
        <div class="fbnh-filters">
            <div class="fbnh-field">
                <div class="fbnh-filter-heading"><span>Purok</span><small id="fbnhPurokSelection">All selected</small></div>
                <div class="fbnh-purok-picker" id="fbnhPurokPicker">
                    <label><input id="fbnhAllPuroks" type="checkbox" checked>All Puroks</label>
                    <div class="fbnh-purok-options" id="fbnhPurokOptions">
                        @foreach ($puroks as $purok)
                            <label>
                                <input class="fbnh-purok-option" type="checkbox" value="{{ $purok->id }}" checked>
                                {{ $purok->purok_name ?: 'Purok '.$purok->purok_number }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="fbnh-field">
                <div class="fbnh-filter-heading"><span>Kalagayan</span><small id="fbnhStatusSelection">All selected</small></div>
                <div class="fbnh-purok-picker" id="fbnhStatusPicker">
                    <label><input id="fbnhAllStatuses" type="checkbox" checked>All</label>
                    <div class="fbnh-purok-options" id="fbnhStatusOptions">
                        @foreach (['MS', 'UWP', 'MK'] as $status)
                            <label>
                                <input class="fbnh-status-option" type="checkbox" value="{{ $status }}" @checked($status !== 'MK' || $canViewMk) @disabled($status === 'MK' && ! $canViewMk)>
                                {{ $status }}@if ($status === 'MK' && ! $canViewMk) <span class="fbnh-locked-label">De-Listment access required</span> @endif
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="fbnh-status" id="fbnhPageStatus" role="status" aria-live="polite"></div>
    </section>

    <div class="fbnh-tabs" role="tablist" aria-label="Filter and BNH tools">
        <button type="button" class="fbnh-tab" id="fbnhTabMatrix" role="tab" aria-controls="fbnhPanelMatrix" aria-selected="true">Purok Group Filter</button>
        <button type="button" class="fbnh-tab" id="fbnhTabList" role="tab" aria-controls="fbnhPanelList" aria-selected="false" tabindex="-1">BNH Form Helper</button>
        <button type="button" class="fbnh-tab" id="fbnhTabResumen" role="tab" aria-controls="fbnhPanelResumen" aria-selected="false" tabindex="-1">BNH Helper Form Resumen</button>
    </div>

    <section class="fbnh-card fbnh-tab-panel" id="fbnhPanelMatrix" role="tabpanel" aria-labelledby="fbnhTabMatrix">
        <div class="fbnh-head">
            <div>
                <h2>Purok Group Filter</h2>
                <p class="fbnh-subtitle">Select a Male, Female, or Total count to view the matching members.</p>
            </div>
        </div>
        <div class="fbnh-table-wrap">
            <table class="fbnh-table">
                <thead><tr><th>Purok-Grupo</th><th class="number">Male</th><th class="number">Female</th><th class="number">Total</th></tr></thead>
                <tbody id="fbnhMatrixBody"><tr><td colspan="4" class="fbnh-empty">Loading group counts…</td></tr></tbody>
            </table>
        </div>
        <div class="fbnh-status" id="fbnhMatrixStatus" role="status"></div>
    </section>

    <section class="fbnh-card fbnh-tab-panel" id="fbnhPanelList" role="tabpanel" aria-labelledby="fbnhTabList" hidden>
        <div class="fbnh-head">
            <div>
                <h2>BNH Form Helper</h2>
                <p class="fbnh-subtitle">Mark Salaysay / Pakikipag-usap and Paghahanda. A member is qualified only when both are checked.</p>
            </div>
            <div class="fbnh-actions">
                <a class="fbnh-button fbnh-button-primary" id="fbnhDownloadList" href="#">Download Word Form</a>
            </div>
        </div>
        <div class="fbnh-filters" style="margin-bottom:1rem;">
            <div class="fbnh-field">
                <label for="fbnhSearch">Search BNH records</label>
                <input type="search" id="fbnhSearch" maxlength="150" placeholder="Search by member name…">
            </div>
        </div>
        <div class="fbnh-table-wrap">
            <table class="fbnh-table">
                <thead><tr><th>Name</th><th>Kalagayan</th><th>Purok-Grupo</th><th>Salaysay / Pakikipag-usap</th><th>Paghahanda</th><th>Verdict</th></tr></thead>
                <tbody id="fbnhRecordsBody"><tr><td colspan="6" class="fbnh-empty">Loading BNH helper records…</td></tr></tbody>
            </table>
        </div>
        <div class="fbnh-pagination">
            <span id="fbnhPageInfo">Page 1</span>
            <div class="fbnh-pagination-controls">
                <button type="button" class="fbnh-button" id="fbnhPrevious" disabled>Previous</button>
                <button type="button" class="fbnh-button" id="fbnhNext" disabled>Next</button>
            </div>
        </div>
        <div class="fbnh-status" id="fbnhRecordsStatus" role="status"></div>
    </section>

    <section class="fbnh-card fbnh-tab-panel" id="fbnhPanelResumen" role="tabpanel" aria-labelledby="fbnhTabResumen" hidden>
        <div class="fbnh-head">
            <div>
                <h2>BNH Helper Form Resumen</h2>
                <p class="fbnh-subtitle">Qualified members have both BNH checks. Total minuses is the selected MS, UWP, and/or MK count minus those qualified members.</p>
            </div>
            <div class="fbnh-actions">
                <a class="fbnh-button fbnh-button-primary" id="fbnhDownloadCounts" href="#">Download Resumen</a>
            </div>
        </div>
        <div class="fbnh-resumen-toolbar">
            <fieldset class="fbnh-resumen-options">
                <legend>Show counts</legend>
                <label><input type="radio" name="fbnhCountMode" id="fbnhShowQualified" value="qualified"> Qualified for BNH</label>
                <label><input type="radio" name="fbnhCountMode" id="fbnhShowMinuses" value="minuses"> Total minuses</label>
                <label><input type="radio" name="fbnhCountMode" id="fbnhShowBoth" value="both" checked> Both</label>
            </fieldset>
        </div>
        <div class="fbnh-table-wrap">
            <table class="fbnh-table fbnh-counts-table">
                <thead id="fbnhCountsHead">
                    <tr><th rowspan="2">Purok-Grupo</th><th class="fbnh-summary-group" colspan="3">Qualified for BNH</th><th class="fbnh-summary-minus" colspan="3">Total minuses</th></tr>
                    <tr><th class="number">L</th><th class="number">B</th><th class="number">Total</th><th class="number">L</th><th class="number">B</th><th class="number">Total</th></tr>
                </thead>
                <tbody id="fbnhCountsBody"><tr><td colspan="7" class="fbnh-empty">Loading counts…</td></tr></tbody>
            </table>
        </div>
        <div class="fbnh-status" id="fbnhCountsStatus" role="status"></div>
    </section>
</div>

<div class="fbnh-modal-overlay" id="fbnhMembersModal" role="presentation" hidden>
    <section class="fbnh-modal" role="dialog" aria-modal="true" aria-labelledby="fbnhMemberTitle">
        <div class="fbnh-modal-header">
            <div>
                <h2 id="fbnhMemberTitle">Members</h2>
                <p id="fbnhMemberCount"></p>
            </div>
            <button type="button" class="fbnh-button" id="fbnhMemberClose">Close</button>
        </div>
        <div class="fbnh-table-wrap">
            <table class="fbnh-table">
                <thead><tr><th>Name</th><th>Kalagayan</th><th>Purok-Grupo</th></tr></thead>
                <tbody id="fbnhMemberBody"></tbody>
            </table>
        </div>
    </section>
</div>

<script>
(() => {
    const app = document.getElementById('fbnhApp');
    if (!app) return;

    const allPuroksCheckbox = document.getElementById('fbnhAllPuroks');
    const purokCheckboxes = Array.from(document.querySelectorAll('.fbnh-purok-option'));
    const allStatusesCheckbox = document.getElementById('fbnhAllStatuses');
    const statusCheckboxes = Array.from(document.querySelectorAll('.fbnh-status-option'));
    const availableStatusCheckboxes = statusCheckboxes.filter(checkbox => !checkbox.disabled);
    const searchInput = document.getElementById('fbnhSearch');
    const showQualifiedRadio = document.getElementById('fbnhShowQualified');
    const showMinusesRadio = document.getElementById('fbnhShowMinuses');
    const showBothRadio = document.getElementById('fbnhShowBoth');
    const csrf = app.dataset.csrf;
    let currentPage = 1;
    let lastPage = 1;
    let searchTimer;

    const query = (extra = {}) => {
        const params = new URLSearchParams({
            purok_selection: 'selected',
            status_selection: 'selected',
        });
        purokCheckboxes.filter(checkbox => checkbox.checked).forEach(checkbox => params.append('purok_ids[]', checkbox.value));
        availableStatusCheckboxes.filter(checkbox => checkbox.checked).forEach(checkbox => params.append('status_ids[]', checkbox.value));
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
            headers: { 'Accept': 'application/json', ...(options.body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf } : {}) },
            ...options,
        });
        let payload = {};
        try { payload = await response.json(); } catch (error) { throw new Error('The server returned an unreadable response.'); }
        if (!response.ok) {
            const message = payload.message || Object.values(payload.errors || {}).flat()[0] || 'Unable to load the requested data.';
            throw new Error(message);
        }
        return payload;
    }

    function setStatus(id, message = '', isError = false) {
        const node = document.getElementById(id);
        node.textContent = message;
        node.dataset.error = isError ? 'true' : 'false';
    }

    function cell(row, text, className = '') {
        const node = document.createElement('td');
        node.textContent = text;
        if (className) node.className = className;
        row.append(node);
        return node;
    }

    function appendCountButton(row, item, gender, count, groupLabel) {
        const td = document.createElement('td');
        td.className = 'number';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'fbnh-count-link';
        button.textContent = String(count);
        button.setAttribute('aria-label', `Show ${gender === 'all' ? 'all' : gender} records in ${groupLabel}`);
        button.addEventListener('click', () => loadMembers(item, gender));
        td.append(button);
        row.append(td);
    }

    async function loadMatrix() {
        const body = document.getElementById('fbnhMatrixBody');
        body.replaceChildren();
        try {
            const data = await requestJson(`${app.dataset.matrixUrl}?${query()}`);
            for (const item of data.rows) {
                const row = document.createElement('tr');
                row.className = item.type === 'grand_total' ? 'fbnh-total-row' : (item.type === 'purok_total' ? 'fbnh-purok-total-row' : '');
                cell(row, item.label);
                appendCountButton(row, item, 'male', item.male, item.label);
                appendCountButton(row, item, 'female', item.female, item.label);
                appendCountButton(row, item, 'all', item.total, item.label);
                body.append(row);
            }
            if (!data.rows.length) {
                const empty = document.createElement('tr');
                const emptyCell = cell(empty, 'No configured Purok groups found.', 'fbnh-empty');
                emptyCell.colSpan = 4;
                body.prepend(empty);
            }
            setStatus('fbnhMatrixStatus');
        } catch (error) {
            setStatus('fbnhMatrixStatus', error.message, true);
            const row = document.createElement('tr');
            const message = cell(row, error.message, 'fbnh-empty');
            message.colSpan = 4;
            body.append(row);
        }
    }

    async function loadMembers(group, gender) {
        const modal = document.getElementById('fbnhMembersModal');
        const body = document.getElementById('fbnhMemberBody');
        const genderLabel = gender === 'all' ? 'All' : (gender === 'male' ? 'Male' : 'Female');
        document.getElementById('fbnhMemberTitle').textContent = `${genderLabel} records in ${group.label}`;
        body.replaceChildren();
        document.getElementById('fbnhMemberCount').textContent = 'Loading matching records…';
        modal.hidden = false;
        document.getElementById('fbnhMemberClose').focus();
        try {
            const params = query({
                purok_ids: group.purok_id === null ? purokCheckboxes.filter(option => option.checked).map(option => option.value) : [String(group.purok_id)],
                grupo: group.grupo ?? '',
                gender,
            });
            const data = await requestJson(`${app.dataset.membersUrl}?${params}`);
            document.getElementById('fbnhMemberCount').textContent = `${data.count} active record${data.count === 1 ? '' : 's'}`;
            if (!data.records.length) {
                const row = document.createElement('tr');
                const empty = cell(row, 'No matching records.', 'fbnh-empty');
                empty.colSpan = 3;
                body.append(row);
            } else {
                for (const member of data.records) {
                    const row = document.createElement('tr');
                    cell(row, member.name);
                    cell(row, member.status || '—');
                    cell(row, member.location || '—');
                    body.append(row);
                }
            }
        } catch (error) {
            document.getElementById('fbnhMemberCount').textContent = error.message;
            const row = document.createElement('tr');
            const message = cell(row, error.message, 'fbnh-empty');
            message.colSpan = 3;
            body.append(row);
        }
    }

    function verdict(isValid1, isValid2) {
        const qualified = isValid1 && isValid2;
        const badge = document.createElement('span');
        badge.className = `fbnh-verdict ${qualified ? 'qualified' : 'not-qualified'}`;
        badge.textContent = qualified ? 'QUALIFIED' : 'NOT QUALIFIED';
        return badge;
    }

    async function saveChecks(record, row, checkbox, previousState) {
        const checkboxes = row.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(input => { input.disabled = true; });
        const values = {
            is_valid1: row.querySelector('[data-check="is_valid1"]').checked ? 1 : 0,
            is_valid2: row.querySelector('[data-check="is_valid2"]').checked ? 1 : 0,
        };
        try {
            await requestJson(`${app.dataset.checksUrl}/${record.id}/checks`, {
                method: 'PUT',
                body: JSON.stringify(values),
            });
            row.querySelector('[data-verdict]').replaceChildren(verdict(Boolean(values.is_valid1), Boolean(values.is_valid2)));
            setStatus('fbnhRecordsStatus', 'BNH checks saved.');
            await loadQualifiedCounts();
        } catch (error) {
            checkbox.checked = previousState;
            setStatus('fbnhRecordsStatus', error.message, true);
        } finally {
            checkboxes.forEach(input => { input.disabled = false; });
        }
    }

    async function loadBnhRecords() {
        const body = document.getElementById('fbnhRecordsBody');
        body.replaceChildren();
        try {
            const data = await requestJson(`${app.dataset.bnhUrl}?${query({ page: currentPage, q: searchInput.value.trim() })}`);
            lastPage = data.last_page;
            document.getElementById('fbnhPageInfo').textContent = `Page ${data.page} of ${data.last_page} · ${data.total} records`;
            document.getElementById('fbnhPrevious').disabled = data.page <= 1;
            document.getElementById('fbnhNext').disabled = data.page >= data.last_page;
            for (const record of data.records) {
                const row = document.createElement('tr');
                cell(row, record.name);
                cell(row, record.status || '—');
                cell(row, record.location || '—');
                for (const key of ['is_valid1', 'is_valid2']) {
                    const td = document.createElement('td');
                    td.className = 'number';
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'fbnh-check';
                    checkbox.dataset.check = key;
                    checkbox.checked = record[key];
                    checkbox.setAttribute('aria-label', `${key === 'is_valid1' ? 'Salaysay / Pakikipag-usap' : 'Paghahanda'} for ${record.name}`);
                    checkbox.addEventListener('change', () => saveChecks(record, row, checkbox, !checkbox.checked));
                    td.append(checkbox);
                    row.append(td);
                }
                const verdictCell = document.createElement('td');
                verdictCell.dataset.verdict = 'true';
                verdictCell.append(verdict(record.is_valid1, record.is_valid2));
                row.append(verdictCell);
                body.append(row);
            }
            if (!data.records.length) {
                const row = document.createElement('tr');
                const empty = cell(row, 'No matching active records.', 'fbnh-empty');
                empty.colSpan = 6;
                body.append(row);
            }
            setStatus('fbnhRecordsStatus');
        } catch (error) {
            setStatus('fbnhRecordsStatus', error.message, true);
            const row = document.createElement('tr');
            const message = cell(row, error.message, 'fbnh-empty');
            message.colSpan = 6;
            body.append(row);
        }
    }

    async function loadQualifiedCounts() {
        const body = document.getElementById('fbnhCountsBody');
        const head = document.getElementById('fbnhCountsHead');
        const showQualified = showQualifiedRadio.checked || showBothRadio.checked;
        const showMinuses = showMinusesRadio.checked || showBothRadio.checked;
        const countMode = showBothRadio.checked ? 'both' : showQualified ? 'qualified' : 'minuses';
        document.querySelector('.fbnh-counts-table').dataset.countMode = countMode;
        const columnCount = 1 + (showQualified ? 3 : 0) + (showMinuses ? 3 : 0);
        body.replaceChildren();
        head.replaceChildren();
        const groupHeader = document.createElement('tr');
        const labelHeader = document.createElement('th');
        labelHeader.textContent = 'Purok-Grupo';
        labelHeader.rowSpan = 2;
        groupHeader.append(labelHeader);
        if (showQualified) {
            const qualifiedHeader = document.createElement('th');
            qualifiedHeader.className = 'fbnh-summary-group';
            qualifiedHeader.colSpan = 3;
            qualifiedHeader.textContent = 'Qualified for BNH';
            groupHeader.append(qualifiedHeader);
        }
        if (showMinuses) {
            const minusesHeader = document.createElement('th');
            minusesHeader.className = 'fbnh-summary-minus';
            minusesHeader.colSpan = 3;
            minusesHeader.textContent = 'Total minuses';
            groupHeader.append(minusesHeader);
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

        try {
            const data = await requestJson(`${app.dataset.countsUrl}?${query()}`);
            for (const item of data.rows) {
                const row = document.createElement('tr');
                row.className = item.type === 'grand_total' ? 'fbnh-total-row' : (item.type === 'purok_total' ? 'fbnh-purok-total-row' : '');
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
            if (!data.rows.length) {
                const row = document.createElement('tr');
                const empty = cell(row, 'No configured Purok groups found.', 'fbnh-empty');
                empty.colSpan = columnCount;
                body.prepend(row);
            }
            setStatus('fbnhCountsStatus');
        } catch (error) {
            setStatus('fbnhCountsStatus', error.message, true);
            const row = document.createElement('tr');
            const message = cell(row, error.message, 'fbnh-empty');
            message.colSpan = columnCount;
            body.append(row);
        }
    }

    function updateDownloadLinks() {
        document.getElementById('fbnhDownloadList').href = `${app.dataset.wordUrl}?${query({ q: searchInput.value.trim() })}`;
        document.getElementById('fbnhDownloadCounts').href = `${app.dataset.countsWordUrl}?${query({
            show_qualified: showQualifiedRadio.checked || showBothRadio.checked ? '1' : '0',
            show_minuses: showMinusesRadio.checked || showBothRadio.checked ? '1' : '0',
        })}`;
    }

    function updateFilterSummary() {
        const selectedPuroks = purokCheckboxes.filter(checkbox => checkbox.checked).length;
        const selectedStatuses = availableStatusCheckboxes.filter(checkbox => checkbox.checked).length;
        document.getElementById('fbnhPurokSelection').textContent = selectedPuroks === purokCheckboxes.length
            ? 'All selected'
            : `${selectedPuroks} of ${purokCheckboxes.length} selected`;
        document.getElementById('fbnhStatusSelection').textContent = selectedStatuses === availableStatusCheckboxes.length
            ? 'All selected'
            : `${selectedStatuses} of ${availableStatusCheckboxes.length} selected`;
    }

    function refreshFilters() {
        currentPage = 1;
        updateFilterSummary();
        updateDownloadLinks();
        loadMatrix();
        loadBnhRecords();
        loadQualifiedCounts();
        document.getElementById('fbnhMembersModal').hidden = true;
    }

    async function reloadData() {
        const button = document.getElementById('fbnhReload');
        const label = document.getElementById('fbnhReloadLabel');
        button.disabled = true;
        button.classList.add('is-loading');
        label.textContent = 'Reloading…';
        updateDownloadLinks();
        try {
            await Promise.all([loadMatrix(), loadBnhRecords(), loadQualifiedCounts()]);
        } finally {
            button.disabled = false;
            button.classList.remove('is-loading');
            label.textContent = 'Reload Data';
        }
    }

    document.getElementById('fbnhReload').addEventListener('click', reloadData);
    [showQualifiedRadio, showMinusesRadio, showBothRadio].forEach(radio => radio.addEventListener('change', () => {
        updateDownloadLinks();
        loadQualifiedCounts();
    }));
    allPuroksCheckbox.addEventListener('change', () => {
        purokCheckboxes.forEach(checkbox => { checkbox.checked = allPuroksCheckbox.checked; });
        refreshFilters();
    });
    purokCheckboxes.forEach(checkbox => checkbox.addEventListener('change', () => {
        allPuroksCheckbox.checked = purokCheckboxes.every(option => option.checked);
        refreshFilters();
    }));
    allStatusesCheckbox.addEventListener('change', () => {
        availableStatusCheckboxes.forEach(checkbox => { checkbox.checked = allStatusesCheckbox.checked; });
        refreshFilters();
    });
    availableStatusCheckboxes.forEach(checkbox => checkbox.addEventListener('change', () => {
        allStatusesCheckbox.checked = availableStatusCheckboxes.every(option => option.checked);
        refreshFilters();
    }));
    searchInput.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        updateDownloadLinks();
        searchTimer = window.setTimeout(() => {
            currentPage = 1;
            loadBnhRecords();
        }, 250);
    });
    document.getElementById('fbnhMemberClose').addEventListener('click', () => { document.getElementById('fbnhMembersModal').hidden = true; });
    document.getElementById('fbnhMembersModal').addEventListener('click', event => {
        if (event.target === document.getElementById('fbnhMembersModal')) document.getElementById('fbnhMembersModal').hidden = true;
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') document.getElementById('fbnhMembersModal').hidden = true;
    });
    const tabs = Array.from(document.querySelectorAll('.fbnh-tab'));
    function selectTab(tab) {
        tabs.forEach(button => {
            const selected = button === tab;
            button.setAttribute('aria-selected', String(selected));
            button.tabIndex = selected ? 0 : -1;
            document.getElementById(button.getAttribute('aria-controls')).hidden = !selected;
        });
    }
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => selectTab(tab));
        tab.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const nextIndex = event.key === 'Home' ? 0
                : event.key === 'End' ? tabs.length - 1
                    : (index + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length;
            tabs[nextIndex].focus();
            selectTab(tabs[nextIndex]);
        });
    });
    document.getElementById('fbnhPrevious').addEventListener('click', () => { if (currentPage > 1) { currentPage--; loadBnhRecords(); } });
    document.getElementById('fbnhNext').addEventListener('click', () => { if (currentPage < lastPage) { currentPage++; loadBnhRecords(); } });
    updateFilterSummary();
    updateDownloadLinks();
    loadMatrix();
    loadBnhRecords();
    loadQualifiedCounts();
})();
</script>
@endsection
