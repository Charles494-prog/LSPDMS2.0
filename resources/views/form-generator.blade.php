@extends('layouts.app')

@section('title', 'Form Generator')

@section('content')
@php
    $icon = fn (string $paths): string => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths.'</svg>';
    $doc = '<path d="M7 3h7l5 5v13H5V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M8 13h8M8 17h8"/>';

    $weeklyCards = [
        ['url' => route('forms.weekly.r4-02'), 'icon' => $doc, 'title' => 'R4-02 Generator', 'text' => 'Filter R4-01 members, assign configured Hakbang codes, and save a printable R4-02.', 'tag' => 'Open R4-02 Maker'],
        ['url' => route('forms.weekly.r4-03'), 'icon' => '<path d="M7 3h7l5 5v13H5V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M8 13h8M8 17h5"/>', 'title' => 'R4-03 Generator', 'text' => 'Summarize saved R4-02 forms, grouped only by Puroks with generated reports.', 'tag' => 'Open R4-03 Maker'],
    ];
    $deliberationCards = [
        ['url' => route('forms.monthly.deliberation'), 'icon' => '<path d="M12 5v14m-7-7h14"/>', 'title' => 'Add', 'text' => 'Add members to the monthly deliberation workflow.', 'tag' => 'Open Deliberation Form'],
        ['url' => route('forms.monthly.modify'), 'icon' => '<path d="M4 20h4l10.5-10.5a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 7.5 4 4"/>', 'title' => 'Modify', 'text' => 'Move a member to another Purok or Grupo.', 'tag' => 'Open Modify Form'],
        ['url' => route('forms.monthly.deliberation', ['mode' => 'subtract']), 'icon' => '<path d="M5 12h14"/>', 'title' => 'Subtract', 'text' => 'Search for an existing member and mark an eligible record inactive.', 'tag' => 'Open Subtract Form'],
    ];
    $monthlyFormCards = [
        ['url' => route('forms.monthly.generators'), 'icon' => '<path d="M4 19V5m0 14h16M8 15l3-4 3 2 5-6"/>', 'title' => 'Resumen & R4-01 Generator', 'text' => 'Open both monthly generators together, with Resumen first and R4-01 below.', 'tag' => 'Open Monthly Generators'],
        ['url' => route('forms.monthly.r4-01.snapshots'), 'icon' => '<path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h8M8 17h5"/>', 'title' => 'Resumen & R4-01 Snapshots', 'text' => 'Browse and re-download saved Resumen and R4-01 reports.', 'tag' => 'Open Report Snapshots'],
    ];
