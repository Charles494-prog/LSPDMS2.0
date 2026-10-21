@extends('layouts.app')

@section('title', 'Form Generator')

@section('content')
<style>
    .form-hub { max-width: 1180px; margin: 0 auto; }
    .form-hero {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        overflow: hidden;
        margin-bottom: 1.35rem;
        padding: clamp(1.5rem, 4vw, 2.4rem);
        border: 1px solid var(--border);
        border-radius: 20px;
        background: var(--surface);
        box-shadow: 0 1px 3px rgba(15,23,42,.04);
    }
    .form-eyebrow { margin: 0 0 .55rem; color: var(--accent); font-size: .74rem; font-weight: 750; letter-spacing: .12em; text-transform: uppercase; }
    .form-hero h1 { margin: 0; font-size: clamp(1.8rem, 4vw, 2.55rem); letter-spacing: -.045em; }
    .form-hero p:last-child { max-width: 42rem; margin: .7rem 0 0; color: var(--text-muted); line-height: 1.6; }
    .form-hero-mark { display:grid; flex:none; width:72px; height:72px; place-items:center; border:1px solid var(--border); border-radius:22px; background:var(--bg); color:var(--accent); }
    .form-hero-mark svg { width:34px; height:34px; }
    .form-tabs { display:flex; flex-wrap:wrap; gap:.55rem; margin-bottom:1.15rem; padding:.35rem; border:1px solid var(--border); border-radius:13px; background:var(--surface); }
    .monthly-tabs { margin:.25rem 0 1rem; }
    .form-tab { display:flex; align-items:center; gap:.5rem; padding:.75rem 1rem; border:0; border-radius:9px; background:transparent; color:var(--text-muted); font:inherit; font-size:.9rem; font-weight:700; cursor:pointer; }
    .form-tab[aria-selected="true"] { background:var(--accent); color:#fff; box-shadow:0 4px 12px color-mix(in srgb, var(--accent) 25%, transparent); }
    .form-tab svg { width:18px; height:18px; }
    .form-panel[hidden] { display:none; }
    .form-section-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:1rem; margin:1.4rem 0 .85rem; }
    .form-section-heading h2 { margin:0; font-size:1.18rem; letter-spacing:-.025em; }
    .form-section-heading p { margin:.3rem 0 0; color:var(--text-muted); font-size:.88rem; }
    .generator-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.9rem; }
    .generator-grid.monthly { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .generator-card {
        display:flex;
        min-height:156px;
        flex-direction:column;
        align-items:flex-start;
        padding:1.15rem;
        border:1px solid var(--border);
        border-radius:15px;
        background:var(--surface);
        box-shadow:0 4px 16px rgba(15,23,42,.035);
        transition:transform .17s ease, border-color .17s ease, box-shadow .17s ease;
    }
    a.generator-card { color:inherit; text-decoration:none; }
    .generator-card:hover { transform:translateY(-2px); border-color:color-mix(in srgb, var(--accent) 45%, var(--border)); box-shadow:0 12px 26px rgba(15,23,42,.08); }
    .generator-card-top { display:flex; align-items:center; gap:.8rem; width:100%; }
    .generator-icon { display:grid; flex:none; width:42px; height:42px; place-items:center; border-radius:13px; background:color-mix(in srgb, var(--accent) 11%, var(--surface)); color:var(--accent); }
    .generator-icon svg { width:21px; height:21px; }
    .generator-card h3 { margin:0; font-size:.98rem; }
    .generator-card p { margin:.8rem 0 0; color:var(--text-muted); font-size:.84rem; line-height:1.5; }
    .generator-tag { margin-top:auto; padding-top:.8rem; color:var(--accent); font-size:.7rem; font-weight:750; letter-spacing:.08em; text-transform:uppercase; }
    .monthly-subheading { grid-column:1/-1; margin:.4rem 0 -.3rem; color:var(--text-muted); font-size:.77rem; font-weight:750; letter-spacing:.1em; text-transform:uppercase; }
    .notes-layout { display:grid; grid-template-columns:minmax(250px,340px) minmax(0,1fr); height:clamp(460px,calc(100vh - 260px),680px); min-height:460px; overflow:hidden; border:1px solid var(--border); border-radius:16px; background:var(--surface); box-shadow:0 8px 24px rgba(15,23,42,.05); }
    .notes-sidebar { display:flex; min-height:0; flex-direction:column; border-right:1px solid var(--border); }
    .notes-sidebar-head { padding:1rem; border-bottom:1px solid var(--border); }
    .notes-sidebar-head h2 { margin:0 0 .75rem; font-size:1rem; }
    .notes-section-meta { display:flex; align-items:center; gap:.55rem; flex-wrap:wrap; }
    .notes-count-pill { display:inline-flex; align-items:center; min-height:1.5rem; padding:.1rem .55rem; border:1px solid var(--border); border-radius:999px; background:var(--bg); color:var(--text-muted); font-size:.73rem; font-weight:700; white-space:nowrap; }
    .notes-search { width:100%; padding:.7rem .8rem; border:1px solid var(--border); border-radius:9px; background:var(--bg); color:var(--text); font:inherit; }
    .notes-records { flex:1; min-height:0; overflow-y:auto; }
    .notes-record { display:block; width:100%; padding:.85rem 1rem; border:0; border-bottom:1px solid var(--border); background:transparent; color:var(--text); text-align:left; cursor:pointer; }
    .notes-record:hover,.notes-record.selected { background:color-mix(in srgb,var(--accent) 8%,var(--surface)); }
    .notes-record strong,.notes-record span { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .notes-record strong { font-size:.88rem; }
    .notes-record span { margin-top:.25rem; color:var(--text-muted); font-size:.77rem; }
    .notes-record .notes-record-meta { display:flex; align-items:center; justify-content:space-between; gap:.4rem; }
    .notes-record .notes-record-meta span:last-child { flex:none; color:var(--accent); font-weight:700; }
    .notes-record-pagination { display:flex; flex:none; align-items:center; justify-content:space-between; gap:.4rem; padding:.65rem .75rem; border-top:1px solid var(--border); }
    .notes-record-pagination[hidden] { display:none; }
    .notes-record-pagination button { padding:.4rem .55rem; border:1px solid var(--border); border-radius:7px; background:var(--surface); color:var(--text); font:inherit; font-size:.75rem; font-weight:700; cursor:pointer; }
    .notes-record-pagination button:disabled { opacity:.45; cursor:not-allowed; }
    .notes-record-page-status { color:var(--text-muted); font-size:.72rem; white-space:nowrap; }
    .notes-editor { display:flex; min-width:0; min-height:0; flex-direction:column; }
    .notes-editor-head { padding:1rem 1.2rem; border-bottom:1px solid var(--border); }
    .notes-editor-head-row { display:flex; align-items:flex-start; justify-content:space-between; gap:.8rem; }
    .notes-editor-head h2 { margin:0; font-size:1.08rem; }
    .notes-editor-head p { margin:.28rem 0 0; color:var(--text-muted); font-size:.82rem; }
    .notes-list { display:grid; align-content:start; gap:.65rem; flex:1; min-height:180px; overflow-y:auto; padding:1rem 1.2rem; background:var(--bg); }
    .notes-pagination { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.65rem 1.2rem; border-top:1px solid var(--border); background:var(--surface); }
    .notes-pagination[hidden] { display:none; }
    .notes-pagination button { padding:.42rem .7rem; border:1px solid var(--border); border-radius:7px; background:var(--surface); color:var(--text); font:inherit; font-size:.78rem; font-weight:700; cursor:pointer; }
    .notes-pagination button:disabled { opacity:.45; cursor:not-allowed; }
    .notes-page-status { color:var(--text-muted); font-size:.78rem; }
    .note-entry { display:flex; align-items:flex-start; gap:.85rem; padding:.85rem .9rem; border:1px solid var(--border); border-radius:12px; background:var(--surface); }
    .note-date { flex:none; min-width:90px; color:var(--accent); font-size:.77rem; font-weight:750; }
    .note-content { flex:1; min-width:0; color:var(--text); font-size:.88rem; line-height:1.55; overflow-wrap:anywhere; }
    .note-delete { flex:none; padding:.35rem .55rem; border:1px solid color-mix(in srgb, var(--danger) 25%, var(--border)); border-radius:7px; background:transparent; color:var(--danger); font:inherit; font-size:.75rem; font-weight:700; cursor:pointer; }
    .note-delete:hover { background:color-mix(in srgb, var(--danger) 8%, var(--surface)); }
    .notes-empty { grid-column:1/-1; align-self:center; justify-self:center; max-width:24rem; padding:1.5rem; color:var(--text-muted); text-align:center; line-height:1.6; }
    .notes-compose { padding:1rem 1.2rem; border-top:1px solid var(--border); }
    .notes-compose label { display:block; margin-bottom:.45rem; font-size:.83rem; font-weight:700; }
    .notes-compose-row { display:flex; align-items:flex-end; gap:.65rem; }
    .notes-compose textarea { flex:1; min-height:74px; max-height:180px; resize:vertical; padding:.7rem .8rem; border:1px solid var(--border); border-radius:9px; background:var(--bg); color:var(--text); font:inherit; }
    .notes-compose button { padding:.74rem 1rem; border:0; border-radius:9px; background:var(--accent); color:white; font:inherit; font-size:.85rem; font-weight:750; cursor:pointer; }
    .notes-compose button:disabled { opacity:.55; cursor:wait; }
    .notes-hint { margin:.55rem 0 0; color:var(--text-muted); font-size:.75rem; }
    .notes-compose-footer { display:flex; align-items:center; justify-content:space-between; gap:.75rem; }
    .notes-character-count { color:var(--text-muted); font-size:.72rem; font-variant-numeric:tabular-nums; white-space:nowrap; }
    .notes-alert { margin:0 1.2rem .6rem; color:var(--danger); font-size:.82rem; }
    .notes-alert:empty { display:none; }
    @media(max-width:850px) { .generator-grid.monthly { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(max-width:680px) {
        .form-hero-mark { display:none; }
        .notes-layout { height:min(800px,calc(100vh - 230px)); min-height:500px; grid-template-columns:1fr; grid-template-rows:minmax(170px,30%) minmax(0,1fr); }
        .notes-sidebar { border-right:0; border-bottom:1px solid var(--border); }
        .generator-grid,.generator-grid.monthly { grid-template-columns:1fr; }
    }
</style>

<div class="form-hub">
    <header class="form-hero">
        <div>
            <p class="form-eyebrow">Records workspace</p>
            <h1>Form Generator</h1>
            <p>Choose a reporting workflow or review and update the notes recorded for a member.</p>
        </div>
        <span class="form-hero-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M7 3h7l5 5v12a1 1 0 0 1-1 1H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M8 13h8M8 17h8"/>
            </svg>
        </span>
    </header>

    <nav class="form-tabs" role="tablist" aria-label="Form Generator sections">
        <button class="form-tab" id="weekly-tab" type="button" role="tab" aria-selected="{{ $activePanel === 'weekly' ? 'true' : 'false' }}" aria-controls="weekly-panel" data-panel="weekly">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v4m8-4v4M4 9h16M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1Z"/></svg>
            Weekly
        </button>
        <button class="form-tab" id="monthly-tab" type="button" role="tab" aria-selected="{{ $activePanel === 'monthly' ? 'true' : 'false' }}" aria-controls="monthly-panel" data-panel="monthly">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v4m8-4v4M4 9h16M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1Z"/><path d="M8 13h3m2 0h3m-8 4h3m2 0h3"/></svg>
            Monthly
        </button>
        <button class="form-tab" id="notes-tab" type="button" role="tab" aria-selected="{{ $activePanel === 'notes' ? 'true' : 'false' }}" aria-controls="notes-panel" data-panel="notes">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12a2 2 0 0 1 2 2v14l-4-2-4 2-4-2-4 2V5a2 2 0 0 1 2-2Z"/><path d="M8 8h8m-8 4h8m-8 4h4"/></svg>
            Notes Checker
        </button>
    </nav>

    <section class="form-panel" id="weekly-panel" role="tabpanel" aria-labelledby="weekly-tab" @if ($activePanel !== 'weekly') hidden @endif>
        <div class="form-section-heading">
            <div><h2>Weekly generators</h2><p>Weekly reporting forms and summaries.</p></div>
        </div>
        <div class="generator-grid">
            <a class="generator-card" href="{{ route('forms.weekly.r4-02') }}">
                <div class="generator-card-top"><span class="generator-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l5 5v13H5V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M8 13h8M8 17h8"/></svg></span><h3>R4-02 Generator</h3></div>
                <p>Filter R4-01 members, assign configured Hakbang codes, and save a printable R4-02.</p><span class="generator-tag">Open R4-02 Maker</span>
            </a>
            <a class="generator-card" href="{{ route('forms.weekly.r4-03') }}">
                <div class="generator-card-top"><span class="generator-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l5 5v13H5V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M8 13h8M8 17h5"/></svg></span><h3>R4-03 Generator</h3></div>
                <p>Summarize saved R4-02 forms, grouped only by Puroks with generated reports.</p><span class="generator-tag">Open R4-03 Maker</span>
            </a>
        </section>
    </section>

    <section class="form-panel" id="monthly-panel" role="tabpanel" aria-labelledby="monthly-tab" @if ($activePanel !== 'monthly') hidden @endif>
        <div class="form-section-heading">
            <div><h2>Monthly generators</h2><p>Monthly reporting forms and summaries.</p></div>
        </div>
        <nav class="form-tabs monthly-tabs" role="tablist" aria-label="Monthly generator sections">
            <button class="form-tab monthly-tab" id="deliberation-tab" type="button" role="tab" aria-selected="{{ $activeMonthlyPanel === 'deliberation' ? 'true' : 'false' }}" aria-controls="deliberation-panel" data-monthly-panel="deliberation">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h4"/><path d="m16 16 1.5 1.5L21 14"/></svg>
                Deliberation
            </button>
            <button class="form-tab monthly-tab" id="monthly-forms-tab" type="button" role="tab" aria-selected="{{ $activeMonthlyPanel === 'monthly-forms' ? 'true' : 'false' }}" aria-controls="monthly-forms-panel" data-monthly-panel="monthly-forms">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l5 5v13H5V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M8 13h8M8 17h8"/></svg>
                Monthly Forms
            </button>
        </nav>
        <section class="monthly-subpanel" id="deliberation-panel" role="tabpanel" aria-labelledby="deliberation-tab" @if ($activeMonthlyPanel !== 'deliberation') hidden @endif>
            <div class="generator-grid monthly">
                <a class="generator-card" href="{{ route('forms.monthly.deliberation') }}"><div class="generator-card-top"><span class="generator-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 5v14m-7-7h14"/></svg></span><h3>Add</h3></div><p>Add members to the monthly deliberation workflow.</p><span class="generator-tag">Open Deliberation Form</span></a>
                <a class="generator-card" href="{{ route('forms.monthly.modify') }}"><div class="generator-card-top"><span class="generator-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4l10.5-10.5a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 7.5 4 4"/></svg></span><h3>Modify</h3></div><p>Move a member to another Purok or Grupo.</p><span class="generator-tag">Open Modify Form</span></a>
                <a class="generator-card" href="{{ route('forms.monthly.deliberation', ['mode' => 'subtract']) }}"><div class="generator-card-top"><span class="generator-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M5 12h14"/></svg></span><h3>Subtract</h3></div><p>Search for an existing member and mark an eligible record inactive.</p><span class="generator-tag">Open Subtract Form</span></a>
            </div>
        </section>
        <section class="monthly-subpanel" id="monthly-forms-panel" role="tabpanel" aria-labelledby="monthly-forms-tab" @if ($activeMonthlyPanel !== 'monthly-forms') hidden @endif>
            <div class="generator-grid monthly">
                <a class="generator-card" href="{{ route('forms.monthly.generators') }}"><div class="generator-card-top"><span class="generator-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5m0 14h16M8 15l3-4 3 2 5-6"/></svg></span><h3>Resumen Generator &amp; R4-01 Generator</h3></div><p>Open both monthly generators together, with Resumen first and R4-01 below.</p><span class="generator-tag">Open Monthly Generators</span></a>
                <a class="generator-card" href="{{ route('forms.monthly.r4-01.snapshots') }}"><div class="generator-card-top"><span class="generator-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h8M8 17h5"/></svg></span><h3>Resumen &amp; R4-01 Snapshots</h3></div><p>Browse and re-download saved Resumen and R4-01 reports.</p><span class="generator-tag">Open Report Snapshots</span></a>
            </div>
        </section>
    </section>

    <section class="form-panel" id="notes-panel" role="tabpanel" aria-labelledby="notes-tab" @if ($activePanel !== 'notes') hidden @endif>
        <div class="form-section-heading">
            <div><h2>Notes Checker</h2><p>Find a record, review its dated log entries, and safely add or remove notes.</p></div>
        </div>
        <div class="notes-layout" id="notesApp"
            data-list-url="{{ route('forms.notes') }}"
            data-add-url="{{ route('forms.notes.append', ['record' => '__ID__']) }}"
            data-delete-url="{{ route('forms.notes.delete', ['record' => '__ID__', 'noteIndex' => '__INDEX__']) }}"
            data-csrf="{{ csrf_token() }}">
            <aside class="notes-sidebar">
                <div class="notes-sidebar-head">
                    <div class="notes-section-meta">
                        <h2>Records with notes</h2>
                        <span class="notes-count-pill" id="notesRecordCount" aria-live="polite">Loading</span>
                    </div>
                    <input class="notes-search" id="notesSearch" type="search" placeholder="Search name or registry…" aria-label="Search records with notes">
                    <p class="notes-hint">Search returns up to 100 matching records.</p>
                </div>
                <div class="notes-records" id="notesRecords" aria-live="polite"><p class="notes-empty">Loading records…</p></div>
                <nav class="notes-record-pagination" id="notesRecordPagination" aria-label="Record pages" hidden>
                    <button type="button" id="previousRecordsPage">Previous</button>
                    <span class="notes-record-page-status" id="recordsPageStatus" aria-live="polite"></span>
                    <button type="button" id="nextRecordsPage">Next</button>
                </nav>
            </aside>
            <section class="notes-editor" aria-label="Selected record notes">
                <header class="notes-editor-head">
                    <div class="notes-editor-head-row">
                        <div><h2 id="selectedRecordName">Select a record</h2><p id="selectedRecordRegistry">Choose a record to review its log notes.</p></div>
                        <span class="notes-count-pill" id="selectedNoteCount">No record selected</span>
                    </div>
                </header>
                <div class="notes-list" id="recordNotes" aria-live="polite"><p class="notes-empty">Select a record from the list to review its notes.</p></div>
                <nav class="notes-pagination" id="notesPagination" aria-label="Notes pages" hidden>
                    <button type="button" id="previousNotesPage">Previous</button>
                    <span class="notes-page-status" id="notesPageStatus" aria-live="polite"></span>
                    <button type="button" id="nextNotesPage">Next</button>
                </nav>
                <div class="notes-alert" id="notesAlert" role="alert"></div>
                <form class="notes-compose" id="noteForm">
                    <label for="newNote">Append a note</label>
                    <div class="notes-compose-row"><textarea id="newNote" maxlength="2000" placeholder="For example: Should be included in deliberation…" disabled></textarea><button id="saveNote" type="submit" disabled>Add note</button></div>
                    <div class="notes-compose-footer"><p class="notes-hint">Today’s date is added automatically to the note.</p><span class="notes-character-count" id="newNoteCount">0 / 2000</span></div>
                </form>
            </section>
        </div>
    </section>
</div>
@include('forms.monthly-session-widget')
@endsection

@push('scripts')
<script>
(() => {
    const tabs = [...document.querySelectorAll('.form-tabs[aria-label="Form Generator sections"] > .form-tab')];
    const monthlyTabs = [...document.querySelectorAll('.monthly-tab')];
    const monthlyPanels = [...document.querySelectorAll('.monthly-subpanel')];
    const panels = [...document.querySelectorAll('.form-panel')];
    tabs.forEach(tab => tab.addEventListener('click', () => {
        tabs.forEach(item => item.setAttribute('aria-selected', String(item === tab)));
        panels.forEach(panel => { panel.hidden = panel.id !== `${tab.dataset.panel}-panel`; });
        if (tab.dataset.panel === 'notes') loadRecords().catch(showError);
    }));
    monthlyTabs.forEach(tab => tab.addEventListener('click', () => {
        monthlyTabs.forEach(item => item.setAttribute('aria-selected', String(item === tab)));
        monthlyPanels.forEach(panel => { panel.hidden = panel.id !== `${tab.dataset.monthlyPanel}-panel`; });
    }));

    const app = document.getElementById('notesApp');
    const recordsList = document.getElementById('notesRecords');
    const recordsPagination = document.getElementById('notesRecordPagination');
    const previousRecordsPageButton = document.getElementById('previousRecordsPage');
    const nextRecordsPageButton = document.getElementById('nextRecordsPage');
    const recordsPageStatus = document.getElementById('recordsPageStatus');
    const notesList = document.getElementById('recordNotes');
    const search = document.getElementById('notesSearch');
    const form = document.getElementById('noteForm');
    const input = document.getElementById('newNote');
    const saveButton = document.getElementById('saveNote');
    const pagination = document.getElementById('notesPagination');
    const previousPageButton = document.getElementById('previousNotesPage');
    const nextPageButton = document.getElementById('nextNotesPage');
    const pageStatus = document.getElementById('notesPageStatus');
    const alert = document.getElementById('notesAlert');
    const selectedName = document.getElementById('selectedRecordName');
    const selectedRegistry = document.getElementById('selectedRecordRegistry');
    const recordCount = document.getElementById('notesRecordCount');
    const selectedNoteCount = document.getElementById('selectedNoteCount');
    const noteCharacterCount = document.getElementById('newNoteCount');
    let records = [];
    let selectedId = null;
    let searchTimer = null;
    let notesPage = 0;
    const notesPerPage = 5;
    let recordsPage = 0;
    const recordsPerPage = 10;
    let activeRecordsRequest = null;
    let recordsRequestSequence = 0;

    function showError(error) {
        if (error?.name === 'AbortError') return;
        alert.textContent = error?.message || String(error);
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': app.dataset.csrf,
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                ...options.headers,
            },
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
            throw new Error(firstError || payload.message || 'Could not complete the notes request.');
        }
        return payload;
    }

    function renderNotes(record) {
        if (!record) return;
        if (Number(record.id) !== selectedId) notesPage = 0;
        selectedId = Number(record.id);
        selectedName.textContent = record.name;
        selectedRegistry.textContent = record.registry ? `Registry: ${record.registry}` : 'No registry number';
        selectedNoteCount.textContent = `${record.notes.length} ${record.notes.length === 1 ? 'note' : 'notes'}`;
        input.disabled = false;
        saveButton.disabled = false;
        recordsList.querySelectorAll('.notes-record').forEach(button => {
            const selected = Number(button.dataset.id) === selectedId;
            button.classList.toggle('selected', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        notesList.replaceChildren();
        if (!record.notes.length) {
            pagination.hidden = true;
            const empty = document.createElement('p');
            empty.className = 'notes-empty';
            empty.textContent = 'This record has no notes yet. Add one below.';
            notesList.append(empty);
            return;
        }

        const pageCount = Math.ceil(record.notes.length / notesPerPage);
        notesPage = Math.min(notesPage, pageCount - 1);
        pagination.hidden = pageCount <= 1;
        previousPageButton.disabled = notesPage === 0;
        nextPageButton.disabled = notesPage >= pageCount - 1;
        pageStatus.textContent = `Page ${notesPage + 1} of ${pageCount}`;
        const startIndex = notesPage * notesPerPage;
        record.notes.slice(startIndex, startIndex + notesPerPage).forEach((note, offset) => {
            const index = startIndex + offset;
            const row = document.createElement('article');
            row.className = 'note-entry';
            const match = note.match(/^(\d{2}\/\d{2}\/\d{4}:)\s*(.*)$/s);
            const date = document.createElement('span');
            date.className = 'note-date';
            date.textContent = match?.[1] || 'Existing note';
            const body = document.createElement('span');
            body.className = 'note-content';
            body.textContent = match?.[2] || note;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'note-delete';
            remove.textContent = 'Delete';
            remove.addEventListener('click', async () => {
                if (!window.confirm('Delete this note from the selected record?')) return;
                remove.disabled = true;
                try {
                    const url = app.dataset.deleteUrl.replace('__ID__', record.id).replace('__INDEX__', index);
                    const updated = await request(url, { method: 'DELETE' });
                    replaceRecord(updated);
                    renderNotes(updated);
                    alert.textContent = '';
                } catch (error) { showError(error); remove.disabled = false; }
            });
            row.append(date, body, remove);
            notesList.append(row);
        });
    }

    function renderRecords(items) {
        records = items;
        recordsList.replaceChildren();
        if (!items.length) {
            const empty = document.createElement('p');
            empty.className = 'notes-empty';
            empty.textContent = 'No records with notes found.';
            recordsList.append(empty);
            selectedId = null;
            selectedName.textContent = 'Select a record';
            selectedRegistry.textContent = 'Choose a record to review its log notes.';
            recordCount.textContent = '0 records';
            selectedNoteCount.textContent = 'No record selected';
            notesList.replaceChildren();
            recordsPagination.hidden = true;
            pagination.hidden = true;
            input.disabled = true;
            saveButton.disabled = true;
            return;
        }

        const pageCount = Math.ceil(items.length / recordsPerPage);
        recordCount.textContent = `${items.length} ${items.length === 1 ? 'record' : 'records'}`;
        recordsPage = Math.min(recordsPage, pageCount - 1);
        recordsPagination.hidden = pageCount <= 1;
        previousRecordsPageButton.disabled = recordsPage === 0;
        nextRecordsPageButton.disabled = recordsPage >= pageCount - 1;
        recordsPageStatus.textContent = `Page ${recordsPage + 1} of ${pageCount}`;
        const startIndex = recordsPage * recordsPerPage;
        const visibleRecords = items.slice(startIndex, startIndex + recordsPerPage);

        visibleRecords.forEach(record => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `notes-record${Number(record.id) === selectedId ? ' selected' : ''}`;
            button.dataset.id = record.id;
            button.setAttribute('aria-pressed', String(Number(record.id) === selectedId));
            const name = document.createElement('strong');
            name.textContent = record.name;
            const detail = document.createElement('span');
            detail.className = 'notes-record-meta';
            const registry = document.createElement('span');
            registry.textContent = record.registry || 'No registry number';
            const count = document.createElement('span');
            count.textContent = `${record.notes.length} ${record.notes.length === 1 ? 'note' : 'notes'}`;
            detail.append(registry, count);
            button.append(name, detail);
            button.addEventListener('click', () => {
                if (Number(record.id) !== selectedId && input.value.trim()) {
                    alert.textContent = 'Save or clear the unsaved note before switching records.';
                    input.focus();
                    return;
                }
                if (Number(record.id) !== selectedId) notesPage = 0;
                renderNotes(record);
            });
            recordsList.append(button);
        });
        const selected = items.find(record => Number(record.id) === selectedId) || visibleRecords[0];
        renderNotes(selected);
    }

    function replaceRecord(updated) {
        records = records.flatMap(record => Number(record.id) === Number(updated.id)
            ? (updated.notes.length ? [updated] : [])
            : [record]);
        renderRecords(records);
    }

    async function loadRecords() {
        const url = `${app.dataset.listUrl}?q=${encodeURIComponent(search.value.trim())}`;
        activeRecordsRequest?.abort();
        const controller = new AbortController();
        activeRecordsRequest = controller;
        const sequence = ++recordsRequestSequence;
        recordsList.replaceChildren();
        const loading = document.createElement('p');
        loading.className = 'notes-empty';
        loading.textContent = 'Searching records…';
        recordsList.append(loading);
        try {
            const items = await request(url, { signal: controller.signal });
            if (sequence !== recordsRequestSequence) return;
            recordCount.textContent = `${items.length} ${items.length === 1 ? 'record' : 'records'}`;
            recordsPage = 0;
            renderRecords(items);
            alert.textContent = '';
        } catch (error) {
            if (error?.name !== 'AbortError') throw error;
        }
    }

    previousRecordsPageButton.addEventListener('click', () => {
        if (recordsPage > 0) {
            recordsPage--;
            renderRecords(records);
        }
    });

    nextRecordsPageButton.addEventListener('click', () => {
        if ((recordsPage + 1) * recordsPerPage < records.length) {
            recordsPage++;
            renderRecords(records);
        }
    });

    previousPageButton.addEventListener('click', () => {
        if (notesPage > 0) {
            notesPage--;
            const selected = records.find(record => Number(record.id) === selectedId);
            if (selected) renderNotes(selected);
        }
    });

    nextPageButton.addEventListener('click', () => {
        const selected = records.find(record => Number(record.id) === selectedId);
        if (selected && (notesPage + 1) * notesPerPage < selected.notes.length) {
            notesPage++;
            renderNotes(selected);
        }
    });

    search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
            if (input.value.trim()) {
                alert.textContent = 'Save or clear the unsaved note before searching for another record.';
                return;
            }
            loadRecords().catch(showError);
        }, 250);
    });
    input.addEventListener('input', () => {
        noteCharacterCount.textContent = `${input.value.length} / 2000`;
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!selectedId || !input.value.trim()) return;
        saveButton.disabled = true;
        try {
            const url = app.dataset.addUrl.replace('__ID__', selectedId);
            const updated = await request(url, { method: 'POST', body: JSON.stringify({ note: input.value }) });
            input.value = '';
            noteCharacterCount.textContent = '0 / 2000';
            alert.textContent = '';
            notesPage = Math.floor((updated.notes.length - 1) / notesPerPage);
            replaceRecord(updated);
            renderNotes(updated);
        } catch (error) { showError(error); }
        finally { saveButton.disabled = false; }
    });

})();
</script>
@endpush
