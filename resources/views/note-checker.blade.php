@extends('layouts.app')

@section('title', 'Note Checker')

@section('content')
<style>
    /* Inherits the layout tokens (--bg / --surface / --border / --text / --text-muted / --accent), so it follows light/dark. */
    .nc { max-width: 1240px; margin: 0 auto; color: var(--text); --r: 12px; --soft: color-mix(in srgb, var(--text) 4%, var(--surface)); }
    .nc h1, .nc h2, .nc h3, .nc p { margin: 0; }
    .nc-hero { margin-bottom: 1.25rem; }
    .nc-hero h1 { font-size: clamp(1.6rem, 3.2vw, 2.1rem); font-weight: 650; letter-spacing: -.035em; line-height: 1.05; }
    .nc-hero p { max-width: 44rem; margin-top: .4rem; color: var(--text-muted); font-size: .9rem; line-height: 1.55; }

    .nc-layout { display: grid; grid-template-columns: minmax(270px, 340px) minmax(0, 1fr); gap: 1rem; align-items: start; }
    .nc-card { border: 1px solid var(--border); border-radius: var(--r); background: var(--surface); }

    /* Left: search + records */
    .nc-side { position: sticky; top: calc(var(--bar-height, 56px) + 1rem); display: flex; flex-direction: column; max-height: calc(100vh - var(--bar-height, 56px) - 2rem); overflow: hidden; }
    .nc-search { display: grid; gap: .5rem; padding: 1rem; border-bottom: 1px solid var(--border); }
    .nc-search-box { position: relative; }
    .nc-search-box svg { position: absolute; left: .8rem; top: 50%; width: 16px; height: 16px; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
    .nc-search input { width: 100%; padding: .65rem .8rem .65rem 2.3rem; border: 1px solid var(--border); border-radius: 999px; background: var(--bg); color: var(--text); font: inherit; font-size: .9rem; -webkit-appearance: none; appearance: none; }
    .nc-search input:hover { border-color: var(--text-muted); }
    .nc-list-meta { display: flex; align-items: center; justify-content: space-between; gap: .5rem; color: var(--text-muted); font-size: .76rem; }
    .nc-list-meta strong { color: var(--text); font-weight: 650; }
    .nc-records { flex: 1; min-height: 120px; overflow-y: auto; overscroll-behavior: contain; padding: .35rem; }
    .nc-record { display: flex; align-items: center; gap: .7rem; width: 100%; padding: .6rem .65rem; border: 0; border-radius: 10px; background: transparent; color: var(--text); text-align: left; cursor: pointer; transition: background .15s ease; }
    .nc-record:hover { background: var(--soft); }
    .nc-record.selected { background: color-mix(in srgb, var(--accent) 10%, var(--surface)); }
    .nc-initial { display: grid; place-items: center; flex: none; width: 36px; height: 36px; border-radius: 50%; background: var(--soft); color: var(--text); font-size: .78rem; font-weight: 700; }
    .nc-record.selected .nc-initial { background: var(--accent); color: #fff; }
    .nc-record-body { display: grid; min-width: 0; flex: 1; }
    .nc-record-body strong { overflow: hidden; font-size: .86rem; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
    .nc-record-body span { overflow: hidden; color: var(--text-muted); font-size: .74rem; text-overflow: ellipsis; white-space: nowrap; }
    .nc-count { flex: none; padding: .15rem .5rem; border-radius: 999px; background: var(--soft); color: var(--text); font-size: .7rem; font-weight: 700; white-space: nowrap; }
    .nc-count.none { color: var(--text-muted); font-weight: 600; }
    .nc-pages { display: flex; align-items: center; justify-content: space-between; gap: .4rem; padding: .55rem .75rem; border-top: 1px solid var(--border); }
    .nc-pages[hidden] { display: none; }
    .nc-pages span { color: var(--text-muted); font-size: .74rem; }
    .nc-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; min-height: 34px; padding: 0 .85rem; border: 1px solid var(--border); border-radius: 999px; background: transparent; color: var(--text); font: inherit; font-size: .8rem; font-weight: 650; cursor: pointer; }
    .nc-btn:hover:not(:disabled) { border-color: var(--text); }
    .nc-btn:disabled { opacity: .4; cursor: not-allowed; }
    .nc-btn.primary { border-color: var(--text); background: var(--text); color: var(--bg); }
    .nc-btn.primary:hover:not(:disabled) { opacity: .88; }

    /* Right: record, timeline, compose, notes */
    .nc-main { display: grid; gap: 1rem; min-width: 0; }
    .nc-head { display: flex; align-items: center; gap: 1rem; padding: 1.1rem 1.25rem; }
    .nc-head .nc-initial { width: 52px; height: 52px; font-size: 1.05rem; background: var(--text); color: var(--bg); }
    .nc-head-body { display: grid; gap: .35rem; min-width: 0; flex: 1; }
    .nc-head h2 { overflow: hidden; font-size: 1.2rem; font-weight: 700; letter-spacing: -.02em; text-overflow: ellipsis; white-space: nowrap; }
    .nc-tags { display: flex; flex-wrap: wrap; gap: .35rem; }
    .nc-tag { padding: .15rem .55rem; border: 1px solid var(--border); border-radius: 999px; color: var(--text-muted); font-size: .74rem; font-weight: 600; }
    .nc-section-title { display: flex; align-items: baseline; justify-content: space-between; gap: .5rem; padding: 1rem 1.25rem 0; }
    .nc-section-title h3 { font-size: .95rem; font-weight: 700; }
    .nc-section-title span { color: var(--text-muted); font-size: .76rem; }

    /* Timeline: the record's notes placed by date on one line, labels alternating above and below */
    .nc-timeline-wrap { overflow-x: auto; padding: .5rem 1.25rem 1.1rem; }
    .nc-timeline { position: relative; height: 196px; min-width: 100%; }
    .nc-tl-line { position: absolute; left: 0; right: 0; top: 50%; height: 2px; border-radius: 2px; background: var(--border); }
    .nc-tl-item { position: absolute; top: 50%; width: 0; height: 0; }
    .nc-tl-dot { position: absolute; left: -8px; top: -8px; width: 16px; height: 16px; padding: 0; border: 3px solid var(--surface); border-radius: 50%; background: var(--text); box-shadow: 0 0 0 1px var(--text); cursor: pointer; transition: transform .15s ease; }
    .nc-tl-dot:hover, .nc-tl-dot:focus-visible { transform: scale(1.25); }
    .nc-tl-item.active .nc-tl-dot { background: var(--accent); box-shadow: 0 0 0 1px var(--accent), 0 0 0 6px color-mix(in srgb, var(--accent) 20%, transparent); }
    .nc-tl-stem { position: absolute; left: -1px; width: 2px; height: 22px; background: var(--border); }
    .nc-tl-label { position: absolute; left: 0; display: grid; gap: .1rem; width: 150px; transform: translateX(-50%); text-align: center; }
    .nc-tl-item.up .nc-tl-stem { bottom: 8px; }
    .nc-tl-item.up .nc-tl-label { bottom: 32px; }
    .nc-tl-item.down .nc-tl-stem { top: 8px; }
    .nc-tl-item.down .nc-tl-label { top: 32px; }
    .nc-tl-item.edge-start .nc-tl-label { transform: translateX(-12px); text-align: left; }
    .nc-tl-item.edge-end .nc-tl-label { transform: translateX(calc(-100% + 12px)); text-align: right; }
    .nc-tl-date { font-size: .74rem; font-weight: 750; font-variant-numeric: tabular-nums; }
    .nc-tl-text { display: -webkit-box; overflow: hidden; color: var(--text-muted); font-size: .74rem; line-height: 1.35; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow-wrap: anywhere; }
    .nc-tl-dot.multi { left: -12px; top: -12px; width: 24px; height: 24px; border-width: 2px; color: var(--surface); font: 800 .7rem/1 inherit; }
    .nc-tl-item.up .nc-tl-stem { bottom: 12px; }
    .nc-tl-item.down .nc-tl-stem { top: 12px; }
    .nc-tl-more { color: var(--text); font-size: .7rem; font-weight: 700; }
    .nc-tl-text.one { -webkit-line-clamp: 1; }
    .nc-timeline { height: 216px; }
    .nc-tl-ends { display: flex; justify-content: space-between; margin-top: .2rem; color: var(--text-muted); font-size: .7rem; }
    .nc-tl-empty { padding: 1.4rem 1.25rem 1.5rem; color: var(--text-muted); font-size: .85rem; text-align: center; }
    .nc-undated { padding: 0 1.25rem 1rem; color: var(--text-muted); font-size: .76rem; }

    .nc-compose { padding: 1rem 1.25rem; }
    .nc-compose label { display: block; margin-bottom: .45rem; font-size: .85rem; font-weight: 700; }
    .nc-compose-row { display: flex; align-items: flex-end; gap: .6rem; }
    .nc-compose textarea { flex: 1; min-height: 46px; max-height: 180px; resize: vertical; padding: .7rem .9rem; border: 1px solid var(--border); border-radius: 12px; background: var(--bg); color: var(--text); font: inherit; font-size: .9rem; line-height: 1.45; }
    .nc-compose textarea:disabled { opacity: .5; }
    .nc-compose-foot { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-top: .45rem; color: var(--text-muted); font-size: .74rem; }
    .nc-alert { margin: 0; padding: .65rem 1rem; border: 1px solid color-mix(in srgb, var(--danger) 40%, var(--border)); border-radius: 10px; color: var(--danger); font-size: .84rem; }
    .nc-alert:empty { display: none; }

    .nc-notes { display: grid; gap: .1rem; padding: .5rem 1.25rem 1rem; }
    .nc-note { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: start; gap: .9rem; padding: .85rem .75rem; border-radius: 10px; transition: background .3s ease; }
    .nc-note + .nc-note { border-top: 1px solid var(--border); border-radius: 0; }
    .nc-note.flash { background: color-mix(in srgb, var(--accent) 12%, transparent); border-radius: 10px; }
    .nc-note-date { display: grid; place-items: center; min-width: 58px; padding: .35rem .45rem; border-radius: 8px; background: var(--soft); text-align: center; line-height: 1.15; }
    .nc-note-date strong { font-size: 1.05rem; font-weight: 750; }
    .nc-note-date span { color: var(--text-muted); font-size: .66rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .nc-note-text { padding-top: .2rem; font-size: .9rem; line-height: 1.55; overflow-wrap: anywhere; }
    .nc-note-del { padding: .3rem .6rem; border: 1px solid var(--border); border-radius: 999px; background: transparent; color: var(--danger); font: inherit; font-size: .74rem; font-weight: 650; cursor: pointer; }
    .nc-note-del:hover:not(:disabled) { border-color: var(--danger); }
    .nc-note-del:disabled { opacity: .5; cursor: not-allowed; }
    .nc-empty { padding: 2rem 1rem; color: var(--text-muted); font-size: .88rem; text-align: center; line-height: 1.6; }
    .nc-notes-pages { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .6rem 1.25rem; border-top: 1px solid var(--border); }
    .nc-notes-pages[hidden] { display: none; }
    .nc-notes-pages span { color: var(--text-muted); font-size: .76rem; }

    .nc button:focus-visible, .nc input:focus-visible, .nc textarea:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    @media (prefers-reduced-motion: reduce) { .nc * { transition: none !important; } }
    @media (max-width: 860px) {
        .nc-layout { grid-template-columns: 1fr; }
        .nc-side { position: static; max-height: 420px; }
        .nc-note { grid-template-columns: auto minmax(0, 1fr); }
        .nc-note-del { grid-column: 2; justify-self: start; }
    }
</style>

<div class="nc">
    <header class="nc-hero">
        <h1>Note Checker</h1>
        <p>Search any record to read its notes on a timeline, add a dated note, or remove one. With no search, the list shows the records that already have notes.</p>
    </header>

    <div class="nc-layout" id="notesApp"
        data-list-url="{{ route('forms.notes') }}"
        data-add-url="{{ route('forms.notes.append', ['record' => '__ID__']) }}"
        data-delete-url="{{ route('forms.notes.delete', ['record' => '__ID__', 'noteIndex' => '__INDEX__']) }}"
        data-csrf="{{ csrf_token() }}">
        <aside class="nc-card nc-side" aria-label="Records">
            <div class="nc-search">
                <div class="nc-search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input id="notesSearch" type="search" placeholder="Search any name or registry…" aria-label="Search any record" autocomplete="off">
                </div>
                <div class="nc-list-meta"><span id="notesListLabel">Records with notes</span><strong id="notesRecordCount" aria-live="polite">Loading</strong></div>
            </div>
            <div class="nc-records" id="notesRecords" aria-live="polite"><p class="nc-empty">Loading records…</p></div>
            <nav class="nc-pages" id="notesRecordPagination" aria-label="Record pages" hidden>
                <button class="nc-btn" type="button" id="previousRecordsPage">Previous</button>
                <span id="recordsPageStatus" aria-live="polite"></span>
                <button class="nc-btn" type="button" id="nextRecordsPage">Next</button>
            </nav>
        </aside>

        <section class="nc-main" aria-label="Selected record">
            <div class="nc-card nc-head">
                <span class="nc-initial" id="selectedInitial" aria-hidden="true">?</span>
                <div class="nc-head-body">
                    <h2 id="selectedRecordName">Select a record</h2>
                    <div class="nc-tags" id="selectedRecordTags"><span class="nc-tag">Search or pick a record on the left</span></div>
                </div>
                <span class="nc-count" id="selectedNoteCount">No record selected</span>
            </div>

            <p class="nc-alert" id="notesAlert" role="alert"></p>

            <div class="nc-card">
                <div class="nc-section-title"><h3>Notes timeline</h3><span id="timelineRange"></span></div>
                <div id="notesTimeline"><p class="nc-tl-empty">The notes of the selected record appear here by date.</p></div>
            </div>

            <form class="nc-card nc-compose" id="noteForm">
                <label for="newNote">Add a note</label>
                <div class="nc-compose-row">
                    <textarea id="newNote" maxlength="2000" placeholder="For example: Should be included in deliberation…" disabled></textarea>
                    <button class="nc-btn primary" id="saveNote" type="submit" disabled>Add note</button>
                </div>
                <div class="nc-compose-foot"><span>Today’s date is added to the note automatically.</span><span id="newNoteCount">0 / 2000</span></div>
            </form>

            <div class="nc-card">
                <div class="nc-section-title"><h3>All notes</h3><span id="notesListCount"></span></div>
                <div class="nc-notes" id="recordNotes" aria-live="polite"><p class="nc-empty">Select a record to review its notes.</p></div>
                <nav class="nc-notes-pages" id="notesPagination" aria-label="Notes pages" hidden>
                    <button class="nc-btn" type="button" id="previousNotesPage">Previous</button>
                    <span id="notesPageStatus" aria-live="polite"></span>
                    <button class="nc-btn" type="button" id="nextNotesPage">Next</button>
                </nav>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const app = document.getElementById('notesApp');
    const $ = id => document.getElementById(id);
    const recordsList = $('notesRecords');
    const recordsPagination = $('notesRecordPagination');
    const previousRecordsPageButton = $('previousRecordsPage');
    const nextRecordsPageButton = $('nextRecordsPage');
    const recordsPageStatus = $('recordsPageStatus');
    const notesList = $('recordNotes');
    const search = $('notesSearch');
    const form = $('noteForm');
    const input = $('newNote');
    const saveButton = $('saveNote');
    const pagination = $('notesPagination');
    const previousPageButton = $('previousNotesPage');
    const nextPageButton = $('nextNotesPage');
    const pageStatus = $('notesPageStatus');
    const alert = $('notesAlert');
    const selectedName = $('selectedRecordName');
    const selectedTags = $('selectedRecordTags');
    const selectedInitial = $('selectedInitial');
    const recordCount = $('notesRecordCount');
    const listLabel = $('notesListLabel');
    const selectedNoteCount = $('selectedNoteCount');
    const noteCharacterCount = $('newNoteCount');
    const timeline = $('notesTimeline');
    const timelineRange = $('timelineRange');
    const notesListCount = $('notesListCount');
    let records = [];
    let selectedId = null;
    let searchTimer = null;
    let notesPage = 0;
    const notesPerPage = 5;
    let recordsPage = 0;
    const recordsPerPage = 10;
    let activeRecordsRequest = null;
    let recordsRequestSequence = 0;
    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };
    const initials = name => (name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase() || '?';
    const plural = (count, word) => `${count} ${word}${count === 1 ? '' : 's'}`;

    // Notes start with "NN/NN/YYYY:". The existing records write the month first (e.g. "10/02/2025: ... OCTOBER"),
    // so a date reads month-first unless a part over 12 shows it is day-first. Notes without a date are kept as-is.
    function parseNote(note) {
        // DD/MM/YYYY or MM/DD/YYYY from notes; DD-MM-YYYY from Monthly Session log entries (always day first).
        const match = note.match(/^(\d{1,2})([\/-])(\d{1,2})\2(\d{4})\s*:\s*(.*)$/s);
        if (!match) return { date: null, text: note };
        const [, first, separator, second, year, text] = match;
        const dayFirst = separator === '-' || Number(first) > 12;
        const month = Number(dayFirst ? second : first);
        const day = Number(dayFirst ? first : second);
        const date = new Date(Number(year), month - 1, day);
        if (Number.isNaN(date.getTime()) || date.getMonth() !== month - 1) return { date: null, text: note };
        return { date, text, label: `${first}${separator}${second}${separator}${year}` };
    }

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
            const error = new Error(firstError || payload.message || 'Could not complete the notes request.');
            error.status = response.status;
            throw error;
        }
        return payload;
    }

    /* ---------- Timeline ---------- */
    function renderTimeline(record) {
        const parsed = record.notes.map((note, index) => ({ ...parseNote(note), index }));
        const dated = parsed.filter(note => note.date).sort((a, b) => a.date - b.date || a.index - b.index);
        const undated = parsed.length - dated.length;
        timeline.replaceChildren();
        timelineRange.textContent = '';
        if (!dated.length) {
            timeline.append(el('p', 'nc-tl-empty', record.notes.length
                ? 'These notes have no dates, so they cannot be placed on the timeline.'
                : 'No notes yet. Add the first one below.'));
            return;
        }
        // Notes written on the same day share one marker (with a count), so none is drawn on top of another.
        const groups = [];
        dated.forEach(note => {
            const last = groups[groups.length - 1];
            if (last && last.date.getTime() === note.date.getTime()) last.notes.push(note);
            else groups.push({ date: note.date, label: note.label, notes: [note] });
        });
        const first = groups[0].date, last = groups[groups.length - 1].date;
        const span = Math.max(1, last - first);
        const byDate = groups.map(group => 4 + ((group.date - first) / span) * 92);
        // Dates bunched too close for their labels are spaced evenly instead (still in date order).
        const crowded = byDate.some((pct, i) => i > 0 && pct - byDate[i - 1] < 9);
        const positions = groups.length === 1 ? [50] : crowded ? groups.map((_, i) => 4 + (i / (groups.length - 1)) * 92) : byDate;
        timelineRange.textContent = groups.length === 1 ? groups[0].label : `${groups[0].label} → ${groups[groups.length - 1].label}`;
        const wrap = el('div', 'nc-timeline-wrap');
        const line = el('div', 'nc-timeline');
        line.style.minWidth = `${Math.max(520, groups.length * 160)}px`;
        line.append(el('div', 'nc-tl-line'));
        groups.forEach((group, i) => {
            const pct = positions[i];
            const item = el('div', `nc-tl-item ${i % 2 ? 'down' : 'up'}${groups.length > 1 && pct < 12 ? ' edge-start' : ''}${groups.length > 1 && pct > 88 ? ' edge-end' : ''}`);
            item.style.left = `${pct}%`;
            item.dataset.indexes = group.notes.map(note => note.index).join(',');
            const count = group.notes.length;
            const dot = el('button', `nc-tl-dot${count > 1 ? ' multi' : ''}`);
            dot.type = 'button';
            if (count > 1) dot.textContent = String(count);
            const summary = group.notes.map(note => note.text).join(' · ');
            dot.setAttribute('aria-label', `${group.label}: ${plural(count, 'note')}. ${summary}`);
            dot.title = `${group.label} (${plural(count, 'note')})\n${group.notes.map(note => `• ${note.text}`).join('\n')}`;
            dot.addEventListener('click', () => focusNote(record, group.notes.map(note => note.index)));
            const label = el('div', 'nc-tl-label');
            label.append(el('span', 'nc-tl-date', count > 1 ? `${group.label} · ${plural(count, 'note')}` : group.label));
            group.notes.slice(0, 2).forEach(note => label.append(el('span', count > 1 ? 'nc-tl-text one' : 'nc-tl-text', note.text)));
            if (count > 2) label.append(el('span', 'nc-tl-more', `+${count - 2} more`));
            item.append(el('span', 'nc-tl-stem'), dot, label);
            line.append(item);
        });
        wrap.append(line);
        if (groups.length > 1) {
            const ends = el('div', 'nc-tl-ends');
            const days = Math.round(span / 86400000);
            const apart = days >= 60 ? `${Math.round(days / 30.4)} months apart` : `${plural(days, 'day')} apart`;
            ends.append(el('span', '', groups[0].label), el('span', '', crowded ? `${apart} · spaced evenly so every date is readable` : apart), el('span', '', groups[groups.length - 1].label));
            wrap.append(ends);
        }
        timeline.append(wrap);
        if (undated) timeline.append(el('p', 'nc-undated', `${plural(undated, 'older note')} without a date ${undated === 1 ? 'is' : 'are'} listed below only.`));
    }

    // Show the page holding the marker's notes and highlight every note of that date.
    function focusNote(record, indexes) {
        notesPage = Math.floor(indexes[0] / notesPerPage);
        renderNotes(record);
        const key = indexes.join(',');
        timeline.querySelectorAll('.nc-tl-item').forEach(item => item.classList.toggle('active', item.dataset.indexes === key));
        const rows = indexes.map(index => notesList.querySelector(`[data-note-index="${index}"]`)).filter(Boolean);
        rows[0]?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        rows.forEach(row => {
            row.classList.add('flash');
            setTimeout(() => row.classList.remove('flash'), 1400);
        });
    }

    /* ---------- Selected record ---------- */
    function renderHeader(record) {
        selectedName.textContent = record.name;
        selectedInitial.textContent = initials(record.name);
        selectedTags.replaceChildren(
            ...[record.registry ? `Registry ${record.registry}` : 'No registry number', record.status, record.purok ? `Purok ${record.purok}` : null, record.grupo ? `Grupo ${record.grupo}` : null]
                .filter(Boolean).map(text => el('span', 'nc-tag', text)),
        );
        selectedNoteCount.textContent = record.notes.length ? plural(record.notes.length, 'note') : 'No notes yet';
        selectedNoteCount.classList.toggle('none', !record.notes.length);
    }

    function renderNotes(record) {
        if (!record) return;
        if (Number(record.id) !== selectedId) notesPage = 0;
        selectedId = Number(record.id);
        renderHeader(record);
        renderTimeline(record);
        input.disabled = false;
        saveButton.disabled = false;
        recordsList.querySelectorAll('.nc-record').forEach(button => {
            const selected = Number(button.dataset.id) === selectedId;
            button.classList.toggle('selected', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        notesList.replaceChildren();
        notesListCount.textContent = record.notes.length ? plural(record.notes.length, 'note') : '';
        if (!record.notes.length) {
            pagination.hidden = true;
            notesList.append(el('p', 'nc-empty', 'This record has no notes yet. Add one above.'));
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
            const parsed = parseNote(note);
            const row = el('article', 'nc-note');
            row.dataset.noteIndex = index;
            const date = el('div', 'nc-note-date');
            if (parsed.date) {
                date.append(el('strong', '', String(parsed.date.getDate())), el('span', '', `${MONTHS[parsed.date.getMonth()]} ${parsed.date.getFullYear()}`));
                date.title = parsed.label;
            } else {
                date.append(el('span', '', 'Older'), el('span', '', 'note'));
            }
            const remove = el('button', 'nc-note-del', 'Delete');
            remove.type = 'button';
            remove.addEventListener('click', async () => {
                if (!window.confirm('Delete this note from the selected record?')) return;
                remove.disabled = true;
                try {
                    const url = app.dataset.deleteUrl.replace('__ID__', record.id).replace('__INDEX__', index);
                    // The note text lets the server refuse (409) if the list changed since this page loaded.
                    const updated = await request(url, { method: 'DELETE', body: JSON.stringify({ note }) });
                    replaceRecord(updated);
                    renderNotes(updated);
                    alert.textContent = '';
                    window.lspdmsNotify?.({ title: 'Note deleted', message: updated.name, changes: [{ label: parsed.label || 'Note', from: parsed.text, to: 'Deleted' }] });
                } catch (error) {
                    remove.disabled = false;
                    if (error.status !== 409) { showError(error); return; }
                    // Reload first: a successful reload clears the alert, so the explanation is written after it.
                    try { await loadRecords(); } catch (reloadError) { showError(reloadError); return; }
                    const changed = 'The notes for this record changed since the page loaded.';
                    const shown = records.find(item => Number(item.id) === selectedId);
                    alert.textContent = Number(record.id) === selectedId
                        ? `${changed} The latest notes are shown now; check them and try again.`
                        : `${changed} ${record.name} is no longer in this list${shown ? `, so ${shown.name} is shown instead` : ''}.`;
                }
            });
            row.append(date, el('p', 'nc-note-text', parsed.text), remove);
            notesList.append(row);
        });
    }

    /* ---------- Record list ---------- */
    function renderRecords(items) {
        records = items;
        recordsList.replaceChildren();
        if (!items.length) {
            recordsList.append(el('p', 'nc-empty', search.value.trim() ? 'No record matches this search.' : 'No records have notes yet. Search for a record to add the first note.'));
            selectedId = null;
            selectedName.textContent = 'Select a record';
            selectedInitial.textContent = '?';
            selectedTags.replaceChildren(el('span', 'nc-tag', 'Search or pick a record on the left'));
            recordCount.textContent = '0 records';
            selectedNoteCount.textContent = 'No record selected';
            notesList.replaceChildren();
            timeline.replaceChildren(el('p', 'nc-tl-empty', 'The notes of the selected record appear here by date.'));
            recordsPagination.hidden = true;
            pagination.hidden = true;
            input.disabled = true;
            saveButton.disabled = true;
            return;
        }

        const pageCount = Math.ceil(items.length / recordsPerPage);
        recordCount.textContent = plural(items.length, 'record');
        recordsPage = Math.min(recordsPage, pageCount - 1);
        recordsPagination.hidden = pageCount <= 1;
        previousRecordsPageButton.disabled = recordsPage === 0;
        nextRecordsPageButton.disabled = recordsPage >= pageCount - 1;
        recordsPageStatus.textContent = `Page ${recordsPage + 1} of ${pageCount}`;
        const startIndex = recordsPage * recordsPerPage;
        const visibleRecords = items.slice(startIndex, startIndex + recordsPerPage);

        visibleRecords.forEach(record => {
            const button = el('button', `nc-record${Number(record.id) === selectedId ? ' selected' : ''}`);
            button.type = 'button';
            button.dataset.id = record.id;
            button.setAttribute('aria-pressed', String(Number(record.id) === selectedId));
            const body = el('span', 'nc-record-body');
            body.append(el('strong', '', record.name), el('span', '', [record.registry || 'No registry number', record.status].filter(Boolean).join(' · ')));
            const count = el('span', `nc-count${record.notes.length ? '' : ' none'}`, record.notes.length ? plural(record.notes.length, 'note') : 'No notes');
            button.append(el('span', 'nc-initial', initials(record.name)), body, count);
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

    // Updated records stay in the list (a searched record keeps its place even with no notes left).
    function replaceRecord(updated) {
        records = records.map(record => Number(record.id) === Number(updated.id) ? { ...record, ...updated } : record);
        renderRecords(records);
    }

    async function loadRecords() {
        const term = search.value.trim();
        const url = `${app.dataset.listUrl}?q=${encodeURIComponent(term)}`;
        activeRecordsRequest?.abort();
        const controller = new AbortController();
        activeRecordsRequest = controller;
        const sequence = ++recordsRequestSequence;
        listLabel.textContent = term ? 'Search results · any record' : 'Records with notes';
        recordsList.replaceChildren(el('p', 'nc-empty', 'Searching records…'));
        try {
            const items = await request(url, { signal: controller.signal });
            if (sequence !== recordsRequestSequence) return;
            recordCount.textContent = plural(items.length, 'record');
            recordsPage = 0;
            renderRecords(items);
            alert.textContent = '';
        } catch (error) {
            if (error?.name !== 'AbortError') throw error;
        }
    }

    previousRecordsPageButton.addEventListener('click', () => { if (recordsPage > 0) { recordsPage--; renderRecords(records); } });
    nextRecordsPageButton.addEventListener('click', () => {
        if ((recordsPage + 1) * recordsPerPage < records.length) { recordsPage++; renderRecords(records); }
    });
    previousPageButton.addEventListener('click', () => {
        if (notesPage > 0) { notesPage--; const selected = records.find(record => Number(record.id) === selectedId); if (selected) renderNotes(selected); }
    });
    nextPageButton.addEventListener('click', () => {
        const selected = records.find(record => Number(record.id) === selectedId);
        if (selected && (notesPage + 1) * notesPerPage < selected.notes.length) { notesPage++; renderNotes(selected); }
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
    input.addEventListener('input', () => { noteCharacterCount.textContent = `${input.value.length} / 2000`; });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!selectedId || !input.value.trim()) return;
        saveButton.disabled = true;
        try {
            const url = app.dataset.addUrl.replace('__ID__', selectedId);
            const text = input.value.trim();
            const updated = await request(url, { method: 'POST', body: JSON.stringify({ note: input.value }) });
            input.value = '';
            noteCharacterCount.textContent = '0 / 2000';
            alert.textContent = '';
            notesPage = Math.floor((updated.notes.length - 1) / notesPerPage);
            replaceRecord(updated);
            renderNotes(updated);
            window.lspdmsNotify?.({ title: 'Note added', message: updated.name, changes: [{ label: 'Today', to: text }] });
        } catch (error) { showError(error); }
        finally { saveButton.disabled = false; }
    });

    loadRecords().catch(showError);
})();
</script>
@endpush