@endphp
<style>
    /* Flat and monochrome. Inherits --bg / --surface / --border / --text / --text-muted from the layout,
       so it follows the light/dark switch. Only --danger is used as a color (delete + errors). */
    .form-hub { max-width: 1180px; margin: 0 auto; color: var(--text); --r: 8px; }
    .form-hub h1, .form-hub h2, .form-hub h3, .form-hub p { margin: 0; }

    /* Header: title on the left, section switcher on the right */
    .form-hero { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem 1.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .form-hero h1 { font-size: clamp(1.6rem, 3.2vw, 2.1rem); font-weight: 650; letter-spacing: -.035em; line-height: 1.05; }
    .form-hero p { max-width: 38rem; margin-top: .4rem; color: var(--text-muted); font-size: .9rem; line-height: 1.55; }

    /* Switchers: segmented controls */
    .form-tabs { display: inline-flex; flex-wrap: wrap; gap: 2px; max-width: 100%; padding: 3px; border: 1px solid var(--border); border-radius: var(--r); background: var(--bg); }
    .form-tab { display: flex; align-items: center; gap: .5rem; padding: .5rem .9rem; border: 0; border-radius: 6px; background: transparent; color: var(--text-muted); font: inherit; font-size: .85rem; font-weight: 600; cursor: pointer; transition: background .15s ease, color .15s ease; }
    .form-tab:hover { color: var(--text); }
    .form-tab[aria-selected="true"] { background: var(--text); color: var(--bg); }
    .form-tab svg { width: 16px; height: 16px; flex: none; }
    .monthly-tabs { margin-bottom: 1rem; }
    .monthly-tabs .form-tab { padding: .4rem .8rem; font-size: .8rem; }
    .form-tab:focus-visible, .form-hub a:focus-visible, .notes-record:focus-visible,
    .notes-search:focus-visible, .notes-compose textarea:focus-visible, .notes-compose button:focus-visible,
    .notes-pagination button:focus-visible, .notes-record-pagination button:focus-visible, .note-delete:focus-visible { outline: 2px solid var(--text); outline-offset: 2px; }
    .form-panel[hidden], .monthly-subpanel[hidden] { display: none; }

    .form-section-heading { margin-bottom: 1rem; }
    .form-section-heading h2 { font-size: 1rem; font-weight: 650; letter-spacing: -.015em; }
    .form-section-heading p { margin-top: .25rem; color: var(--text-muted); font-size: .85rem; }

    /* Generators: tiles in a grid */
    .generator-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: .75rem; }
    .generator-card { display: flex; flex-direction: column; gap: .35rem; min-height: 168px; padding: 1.1rem 1.15rem; border: 1px solid var(--border); border-radius: var(--r); background: var(--surface); color: inherit; text-decoration: none; transition: border-color .15s ease; }
    .generator-card:hover { border-color: var(--text); }
    .generator-card-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: .75rem; }
    .generator-icon { display: grid; width: 36px; height: 36px; place-items: center; border: 1px solid var(--border); border-radius: 6px; color: var(--text); }
    .generator-icon svg { width: 18px; height: 18px; }
    .generator-go { width: 16px; height: 16px; color: var(--text-muted); transition: color .15s ease, transform .15s ease; }
    .generator-card:hover .generator-go { color: var(--text); transform: translate(2px, -2px); }
    .generator-card h3 { font-size: .95rem; font-weight: 650; letter-spacing: -.01em; }
    .generator-card p { color: var(--text-muted); font-size: .83rem; line-height: 1.5; }
    .generator-tag { margin-top: auto; padding-top: .75rem; color: var(--text); font-size: .8rem; font-weight: 600; }

    /* Notes checker */
    .notes-layout { display: grid; grid-template-columns: minmax(250px, 320px) minmax(0, 1fr); height: clamp(480px, calc(100vh - 250px), 700px); min-height: 480px; overflow: hidden; border: 1px solid var(--border); border-radius: var(--r); background: var(--surface); }
    .notes-sidebar { display: flex; min-height: 0; flex-direction: column; border-right: 1px solid var(--border); background: var(--bg); }
    .notes-sidebar-head { display: grid; gap: .6rem; padding: 1rem; border-bottom: 1px solid var(--border); }
    .notes-sidebar-head h2 { font-size: .92rem; font-weight: 650; }
    .notes-section-meta { display: flex; align-items: center; justify-content: space-between; gap: .55rem; flex-wrap: wrap; }
    .notes-count-pill { display: inline-flex; align-items: center; min-height: 1.5rem; padding: .1rem .6rem; border: 1px solid var(--border); border-radius: 99px; background: transparent; color: var(--text-muted); font-size: .73rem; font-weight: 600; white-space: nowrap; }
    .notes-search { width: 100%; padding: .6rem .75rem; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); color: var(--text); font: inherit; font-size: .88rem; -webkit-appearance: none; appearance: none; }
    .notes-search:hover { border-color: var(--text-muted); }
    .notes-records { flex: 1; min-height: 0; overflow-y: auto; }
    .notes-record { display: block; width: 100%; padding: .8rem 1rem .8rem .9rem; border: 0; border-bottom: 1px solid var(--border); border-left: 2px solid transparent; background: transparent; color: var(--text); text-align: left; cursor: pointer; }
    .notes-record:hover { background: var(--surface); }
    .notes-record.selected { border-left-color: var(--text); background: var(--surface); }
    .notes-record strong, .notes-record span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .notes-record strong { font-size: .87rem; font-weight: 600; }
    .notes-record span { margin-top: .25rem; color: var(--text-muted); font-size: .76rem; }
    .notes-record .notes-record-meta { display: flex; align-items: center; justify-content: space-between; gap: .4rem; }
    .notes-record .notes-record-meta span:last-child { flex: none; color: var(--text); font-weight: 650; }
    .notes-record-pagination { display: flex; flex: none; align-items: center; justify-content: space-between; gap: .4rem; padding: .6rem .75rem; border-top: 1px solid var(--border); }
    .notes-record-pagination[hidden] { display: none; }
    .notes-record-pagination button, .notes-pagination button { padding: .4rem .7rem; border: 1px solid var(--border); border-radius: 6px; background: transparent; color: var(--text); font: inherit; font-size: .78rem; font-weight: 600; cursor: pointer; }
    .notes-record-pagination button:hover:not(:disabled), .notes-pagination button:hover:not(:disabled) { border-color: var(--text); }
    .notes-record-pagination button:disabled, .notes-pagination button:disabled { opacity: .4; cursor: not-allowed; }
    .notes-record-page-status { color: var(--text-muted); font-size: .72rem; white-space: nowrap; }

    /* Editor: header, then the compose box, then the dated notes */
    .notes-editor { display: flex; min-width: 0; min-height: 0; flex-direction: column; }
    .notes-editor-head { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
    .notes-editor-head-row { display: flex; align-items: flex-start; justify-content: space-between; gap: .8rem; }
    .notes-editor-head h2 { font-size: 1.05rem; font-weight: 650; letter-spacing: -.015em; }
    .notes-editor-head p { margin-top: .25rem; color: var(--text-muted); font-size: .82rem; }
    .notes-alert { margin: 0; padding: .6rem 1.25rem 0; color: var(--danger); font-size: .82rem; }
    .notes-alert:empty { display: none; }
    .notes-compose { padding: .9rem 1.25rem; border-bottom: 1px solid var(--border); }
    .notes-compose label { display: block; margin-bottom: .4rem; font-size: .8rem; font-weight: 600; }
    .notes-compose-row { display: flex; align-items: flex-end; gap: .65rem; }
    .notes-compose textarea { flex: 1; min-height: 64px; max-height: 160px; resize: vertical; padding: .65rem .75rem; border: 1px solid var(--border); border-radius: 6px; background: var(--bg); color: var(--text); font: inherit; font-size: .88rem; }
    .notes-compose textarea:hover:not(:disabled) { border-color: var(--text-muted); }
    .notes-compose textarea:disabled { opacity: .5; }
    .notes-compose button { padding: .65rem 1rem; border: 1px solid var(--text); border-radius: 6px; background: var(--text); color: var(--bg); font: inherit; font-size: .85rem; font-weight: 600; cursor: pointer; }
    .notes-compose button:hover:not(:disabled) { opacity: .85; }
    .notes-compose button:disabled { opacity: .4; cursor: not-allowed; }
    .notes-hint { margin: 0; color: var(--text-muted); font-size: .75rem; }
    .notes-compose-footer { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-top: .5rem; }
    .notes-character-count { color: var(--text-muted); font-size: .72rem; font-variant-numeric: tabular-nums; white-space: nowrap; }

    .notes-list { display: grid; align-content: start; flex: 1; min-height: 160px; overflow-y: auto; padding: 0 1.25rem; }
    .notes-pagination { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .6rem 1.25rem; border-top: 1px solid var(--border); }
    .notes-pagination[hidden] { display: none; }
    .notes-page-status { color: var(--text-muted); font-size: .78rem; }
    .note-entry { display: grid; grid-template-columns: 96px minmax(0, 1fr) auto; align-items: start; gap: .9rem; padding: .85rem 0; border-bottom: 1px solid var(--border); }
    .note-date { color: var(--text-muted); font-size: .76rem; font-weight: 650; font-variant-numeric: tabular-nums; }
    .note-content { min-width: 0; color: var(--text); font-size: .88rem; line-height: 1.55; overflow-wrap: anywhere; }
    .note-delete { padding: .3rem .55rem; border: 1px solid var(--border); border-radius: 6px; background: transparent; color: var(--danger); font: inherit; font-size: .75rem; font-weight: 600; cursor: pointer; }
    .note-delete:hover:not(:disabled) { border-color: var(--danger); }
    .note-delete:disabled { opacity: .5; cursor: not-allowed; }
    .notes-empty { align-self: center; justify-self: center; max-width: 24rem; padding: 1.5rem; color: var(--text-muted); font-size: .88rem; text-align: center; line-height: 1.6; }

    @media (prefers-reduced-motion: reduce) { .form-hub * { transition: none !important; } }
    @media (max-width: 680px) {
        .form-tabs { width: 100%; }
        .form-tab { flex: 1 1 auto; justify-content: center; padding: .5rem .6rem; }
        .generator-grid { grid-template-columns: 1fr; }
        .generator-card { min-height: 0; }
        .notes-layout { height: min(820px, calc(100vh - 200px)); min-height: 520px; grid-template-columns: 1fr; grid-template-rows: minmax(190px, 32%) minmax(0, 1fr); }
        .notes-sidebar { border-right: 0; border-bottom: 1px solid var(--border); }
        .note-entry { grid-template-columns: minmax(0, 1fr) auto; }
        .note-date { grid-column: 1 / -1; }
    }
</style>

<div class="form-hub">
    <header class="form-hero">
        <div>
            <h1>Form Generator</h1>
            <p>Choose a reporting workflow or review and update the notes recorded for a member.</p>
        </div>
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
    </header>

    <section class="form-panel" id="weekly-panel" role="tabpanel" aria-labelledby="weekly-tab" @if ($activePanel !== 'weekly') hidden @endif>
        <div class="form-section-heading">
            <h2>Weekly generators</h2>
            <p>Weekly reporting forms and summaries.</p>
        </div>
        <div class="generator-grid">
            @foreach ($weeklyCards as $card)
                <a class="generator-card" href="{{ $card['url'] }}">
                    <div class="generator-card-top">
                        <span class="generator-icon">{!! $icon($card['icon']) !!}</span>
                        <svg class="generator-go" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>
                    </div>
                    <h3>{{ $card['title'] }}</h3>
                    <p>{{ $card['text'] }}</p>
                    <span class="generator-tag">{{ $card['tag'] }}</span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="form-panel" id="monthly-panel" role="tabpanel" aria-labelledby="monthly-tab" @if ($activePanel !== 'monthly') hidden @endif>
        <div class="form-section-heading">
            <h2>Monthly generators</h2>
            <p>Monthly reporting forms and summaries.</p>
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
                @foreach ($deliberationCards as $card)
                    <a class="generator-card" href="{{ $card['url'] }}">
                        <div class="generator-card-top">
                            <span class="generator-icon">{!! $icon($card['icon']) !!}</span>
                            <svg class="generator-go" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>
                        </div>
                        <h3>{{ $card['title'] }}</h3>
                        <p>{{ $card['text'] }}</p>
                        <span class="generator-tag">{{ $card['tag'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
        <section class="monthly-subpanel" id="monthly-forms-panel" role="tabpanel" aria-labelledby="monthly-forms-tab" @if ($activeMonthlyPanel !== 'monthly-forms') hidden @endif>
            <div class="generator-grid monthly">
                @foreach ($monthlyFormCards as $card)
                    <a class="generator-card" href="{{ $card['url'] }}">
                        <div class="generator-card-top">
                            <span class="generator-icon">{!! $icon($card['icon']) !!}</span>
                            <svg class="generator-go" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>
                        </div>
                        <h3>{{ $card['title'] }}</h3>
                        <p>{{ $card['text'] }}</p>
                        <span class="generator-tag">{{ $card['tag'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    </section>

    <section class="form-panel" id="notes-panel" role="tabpanel" aria-labelledby="notes-tab" @if ($activePanel !== 'notes') hidden @endif>
        <div class="form-section-heading">
            <h2>Notes Checker</h2>
            <p>Find a record, review its dated log entries, and safely add or remove notes.</p>
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
                <div class="notes-alert" id="notesAlert" role="alert"></div>
                <form class="notes-compose" id="noteForm">
                    <label for="newNote">Append a note</label>
                    <div class="notes-compose-row"><textarea id="newNote" maxlength="2000" placeholder="For example: Should be included in deliberation…" disabled></textarea><button id="saveNote" type="submit" disabled>Add note</button></div>
                    <div class="notes-compose-footer"><p class="notes-hint">Today’s date is added automatically to the note.</p><span class="notes-character-count" id="newNoteCount">0 / 2000</span></div>
                </form>
                <div class="notes-list" id="recordNotes" aria-live="polite"><p class="notes-empty">Select a record from the list to review its notes.</p></div>
                <nav class="notes-pagination" id="notesPagination" aria-label="Notes pages" hidden>
                    <button type="button" id="previousNotesPage">Previous</button>
                    <span class="notes-page-status" id="notesPageStatus" aria-live="polite"></span>
                    <button type="button" id="nextNotesPage">Next</button>
                </nav>
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
            empty.textContent = 'This record has no notes yet. Add one above.';
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