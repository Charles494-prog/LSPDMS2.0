@extends('layouts.app')

@section('title', 'Smart Search / Edit Data')

@section('content')
<div class="reg-root">

    <header class="reg-head">
        <div>
            <h1>Smart Search / Edit Data</h1>
            <p class="reg-lede">Find a record by name or registry number, view its details, and edit it when needed.</p>
        </div>
        <div class="reg-head-actions">
            <button type="button" class="btn btn-ghost" id="remind-open" aria-expanded="false" aria-controls="reg-guide">Guidance</button>
            <button type="button" class="btn btn-ghost" id="helper-open" aria-expanded="false" aria-controls="helper">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" aria-hidden="true"><path d="m12 2 1.9 6.1L20 10l-6.1 1.9L12 18l-1.9-6.1L4 10l6.1-1.9L12 2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                Smart Helper
            </button>
        </div>
    </header>

    {{-- Guidance: inline, no floating window --}}
    <section class="reg-guide" id="reg-guide" aria-labelledby="rem-title" hidden>
        <div class="reg-guide-head">
            <div>
                <h2 id="rem-title">Record update guide</h2>
                <p>Choose the right portal for each change.</p>
            </div>
            <button type="button" class="btn btn-ghost" id="rem-ok">Got it</button>
        </div>
        <div class="reg-guide-grid">
            <div class="reg-guide-cell">
                <span class="reg-guide-label">Smart Search / Edit Data</span>
                <p>Correct personal details, such as name, registry number, gender, baptism date, and evangelist. Update a member's Grupo within their current Purok.</p>
            </div>
            <div class="reg-guide-cell">
                <span class="reg-guide-label">Form Generator &rsaquo; Monthly &rsaquo; Deliberation</span>
                <p>Change a member's status or move a group between Puroks. Remove duplicate entries here only when <strong>none of the matching records is Inactive</strong>.</p>
            </div>
            <div class="reg-guide-cell">
                <span class="reg-guide-label">Duplicate removal</span>
                <p>If a match includes an Inactive record, Smart Helper lets you remove that Inactive record. If all matches are active (for example, MS and UWP), use Monthly Deliberation.</p>
            </div>
        </div>
    </section>

    <div class="reg-layout" id="reg-layout">
    <div class="reg-main">
    <div class="reg-workspace">

        {{-- ============ Search + results table ============ --}}
        <section class="rp rp-list" aria-labelledby="list-title">
            <div class="rp-head">
                <div>
                    <h2 id="list-title">Search records</h2>
                    <p id="list-sub">Type at least 2 characters of a name or registry number.</p>
                </div>
            </div>

            <div class="rp-body">
                <div class="reg-search" role="search">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <input type="text" id="reg-search-input" placeholder="Full name or registry number" autocomplete="off" aria-label="Search records">
                    <button type="button" id="reg-search-btn" class="btn btn-ink">Search</button>
                </div>
                <p id="reg-search-error" class="reg-error" role="alert" hidden></p>

                <div id="reg-empty" class="reg-empty">
                    <strong>No search yet</strong>
                    <span>Matching records appear here as a table. Select a row to view it, then choose Edit to change it.</span>
                </div>

                <div id="reg-list" hidden>
                    <div id="reg-table-area">
                    <div class="reg-toolbar">
                        <p class="reg-count" id="reg-count" aria-live="polite"></p>
                        <div class="reg-chips" role="group" aria-label="Filter by status" id="reg-chips">
                            <button type="button" class="chip is-on" data-filter="all" aria-pressed="true">All <span></span></button>
                            <button type="button" class="chip" data-filter="ms" aria-pressed="false">MS <span></span></button>
                            <button type="button" class="chip" data-filter="uwp" aria-pressed="false">UWP <span></span></button>
                            <button type="button" class="chip" data-filter="mk" aria-pressed="false">MK <span></span></button>
                            <button type="button" class="chip" data-filter="inactive" aria-pressed="false">Inactive <span></span></button>
                        </div>
                    </div>

                    <div class="reg-table-wrap">
                        <table class="reg-table" id="reg-table">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Registry no.</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="col-kap">Kapisanan</th>
                                    <th scope="col" class="col-place">Purok / Grupo</th>
                                    <th scope="col" class="col-act"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody id="reg-rows" aria-live="polite" aria-busy="false"></tbody>
                        </table>
                        <p class="reg-table-empty" id="reg-table-empty" hidden>No records match this status filter.</p>
                    </div>

                    <div class="reg-pager" id="reg-pager" hidden>
                        <span id="reg-pager-info"></span>
                        <div>
                            <button type="button" class="btn btn-ghost btn-sm" id="reg-prev">Previous</button>
                            <button type="button" class="btn btn-ghost btn-sm" id="reg-next">Next</button>
                        </div>
                    </div>
                    </div>{{-- /reg-table-area --}}
                    <div class="reg-collapsed" id="reg-collapsed" hidden>
                        <span id="reg-collapsed-text"></span>
                        <button type="button" class="btn btn-ghost btn-sm" id="reg-show-results">Show results</button>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ Right: record panel (view / edit) ============ --}}
        <section class="rp rp-record" id="reg-record" aria-labelledby="record-title">
            <div class="rp-head">
                <div>
                    <h2 id="record-title">Record</h2>
                    <p id="record-sub">Select a record from the results to open it.</p>
                </div>
            </div>

            <div class="rp-body">
                <div class="reg-placeholder" id="reg-placeholder">
                    <strong>No record open</strong>
                    <span>Records open read-only. Choose Edit inside the record to change anything.</span>
                </div>

                <div id="reg-editor" class="reg-editor" data-mode="view" hidden>
                    <div class="reg-band">
                        <div class="reg-avatar" id="rm-avatar" aria-hidden="true">--</div>
                        <div class="reg-band-text">
                            <div class="reg-name" id="rm-name-display">--</div>
                            <div class="reg-regno" id="rm-registry-display">--</div>
                            <div class="reg-band-meta">
                                <span id="rm-status-badge" class="reg-badge">--</span>
                                <span class="reg-mode" id="rm-mode" aria-live="polite">Viewing</span>
                            </div>
                        </div>
                        <button type="button" id="reg-modal-close" class="reg-close" aria-label="Close record and return to results" title="Close (Esc)">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                            <span>Close</span>
                        </button>
                    </div>

                    <div id="rm-dup" class="reg-alert" hidden></div>

                    <form id="reg-form" novalidate>
                        @csrf
                        <input type="hidden" id="rm-id" name="id">

                        <div class="reg-body">
                            <h3 id="rm-section-title">Details</h3>
                            <div class="reg-grid">
                                <div class="f span2"><label for="rm-name">Name</label><input type="text" id="rm-name"><p class="err" data-err="name"></p></div>
                                <div class="f span2">
                                    <label for="rm-registry">Registry number</label>
                                    <input type="text" id="rm-registry" maxlength="13" placeholder="Exactly 13 characters" autocomplete="off" aria-describedby="rm-registry-hint">
                                    <p class="hint" id="rm-registry-hint"></p>
                                    <p class="err" data-err="registry_number"></p>
                                    <div id="rm-regdup" class="reg-regdup" role="alert" hidden></div>
                                </div>
                                <div class="f"><label for="rm-gender">Gender</label>
                                    <select id="rm-gender" required><option value="" disabled>Select gender</option><option value="Male">Male</option><option value="Female">Female</option></select>
                                    <p class="err" data-err="gender"></p></div>
                                <div class="f"><label for="rm-birthday">Birthday (optional)</label><input type="date" id="rm-birthday" max="{{ now()->toDateString() }}"><p class="err" data-err="date_of_birth"></p></div>
                                <div class="f"><label for="rm-married">Marital status</label>
                                    <label class="check"><input type="checkbox" id="rm-married"> Married <small>(unchecked = Not Married)</small></label>
                                    <p class="err" data-err="is_married"></p>
                                </div>
                                <div class="f"><label for="rm-kapisanan">Kapisanan</label><input type="text" id="rm-kapisanan"><p class="hint" id="rm-kapisanan-hint"></p><p class="err" data-err="kapisanan"></p></div>
                                <div class="f"><label for="rm-grupo">Grupo</label><input type="number" id="rm-grupo" step="1"><p class="hint" id="rm-grupo-hint"></p><p class="err" data-err="grupo"></p></div>
                                <div class="f"><label for="rm-baptism">Date of baptism</label><input type="date" id="rm-baptism"><p class="err" data-err="date_of_baptism"></p></div>
                                <div class="f span2"><label for="rm-evangelist">Evangelist</label><input type="text" id="rm-evangelist"><p class="err" data-err="evangelist"></p></div>
                            </div>

                            <h3 class="reg-locked-title">Managed in Monthly <small>Form Generator › Monthly › Deliberation</small></h3>
                            <div class="reg-locks">
                                <div class="lock"><span class="lock-k">Status</span><span class="lock-v" id="rm-status-text">--</span></div>
                                <div class="lock"><span class="lock-k">Date of entry</span><span class="lock-v" id="rm-entry-display">--</span></div>
                                <div class="lock"><span class="lock-k">Purok</span><span class="lock-v" id="rm-purok-display">--</span></div>
                                <div class="lock"><span class="lock-k">R2-10 eligibility</span>
                                    <span class="lock-v"><span id="rm-r210-display">••••••••••</span>
                                    <button type="button" id="rm-r210-toggle" class="reg-eye" @disabled(! $canViewEligibility)>Show</button></span>
                                    @unless ($canViewEligibility)
                                        <span class="hint">De-Listment permission is required to view this information.</span>
                                    @endunless
                                </div>
                            </div>
                        </div>

                        <div class="reg-review" id="reg-review">
                            <h3>Review your changes</h3>
                            <ul id="reg-review-list"></ul>
                            <p class="err" id="reg-review-err" role="alert"></p>
                        </div>

                        <div class="reg-actions">
                            <span id="reg-save-msg" class="reg-save-msg" role="status"></span>
                            <span class="act act-view">
                                <button type="button" class="btn btn-ink" id="reg-edit-btn">Edit record</button>
                            </span>
                            <span class="act act-edit">
                                <button type="button" class="btn btn-ghost" id="reg-reset-btn">Reset</button>
                                <button type="button" class="btn btn-ghost" id="reg-cancel-btn">Cancel editing</button>
                                <button type="submit" class="btn btn-ink" id="reg-save-btn" disabled>Save changes</button>
                            </span>
                            <span class="act act-review">
                                <button type="button" class="btn btn-ghost" id="reg-back-btn">Back</button>
                                <button type="button" class="btn btn-ink" id="reg-confirm-btn">Confirm and save</button>
                            </span>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
    </div>{{-- /reg-main --}}

    {{-- ============ Smart Helper: opens as the right-hand panel; Search / Record move to the left ============ --}}
    <aside class="rp helper" id="helper" aria-labelledby="helper-title" hidden>
        <div class="rp-head helper-head">
            <span class="helper-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="m12 2 1.9 6.1L20 10l-6.1 1.9L12 18l-1.9-6.1L4 10l6.1-1.9L12 2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m19 15 .9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z" fill="currentColor"/></svg>
            </span>
            <div class="helper-title">
                <h2 id="helper-title">Smart Helper</h2>
                <p>Duplicate and registry number checker</p>
            </div>
            <span class="helper-state"><i aria-hidden="true"></i>Ready</span>
            <button type="button" class="helper-toggle" id="helper-toggle" aria-label="Close Smart Helper" title="Close Smart Helper">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="helper-panel" id="helper-panel">
            <div class="thread" id="smart-helper-thread" role="log" aria-live="polite">
                <div class="msg msg-bot">
                    <span class="msg-who">Smart Helper</span>
                    <div class="msg-body"><p>I find <strong>duplicates and errors in registry numbers</strong>. Would you like to run a search?</p></div>
                </div>
                <div class="msg msg-bot">
                    <span class="msg-who">Smart Helper</span>
                    <div class="msg-body"><p>I scan all records for exact duplicate registry numbers, duplicate names, and names at least 90% similar. Results may include different status or kapisanan values. Nothing is changed or removed unless you delete an Inactive duplicate yourself.</p></div>
                </div>
            </div>

            <div class="helper-foot">
                <span id="smart-helper-status" role="status">Press the button when you are ready.</span>
                <button type="button" id="smart-helper-scan" class="btn btn-ink">Run a search</button>
            </div>
        </div>
    </aside>
    </div>{{-- /reg-layout --}}

    {{-- Confirmation dialog (replaces window.confirm) --}}
    <div class="reg-modal" id="reg-modal" hidden>
        <div class="reg-dialog" role="alertdialog" aria-modal="true" aria-labelledby="dlg-title" aria-describedby="dlg-msg">
            <h2 id="dlg-title"></h2>
            <p id="dlg-msg"></p>
            <div class="dlg-actions">
                <button type="button" class="btn btn-ghost" id="dlg-cancel">Cancel</button>
                <button type="button" class="btn btn-ink" id="dlg-ok">Confirm</button>
            </div>
        </div>
    </div>

    <div class="snack" id="snack" role="status" aria-live="polite"></div>
</div>
@endsection

@push('styles')
<style>
    /* Tokens follow the layout's theme variables (--bg / --surface / --border / --text / --text-muted)
       so Light and Dark mode switch with html[data-theme]. Color is reserved for status and errors. */
    .reg-root {
        --card: var(--surface, #fff); --ink: var(--text, #000); --muted: var(--text-muted, #666); --line: var(--border, #eaeaea);
        --soft: color-mix(in srgb, var(--ink) 4%, var(--card));
        --hover: color-mix(in srgb, var(--ink) 6%, var(--card));
        --bad: #e5484d; --ok: #18a058; --warn: #b7791f;
        --r: 6px; --r-lg: 10px;
        max-width: 1500px; margin: 0 auto; color: var(--ink);
        font-family: 'Geist', 'Inter', -apple-system, 'Helvetica Neue', Arial, sans-serif;
        -webkit-font-smoothing: antialiased; font-variant-numeric: tabular-nums;
    }
    html[data-theme="dark"] .reg-root { --bad: #ff6369; --ok: #3ecf8e; --warn: #fbbf24; }
    .reg-root *, .reg-root *::before, .reg-root *::after { box-sizing: border-box; }
    .reg-root h1, .reg-root h2, .reg-root h3, .reg-root p, .reg-root ul { margin: 0; padding: 0; }
    .reg-root ul { list-style: none; }
    .reg-root [hidden] { display: none !important; }
    .reg-root input, .reg-root select { color-scheme: light; }
    html[data-theme="dark"] .reg-root input, html[data-theme="dark"] .reg-root select { color-scheme: dark; }
    .reg-root .sr-only { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    .reg-root a:focus-visible, .reg-root button:focus-visible, .reg-root [tabindex]:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }

    /* Header */
    .reg-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; padding-bottom: 1.1rem; border-bottom: 1px solid var(--line); }
    .reg-head h1 { font-size: 1.5rem; font-weight: 600; letter-spacing: -.03em; line-height: 1.15; }
    .reg-lede { margin-top: .3rem; color: var(--muted); font-size: .9rem; }

    /* Buttons */
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; height: 36px; padding: 0 1rem; border: 1px solid var(--ink); border-radius: var(--r); background: transparent; color: var(--ink); font: inherit; font-size: .85rem; font-weight: 500; white-space: nowrap; cursor: pointer; transition: background .12s ease, border-color .12s ease, opacity .12s ease; }
    .btn-sm { height: 30px; padding: 0 .75rem; font-size: .78rem; }
    .btn-ink { background: var(--ink); color: var(--card); }
    .btn-ink:hover:not(:disabled) { opacity: .85; }
    .btn-ghost { border-color: var(--line); }
    .btn-ghost:hover:not(:disabled) { border-color: var(--muted); background: var(--hover); }
    .btn-danger { border-color: var(--bad); background: var(--bad); color: #fff; }
    .btn-danger:hover:not(:disabled) { opacity: .88; }
    .btn:disabled { opacity: .45; cursor: not-allowed; }

    /* Guidance */
    .reg-guide { margin-bottom: 1rem; border: 1px solid var(--line); border-radius: var(--r-lg); background: var(--card); overflow: hidden; }
    .reg-guide-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--line); }
    .reg-guide-head h2 { font-size: .95rem; font-weight: 600; }
    .reg-guide-head p { margin-top: .15rem; color: var(--muted); font-size: .8rem; }
    .reg-guide-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .reg-guide-cell { padding: 1rem 1.25rem; border-right: 1px solid var(--line); font-size: .85rem; line-height: 1.6; }
    .reg-guide-cell:last-child { border-right: 0; }
    .reg-guide-label { display: block; margin-bottom: .35rem; color: var(--ink); font-size: .82rem; font-weight: 600; }

    /* Workspace: results | record */
    .reg-workspace { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 1rem; align-items: start; margin-bottom: 1rem; }
    .rp { min-width: 0; border: 1px solid var(--line); border-radius: var(--r-lg); background: var(--card); overflow: hidden; }
    .rp-head { display: flex; align-items: center; gap: .8rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--line); }
    .rp-head h2 { font-size: .95rem; font-weight: 600; letter-spacing: -.01em; }
    .rp-head p { margin-top: .15rem; color: var(--muted); font-size: .8rem; }
    .rp-body { padding: 1.1rem 1.25rem 1.25rem; }
    .rp-record { position: sticky; top: 1rem; max-height: calc(100vh - 2rem); display: flex; flex-direction: column; }
    .rp-record .rp-body { overflow-y: auto; }

    /* Search */
    .reg-search { display: flex; align-items: center; gap: .5rem; padding-left: .8rem; border: 1px solid var(--line); border-radius: var(--r); background: var(--card); overflow: hidden; transition: border-color .12s ease, box-shadow .12s ease; }
    .reg-search:focus-within { border-color: var(--ink); box-shadow: 0 0 0 1px var(--ink); }
    .reg-search svg { flex: none; width: 17px; height: 17px; color: var(--muted); }
    .reg-search input { flex: 1; min-width: 0; height: 40px; padding: 0; border: 0; background: transparent; color: var(--ink); font: inherit; font-size: .95rem; }
    .reg-search input:focus { outline: none; }
    .reg-search .btn { height: 40px; border-width: 0 0 0 1px; border-radius: 0; }
    .reg-error { margin-top: .7rem; padding: .6rem .8rem; border: 1px solid var(--bad); border-left-width: 3px; border-radius: var(--r); color: var(--bad); font-size: .85rem; }

    .reg-empty, .reg-placeholder { display: grid; gap: .3rem; margin-top: 1rem; padding: 2rem 1.2rem; border: 1px dashed var(--line); border-radius: var(--r-lg); background: var(--soft); text-align: center; color: var(--muted); font-size: .85rem; line-height: 1.6; }
    .reg-placeholder { margin-top: 0; }
    .reg-empty strong, .reg-placeholder strong { color: var(--ink); font-size: .95rem; font-weight: 600; }

    /* Toolbar + chips */
    .reg-toolbar { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; margin: 1rem 0 .7rem; }
    .reg-count { color: var(--muted); font-size: .8rem; }
    .reg-chips { display: flex; gap: .35rem; flex-wrap: wrap; }
    .chip { display: inline-flex; align-items: center; gap: .35rem; height: 28px; padding: 0 .65rem; border: 1px solid var(--line); border-radius: 999px; background: transparent; color: var(--muted); font: inherit; font-size: .75rem; font-weight: 500; cursor: pointer; transition: background .12s ease, color .12s ease, border-color .12s ease; }
    .chip span { font-size: .7rem; opacity: .8; }
    .chip:hover { border-color: var(--muted); color: var(--ink); }
    .chip.is-on { border-color: var(--ink); background: var(--ink); color: var(--card); }

    /* Table */
    .reg-table-wrap { border: 1px solid var(--line); border-radius: var(--r-lg); overflow-x: auto; }
    .reg-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .reg-table th { padding: .6rem .8rem; border-bottom: 1px solid var(--line); background: var(--soft); color: var(--muted); font-size: .74rem; font-weight: 500; text-align: left; white-space: nowrap; }
    .reg-table td { padding: .65rem .8rem; border-bottom: 1px solid var(--line); vertical-align: middle; }
    .reg-table tbody tr:last-child td { border-bottom: 0; }
    .reg-table tbody tr.reg-row { cursor: pointer; transition: background .1s ease; }
    .reg-table tbody tr.reg-row:hover td { background: var(--hover); }
    .reg-table tbody tr.is-selected td { background: var(--hover); }
    .reg-table tbody tr.is-selected td:first-child { box-shadow: inset 2px 0 0 var(--ink); }
    .cell-who { display: flex; align-items: center; gap: .6rem; min-width: 0; }
    .cell-who .reg-avatar { width: 30px; height: 30px; font-size: .72rem; }
    .cell-name { min-width: 0; font-weight: 500; overflow-wrap: anywhere; }
    .cell-name button { padding: 0; border: 0; background: none; color: inherit; font: inherit; text-align: left; cursor: pointer; }
    .cell-sub { display: block; color: var(--muted); font-size: .74rem; font-weight: 400; }
    .cell-reg { font-family: ui-monospace, SFMono-Regular, 'Geist Mono', Consolas, monospace; font-size: .8rem; white-space: nowrap; }
    .col-act { width: 1%; text-align: right; }
    .reg-table td.col-act { text-align: right; }
    .reg-dupchip { display: inline-block; margin-left: .4rem; padding: .05rem .4rem; border: 1px solid var(--warn); border-radius: var(--r); color: var(--warn); font-size: .66rem; font-weight: 500; vertical-align: middle; }
    .reg-table-empty { padding: 1.5rem 1rem; color: var(--muted); font-size: .85rem; text-align: center; }

    .skel { display: block; height: 12px; border-radius: 4px; background: linear-gradient(90deg, var(--soft), var(--hover), var(--soft)); background-size: 200% 100%; animation: reg-shimmer 1.2s linear infinite; }
    .skel-av { width: 30px; height: 30px; border-radius: 50%; flex: none; }
    @keyframes reg-shimmer { to { background-position: -200% 0; } }

    .reg-pager { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-top: .75rem; color: var(--muted); font-size: .8rem; }
    .reg-pager div { display: flex; gap: .4rem; }

    /* Avatar + status */
    .reg-avatar { display: grid; place-items: center; flex: none; width: 40px; height: 40px; border: 1px solid var(--line); border-radius: 50%; background: var(--soft); color: var(--ink); font-size: .8rem; font-weight: 600; }
    .reg-badge { display: inline-flex; align-items: center; gap: .4rem; padding: .15rem .55rem; border: 1px solid var(--line); border-radius: var(--r); color: var(--ink); font-size: .74rem; font-weight: 500; white-space: nowrap; }
    .reg-badge::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--muted); }
    .reg-root { --c-ms: #18a058; --c-uwp: #f97316; --c-mk: #eab308; --c-off: #8f8f8f; }
    html[data-theme="dark"] .reg-root { --c-ms: #3ecf8e; --c-uwp: #fb923c; --c-mk: #facc15; --c-off: #a1a1a1; }
    .reg-badge { border-color: color-mix(in srgb, var(--st, var(--c-off)) 40%, var(--line)); background: color-mix(in srgb, var(--st, var(--c-off)) 10%, transparent); }
    .reg-badge::before { background: var(--st, var(--c-off)); }
    .reg-badge.is-ms { --st: var(--c-ms); }
    .reg-badge.is-uwp { --st: var(--c-uwp); }
    .reg-badge.is-mk { --st: var(--c-mk); }
    .reg-badge.is-transferred { --st: var(--warn); }
    .reg-badge.is-inactive { --st: var(--c-off); }

    .reg-collapsed { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-top: 1rem; padding: .65rem .9rem; border: 1px solid var(--line); border-radius: var(--r); background: var(--soft); color: var(--muted); font-size: .82rem; }

    /* Record panel */
    .reg-band { display: flex; align-items: flex-start; gap: .85rem; padding: 0 0 1rem; border-bottom: 1px solid var(--line); }
    .reg-band .reg-avatar { width: 44px; height: 44px; background: var(--ink); border-color: var(--ink); color: var(--card); }
    .reg-band-text { flex: 1; min-width: 0; }
    .reg-name { font-size: 1.1rem; font-weight: 600; letter-spacing: -.02em; overflow-wrap: anywhere; }
    .reg-regno { margin-top: .1rem; color: var(--muted); font-size: .82rem; font-family: ui-monospace, SFMono-Regular, 'Geist Mono', Consolas, monospace; }
    .reg-band-meta { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-top: .5rem; }
    .reg-mode { padding: .15rem .55rem; border: 1px solid var(--line); border-radius: var(--r); color: var(--muted); font-size: .74rem; font-weight: 500; }
    .reg-editor[data-mode="edit"] .reg-mode { border-color: var(--ink); background: var(--ink); color: var(--card); }
    .reg-close { display: inline-flex; align-items: center; gap: .4rem; flex: none; height: 30px; padding: 0 .7rem; border: 1px solid var(--line); border-radius: var(--r); background: transparent; color: var(--ink); font: inherit; font-size: .78rem; font-weight: 500; cursor: pointer; transition: background .12s ease, border-color .12s ease; }
    .reg-close:hover { background: var(--hover); border-color: var(--muted); }

    .reg-alert { margin-top: 1rem; padding: .7rem .9rem; border: 1px solid var(--line); border-left: 3px solid var(--warn); border-radius: var(--r); background: var(--soft); font-size: .83rem; line-height: 1.55; }
    .reg-regdup { margin-top: .5rem; padding: .7rem .8rem; border: 1px solid var(--bad); border-left-width: 3px; border-radius: var(--r); background: color-mix(in srgb, var(--bad) 8%, var(--card)); font-size: .82rem; line-height: 1.5; }
    .reg-regdup strong { display: block; margin-bottom: .4rem; color: var(--bad); font-weight: 600; }
    .regdup-item { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; padding: .45rem 0; border-top: 1px solid var(--line); }
    .regdup-item:first-of-type { border-top: 0; }
    .regdup-item .who { flex: 1; min-width: 140px; }
    .regdup-item .who b { display: block; font-weight: 600; }
    .regdup-item .who small { color: var(--muted); }

    .reg-body, .reg-review { padding: 1rem 0 .25rem; }
    .reg-body h3, .reg-review h3 { margin-bottom: .8rem; color: var(--ink); font-size: .85rem; font-weight: 600; }
    .reg-locked-title { margin-top: 1.5rem !important; padding-top: 1rem; border-top: 1px solid var(--line); }
    .reg-locked-title small { margin-left: .4rem; color: var(--muted); font-size: .74rem; font-weight: 400; }
    .reg-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem; }
    .span2 { grid-column: 1 / -1; }
    .f label { display: block; margin-bottom: .3rem; color: var(--muted); font-size: .76rem; font-weight: 500; }
    .f input, .f select { width: 100%; min-height: 38px; padding: .45rem .7rem; border: 1px solid var(--line); border-radius: var(--r); background: var(--card); color: var(--ink); font: inherit; font-size: .88rem; transition: border-color .12s ease, box-shadow .12s ease; }
    .f input:hover:not(:disabled), .f select:hover:not(:disabled) { border-color: var(--muted); }
    .f input:focus, .f select:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 1px var(--ink); }
    /* View mode: read-only look, no input chrome */
    .reg-editor[data-mode="view"] .f input:disabled,
    .reg-editor[data-mode="view"] .f select:disabled { border-color: transparent; background: var(--soft); color: var(--ink); opacity: 1; -webkit-text-fill-color: var(--ink); cursor: default; appearance: none; -webkit-appearance: none; }
    .reg-editor[data-mode="edit"] .f input:disabled { background: var(--soft); color: var(--muted); -webkit-text-fill-color: var(--muted); cursor: not-allowed; }
    .f input[type="checkbox"] { width: 16px; min-height: 0; height: 16px; padding: 0; accent-color: var(--ink); }
    .f .check { display: flex; align-items: center; gap: .55rem; margin: 0; min-height: 38px; color: var(--ink); font-size: .88rem; font-weight: 400; }
    .f .check small { color: var(--muted); font-size: .74rem; }
    .f.is-changed label { color: var(--ink); }
    .f.is-changed label::after { content: " (edited)"; color: var(--muted); font-weight: 400; }
    .f.is-changed input, .f.is-changed select { border-color: var(--ink); }
    .f.has-error input, .f.has-error select { border-color: var(--bad); }
    .hint { margin-top: .25rem; color: var(--muted); font-size: .74rem; line-height: 1.45; }
    .hint.is-bad { color: var(--bad); }
    .reg-editor[data-mode="view"] .hint:not(.keep) { display: none; }
    .err { margin-top: .25rem; color: var(--bad); font-size: .78rem; line-height: 1.4; }
    .err:empty { display: none; }

    .reg-locks { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
    .lock { display: grid; gap: .2rem; padding: .7rem .8rem; border: 1px solid var(--line); border-radius: var(--r); background: var(--soft); }
    .lock-k { color: var(--muted); font-size: .74rem; font-weight: 500; }
    .lock-v { display: flex; align-items: center; gap: .5rem; font-size: .88rem; font-weight: 500; }
    .reg-eye { margin-left: auto; padding: .15rem .6rem; border: 1px solid var(--line); border-radius: var(--r); background: var(--card); color: var(--ink); font: inherit; font-size: .74rem; font-weight: 500; cursor: pointer; }
    .reg-eye:hover:not(:disabled) { border-color: var(--ink); }
    .reg-eye:disabled { opacity: .45; cursor: not-allowed; }

    /* Review step */
    .reg-review { display: none; }
    .reg-editor.is-review .reg-body { display: none; }
    .reg-editor.is-review .reg-review { display: block; }
    .reg-review ul { display: grid; gap: .5rem; }
    .reg-review li { display: grid; gap: .15rem; padding: .6rem .8rem; border: 1px solid var(--line); border-left: 3px solid var(--ink); border-radius: var(--r); font-size: .88rem; }
    .reg-review .k { color: var(--muted); font-size: .74rem; font-weight: 500; }
    .reg-review .from { color: var(--muted); text-decoration: line-through; }
    .reg-review .to { font-weight: 600; }

    /* Actions */
    .reg-actions { display: flex; align-items: center; gap: .6rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--line); }
    .reg-actions .act { display: none; gap: .5rem; margin-left: auto; }
    .reg-editor[data-mode="view"]:not(.is-review) .act-view,
    .reg-editor[data-mode="edit"]:not(.is-review) .act-edit,
    .reg-editor.is-review .act-review { display: flex; }
    .reg-save-msg { color: var(--ok); font-size: .83rem; font-weight: 500; }

    /* Smart Helper */
    .helper-icon { display: grid; place-items: center; flex: none; width: 36px; height: 36px; border-radius: var(--r); background: var(--ink); color: var(--card); }
    .helper-icon svg { width: 20px; height: 20px; }
    .helper-title { flex: 1; min-width: 0; }
    .helper-state { display: inline-flex; align-items: center; gap: .4rem; color: var(--muted); font-size: .74rem; font-weight: 500; }
    .helper-state i { width: 7px; height: 7px; border-radius: 50%; background: var(--ok); }
    .helper-toggle { display: grid; place-items: center; flex: none; width: 30px; height: 30px; border: 1px solid var(--line); border-radius: var(--r); background: transparent; color: var(--muted); cursor: pointer; transition: color .12s ease, border-color .12s ease; }
    .helper-toggle:hover { color: var(--ink); border-color: var(--muted); }
    .helper-toggle svg { transition: transform .15s ease; }
    .helper-toggle.is-collapsed svg { transform: rotate(-90deg); }
    .helper:has(.helper-panel[hidden]) .helper-head { border-bottom: 0; }
    .helper-panel { display: flex; flex-direction: column; min-width: 0; }
    /* Fixed height: new messages scroll inside the thread and never resize the page */
    .thread { display: grid; align-content: start; gap: .8rem; height: clamp(260px, 45vh, 440px); padding: 1rem; overflow-y: auto; background: var(--soft); overscroll-behavior: contain; }
    .msg { display: grid; gap: .3rem; max-width: 94%; }
    .msg-who { color: var(--muted); font-size: .72rem; font-weight: 500; }
    .msg-body { padding: .75rem .9rem; border: 1px solid var(--line); border-radius: var(--r-lg); background: var(--card); font-size: .85rem; line-height: 1.6; }
    .msg-body p + p { margin-top: .5rem; }
    .msg-user { justify-self: end; text-align: right; }
    .msg-user .msg-body { border-color: var(--ink); background: var(--ink); color: var(--card); }
    .msg-bot:has(.finding) { max-width: 100%; }
    .msg-error { color: var(--bad); }
    .msg-ok { font-weight: 600; }
    .findings { display: grid; gap: .6rem; margin-top: .7rem; }
    .finding { padding: .75rem .85rem; border: 1px solid var(--line); border-left: 3px solid var(--ink); border-radius: var(--r); background: var(--soft); text-align: left; }
    .finding h3 { margin-bottom: .5rem; font-size: .83rem; font-weight: 600; }
    .finding ul { display: grid; gap: .5rem; }
    .finding li { display: grid; gap: .1rem; padding-top: .5rem; border-top: 1px solid var(--line); color: var(--muted); font-size: .8rem; }
    .finding li:first-child { padding-top: 0; border-top: 0; }
    .finding li strong { color: var(--ink); font-size: .85rem; }
    .finding-actions { margin-top: .3rem; }
    .finding-delete { padding: .3rem .65rem; border: 1px solid var(--bad); border-radius: var(--r); background: transparent; color: var(--bad); font: inherit; font-size: .74rem; font-weight: 500; cursor: pointer; transition: background .12s ease, color .12s ease; }
    .finding-delete:hover:not(:disabled) { background: var(--bad); color: #fff; }
    .finding-delete:disabled { opacity: .6; cursor: wait; }
    .finding-note { margin-top: .5rem; color: var(--warn); font-size: .76rem; line-height: 1.45; }
    .helper-foot { display: flex; align-items: center; gap: .8rem; padding: .85rem 1rem; border-top: 1px solid var(--line); }
    .helper-foot [role="status"] { flex: 1; min-height: 2.1em; color: var(--muted); font-size: .8rem; line-height: 1.4; }

    /* Confirmation dialog */
    .reg-modal { position: fixed; inset: 0; z-index: 99999; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(0,0,0,.6); }
    .reg-dialog { width: min(100%, 420px); padding: 1.25rem 1.4rem; border: 1px solid var(--line); border-radius: var(--r-lg); background: var(--card); color: var(--ink); box-shadow: 0 24px 60px rgba(0,0,0,.45); }
    .reg-dialog h2 { font-size: 1rem; font-weight: 600; letter-spacing: -.01em; }
    .reg-dialog p { margin-top: .4rem; color: var(--muted); font-size: .86rem; line-height: 1.55; }
    .dlg-actions { display: flex; justify-content: flex-end; gap: .5rem; margin-top: 1.25rem; }

    /* Layout: Smart Helper closed = full-width workspace; open = workspace left, helper right */
    .reg-head-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
    .reg-root #helper-open.is-on { border-color: var(--ink); background: var(--ink); color: var(--card); }
    .reg-layout { display: block; }
    .reg-main { min-width: 0; }
    /* Search and Record are always one column: Search on top, Record below, full width */
    .reg-root .reg-workspace { grid-template-columns: minmax(0, 1fr); }
    .reg-root .rp-record { position: static; max-height: none; }
    .reg-root .rp-record .rp-body { overflow: visible; }
    .reg-root.helper-open .reg-layout { display: grid; grid-template-columns: minmax(0, 1fr) minmax(340px, 420px); gap: 1rem; align-items: start; }
    .reg-root.helper-open .reg-workspace { grid-template-columns: minmax(0, 1fr); margin-bottom: 0; }
    .reg-root.helper-open .rp-record { position: static; max-height: none; }
    .reg-root.helper-open .rp-record .rp-body { overflow: visible; }
    .reg-root .helper { position: sticky; top: 1rem; display: flex; flex-direction: column; max-height: calc(100vh - 2rem); }
    .reg-root .helper-panel { flex: 1; min-height: 0; }
    .reg-root .thread { flex: 1; height: auto; min-height: 240px; }
    @media (max-width: 1100px) {
        .reg-root.helper-open .reg-layout { grid-template-columns: 1fr; }
        .reg-root .helper { position: static; max-height: none; }
        .reg-root .thread { flex: none; height: 420px; }
    }

    /* Snack */
    .snack { position: fixed; z-index: 100000; left: 50%; bottom: max(1.4rem, env(safe-area-inset-bottom)); width: max-content; max-width: calc(100vw - 2rem); padding: .65rem 1.1rem; border-radius: var(--r); background: var(--ink); color: var(--card); font-size: .86rem; font-weight: 500; text-align: center; overflow-wrap: anywhere; transform: translate(-50%, 160%); transition: transform .3s cubic-bezier(.2,.8,.2,1); pointer-events: none; }
    .snack.show { transform: translate(-50%, 0); }
    .snack.is-error { background: var(--bad); color: #fff; }

    @media (prefers-reduced-motion: reduce) { .reg-root *, .reg-root *::before { transition: none !important; animation: none !important; } }

    @media (max-width: 1100px) {
        .reg-workspace { grid-template-columns: 1fr; }
        .rp-record { position: static; max-height: none; }
        .rp-record .rp-body { overflow: visible; }
        .reg-guide-grid { grid-template-columns: 1fr; }
        .reg-guide-cell { border-right: 0; border-bottom: 1px solid var(--line); }
        .reg-guide-cell:last-child { border-bottom: 0; }
    }
    @media (max-width: 760px) {
        .col-kap, .col-place { display: none; }
        .rp-body { padding: .9rem; }
        .reg-grid, .reg-locks { grid-template-columns: 1fr; }
        .reg-band { flex-wrap: wrap; }
        .reg-actions { flex-wrap: wrap; }
        .reg-actions .act { width: 100%; }
        .reg-actions .act .btn { flex: 1; }
        .helper-foot { flex-direction: column; align-items: stretch; }
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const $ = id => document.getElementById(id);
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const SEARCH_URL = @json(route('records.search'));
    const SMART_HELPER_URL = @json(route('records.smart-helper'));
    const DELETE_URL = @json(route('records.destroy', ['record' => '__RECORD_ID__']));
    const UPDATE_URL = @json(route('records.update', ['record' => '__RECORD_ID__']));
    const REGISTRY_LENGTH = 13;
    const PAGE_SIZE = 10;

    /* ---------------- Elements + helpers ---------------- */
    const FIELDS = {
        name: ['rm-name', 'Name'], registry_number: ['rm-registry', 'Registry number'], gender: ['rm-gender', 'Gender'],
        date_of_birth: ['rm-birthday', 'Birthday'], is_married: ['rm-married', 'Married'],
        kapisanan: ['rm-kapisanan', 'Kapisanan'], grupo: ['rm-grupo', 'Grupo'],
        date_of_baptism: ['rm-baptism', 'Date of baptism'], evangelist: ['rm-evangelist', 'Evangelist'],
    };
    const input = k => $(FIELDS[k][0]);
    const editor = $('reg-editor'), form = $('reg-form');
    const placeholder = $('reg-placeholder');
    const emptyState = $('reg-empty'), listWrap = $('reg-list'), rowsEl = $('reg-rows');
    const searchInput = $('reg-search-input'), searchBtn = $('reg-search-btn'), errBox = $('reg-search-error');
    const csrf = () => form.querySelector('input[name="_token"]').value;

    let current = null, original = null, mode = 'view';
    let r210 = '', r210Shown = false;
    let searchMatches = [], lastQuery = '';
    let statusFilter = 'all', page = 1, loadingList = false;

    const initials = n => String(n || '').split(/\s+/).filter(Boolean).map((w, i, a) => (i === 0 || i === a.length - 1) ? w[0] : '').join('').toUpperCase() || '--';
    const fmtDate = iso => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '--';
    const statusKey = s => (s || '').trim().toLowerCase();
    const statusClass = s => ({ ms: 'is-ms', uwp: 'is-uwp', mk: 'is-mk', active: 'is-ms', transferred: 'is-transferred', inactive: 'is-inactive' }[statusKey(s)] || 'is-inactive');
    const norm = s => String(s ?? '').toLowerCase().replace(/\s+/g, ' ').trim();
    const normReg = s => String(s ?? '').trim().toUpperCase();
    const place = m => [m.purok ? `Purok ${m.purok}` : '', m.grupo ? `Grupo ${m.grupo}` : ''].filter(Boolean).join(', ') || '--';
    const readVal = k => input(k).type === 'checkbox' ? (input(k).checked ? '1' : '0') : String(input(k).value).trim();
    const setVal = (k, value) => {
        if (input(k).type === 'checkbox') input(k).checked = value === true || value === 1 || value === '1' || value === 'true';
        else input(k).value = value ?? '';
    };
    function el(tag, cls, text) { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
    function badge(status) { return el('span', 'reg-badge ' + statusClass(status), status || 'Unknown'); }

    let snackTimer;
    function snack(text, isError) {
        const s = $('snack');
        s.textContent = text;
        s.classList.toggle('is-error', Boolean(isError));
        s.classList.add('show');
        clearTimeout(snackTimer);
        snackTimer = setTimeout(() => s.classList.remove('show'), isError ? 6000 : 3600);
    }

    /* ---------------- Confirmation dialog (replaces window.confirm) ---------------- */
    const modal = $('reg-modal'), dlgOk = $('dlg-ok'), dlgCancel = $('dlg-cancel');
    let dlgResolve = null, dlgReturnFocus = null;
    function confirmDialog({ title, message, confirmText = 'Confirm', cancelText = 'Cancel', danger = false }) {
        return new Promise(resolve => {
            dlgResolve = resolve;
            dlgReturnFocus = document.activeElement;
            $('dlg-title').textContent = title;
            $('dlg-msg').textContent = message;
            dlgOk.textContent = confirmText;
            dlgCancel.textContent = cancelText;
            dlgOk.className = 'btn ' + (danger ? 'btn-danger' : 'btn-ink');
            modal.hidden = false;
            (danger ? dlgCancel : dlgOk).focus();
        });
    }
    function closeDialog(value) {
        if (modal.hidden) return;
        modal.hidden = true;
        const resolve = dlgResolve; dlgResolve = null;
        if (dlgReturnFocus && dlgReturnFocus.focus) dlgReturnFocus.focus();
        if (resolve) resolve(value);
    }
    dlgOk.addEventListener('click', () => closeDialog(true));
    dlgCancel.addEventListener('click', () => closeDialog(false));
    modal.addEventListener('click', e => { if (e.target === modal) closeDialog(false); });
    document.addEventListener('keydown', e => {
        if (modal.hidden) return;
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); closeDialog(false); }
        if (e.key === 'Tab') {
            const items = [dlgCancel, dlgOk];
            const i = items.indexOf(document.activeElement);
            e.preventDefault();
            items[(i + (e.shiftKey ? -1 : 1) + items.length) % items.length].focus();
        }
    }, true);

    /* ---------------- Guidance ---------------- */
    const guide = $('reg-guide'), guideBtn = $('remind-open');
    function setGuide(open) { guide.hidden = !open; guideBtn.setAttribute('aria-expanded', String(open)); }
    guideBtn.addEventListener('click', () => setGuide(guide.hidden));
    $('rem-ok').addEventListener('click', () => { setGuide(false); guideBtn.focus(); });

    /* ---------------- Results list: idle / loading / results ---------------- */
    function showList(stage) {
        emptyState.hidden = stage !== 'idle';
        listWrap.hidden = stage === 'idle';
        $('list-sub').textContent = stage === 'results'
            ? 'Select a row to view the record.'
            : 'Type at least 2 characters of a name or registry number.';
    }
    showList('idle');

    // Collapse the results table while a record is open; Close or "Show results" brings it back.
    function setListCollapsed(on) {
        $('reg-table-area').hidden = on;
        $('reg-collapsed').hidden = !on;
        if (on) {
            $('reg-collapsed-text').textContent = `${searchMatches.length} ${searchMatches.length === 1 ? 'record' : 'records'} for "${lastQuery}" (hidden while a record is open)`;
        }
    }
    $('reg-show-results').addEventListener('click', () => setListCollapsed(false));

    function visibleMatches() {
        return statusFilter === 'all' ? searchMatches : searchMatches.filter(m => statusKey(m.status) === statusFilter);
    }

    function renderSkeleton() {
        rowsEl.setAttribute('aria-busy', 'true');
        rowsEl.replaceChildren(...Array.from({ length: 5 }, () => {
            const tr = el('tr');
            const who = el('td'); const wrap = el('div', 'cell-who');
            wrap.append(el('span', 'skel skel-av'), el('span', 'skel')); wrap.lastChild.style.width = '120px';
            who.append(wrap);
            tr.append(who);
            [90, 70, 80, 70].forEach((w, i) => {
                const td = el('td', i === 2 ? 'col-kap' : i === 3 ? 'col-place' : '');
                const s = el('span', 'skel'); s.style.width = w + 'px'; td.append(s); tr.append(td);
            });
            tr.append(el('td', 'col-act'));
            return tr;
        }));
        $('reg-count').textContent = 'Searching...';
        $('reg-table-empty').hidden = true;
        $('reg-pager').hidden = true;
    }

    function renderList() {
        if (loadingList) return;
        rowsEl.setAttribute('aria-busy', 'false');
        const all = searchMatches;
        const counts = { all: all.length, ms: 0, uwp: 0, mk: 0, inactive: 0 };
        all.forEach(m => { const k = statusKey(m.status); if (k in counts) counts[k]++; });
        document.querySelectorAll('#reg-chips .chip').forEach(chip => {
            chip.querySelector('span').textContent = counts[chip.dataset.filter] ?? 0;
            const on = chip.dataset.filter === statusFilter;
            chip.classList.toggle('is-on', on);
            chip.setAttribute('aria-pressed', String(on));
        });

        const list = visibleMatches();
        const pages = Math.max(1, Math.ceil(list.length / PAGE_SIZE));
        page = Math.min(page, pages);
        const start = (page - 1) * PAGE_SIZE;
        const slice = list.slice(start, start + PAGE_SIZE);
        const dupNames = new Set(all.filter((m, i) => all.findIndex(x => norm(x.name) === norm(m.name)) !== i).map(m => norm(m.name)));

        $('reg-count').textContent = `${all.length} ${all.length === 1 ? 'record' : 'records'} for "${lastQuery}"`;
        $('reg-table-empty').hidden = list.length > 0;
        $('reg-table').hidden = list.length === 0;

        rowsEl.replaceChildren(...slice.map(m => {
            const tr = el('tr', 'reg-row' + (current && current.id === m.id ? ' is-selected' : ''));
            tr.dataset.id = m.id;
            if (current && current.id === m.id) tr.setAttribute('aria-selected', 'true');

            const who = el('td'); const wrap = el('div', 'cell-who');
            const nameWrap = el('div', 'cell-name');
            const nameBtn = el('button', '', m.name || 'Name unavailable'); nameBtn.type = 'button';
            nameWrap.append(nameBtn);
            if (dupNames.has(norm(m.name))) nameWrap.append(el('span', 'reg-dupchip', 'Same name'));
            wrap.append(el('span', 'reg-avatar', initials(m.name)), nameWrap);
            who.append(wrap);

            const reg = el('td', 'cell-reg', m.registry_number || '--');
            const st = el('td'); st.append(badge(m.status));
            const kap = el('td', 'col-kap', m.kapisanan || '--');
            const pl = el('td', 'col-place', place(m));
            const act = el('td', 'col-act');
            const open = el('button', 'btn btn-ghost btn-sm', 'Open'); open.type = 'button';
            open.setAttribute('aria-label', `Open record for ${m.name}`);
            act.append(open);
            tr.append(who, reg, st, kap, pl, act);
            return tr;
        }));

        const pager = $('reg-pager');
        pager.hidden = list.length <= PAGE_SIZE;
        $('reg-pager-info').textContent = list.length
            ? `Showing ${start + 1}-${Math.min(start + PAGE_SIZE, list.length)} of ${list.length}` : '';
        $('reg-prev').disabled = page <= 1;
        $('reg-next').disabled = page >= pages;
    }

    $('reg-chips').addEventListener('click', e => {
        const chip = e.target.closest('.chip');
        if (!chip) return;
        statusFilter = chip.dataset.filter; page = 1; renderList();
    });
    $('reg-prev').addEventListener('click', () => { page--; renderList(); });
    $('reg-next').addEventListener('click', () => { page++; renderList(); });
    rowsEl.addEventListener('click', e => {
        const tr = e.target.closest('tr.reg-row');
        if (!tr) return;
        const m = searchMatches.find(x => String(x.id) === tr.dataset.id);
        if (m) requestOpen(m);
    });

    /* ---------------- Search (debounced, abortable) ---------------- */
    let searchTimer = null, activeSearch = null, searchSequence = 0;
    function searchFail(msg) { errBox.textContent = msg; errBox.hidden = false; }

    async function fetchRecords(q, signal) {
        const response = await fetch(`${SEARCH_URL}?q=${encodeURIComponent(q)}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal,
        });
        const body = await response.json();
        if (!response.ok) throw new Error(body.message || 'Unable to search records.');
        return body.data || [];
    }

    async function runSearch(raw, showEmptyError = true) {
        const q = norm(raw ?? searchInput.value);
        errBox.hidden = true;
        if (!q) {
            showList('idle');
            if (showEmptyError) searchFail('Enter a name or registry number.');
            return;
        }
        if (q.length < 2) { showList('idle'); return; }

        activeSearch?.abort();
        activeSearch = new AbortController();
        const sequence = ++searchSequence;
        searchBtn.disabled = true; searchBtn.textContent = 'Searching...';
        loadingList = true; setListCollapsed(false); showList('loading'); renderSkeleton();
        try {
            const found = await fetchRecords(q, activeSearch.signal);
            if (sequence !== searchSequence) return;
            loadingList = false;
            if (!found.length) {
                searchMatches = []; showList('idle');
                return searchFail('No matching record found. Check the spelling or try the registry number.');
            }
            searchMatches = found; lastQuery = q; statusFilter = 'all'; page = 1;
            showList('results'); renderList();
        } catch (error) {
            if (error.name === 'AbortError') return;
            if (sequence !== searchSequence) return;
            console.error(error);
            loadingList = false; showList('idle');
            searchFail(error.message || 'Unable to search records. Please try again.');
        } finally {
            if (sequence === searchSequence) {
                loadingList = false;
                searchBtn.disabled = false; searchBtn.textContent = 'Search';
            }
        }
    }
    function scheduleSearch() {
        clearTimeout(searchTimer);
        activeSearch?.abort(); activeSearch = null; searchSequence++;
        loadingList = false;
        searchBtn.disabled = false; searchBtn.textContent = 'Search';
        errBox.hidden = true;
        if (!searchInput.value.trim()) { showList('idle'); return; }
        searchTimer = setTimeout(() => runSearch(searchInput.value, false), 300);
    }
    searchBtn.addEventListener('click', () => { clearTimeout(searchTimer); runSearch(searchInput.value); });
    searchInput.addEventListener('input', scheduleSearch);
    searchInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); clearTimeout(searchTimer); runSearch(searchInput.value); }
    });

    // Re-read the last search so the table reflects what the server actually saved.
    async function refreshResults() {
        if (!lastQuery) return;
        try {
            const fresh = await fetchRecords(lastQuery);
            searchMatches = fresh;
            const match = current ? fresh.find(x => x.id === current.id) : null;
            if (match && mode === 'view' && !editor.classList.contains('is-review')) {
                fillRecord({ ...current, ...match });
                refreshKapisanan(); applyMode(); clearRegDup();
            }
            renderList();
        } catch (e) { /* keep what is on screen; the next search will refresh it */ }
    }

    /* ---------------- Record panel: fill, mode, open, close ---------------- */
    function fillRecord(m) {
        current = m;
        original = { ...m, is_married: m.is_married ? 1 : 0 };
        Object.keys(FIELDS).forEach(k => setVal(k, original[k]));
        $('rm-id').value = m.id;
        $('rm-avatar').textContent = initials(m.name);
        $('rm-name-display').textContent = m.name;
        $('rm-registry-display').textContent = m.registry_number || '--';
        const b = $('rm-status-badge'); b.textContent = m.status || 'Unknown'; b.className = 'reg-badge ' + statusClass(m.status);
        $('rm-status-text').textContent = m.status || '--';
        $('rm-purok-display').textContent = m.purok_name || (m.purok ? `Purok ${m.purok}` : '--');
        $('rm-entry-display').textContent = fmtDate(m.date_of_entry);
        r210 = m.r210_eligible || '--'; r210Shown = false;
        $('rm-r210-display').textContent = '••••••••••'; $('rm-r210-toggle').textContent = 'Show';
        input('grupo').min = m.grupo_min ?? 1;
        input('grupo').max = m.grupo_max ?? 24;
        $('rm-grupo-hint').textContent = `Grupo must stay between ${input('grupo').min} and ${input('grupo').max}${m.purok_name ? ` in ${m.purok_name}` : ' in the current Purok'}.`;
        updateRegistryHint();
    }

    function applyMode() {
        const editing = mode === 'edit';
        editor.dataset.mode = mode;
        Object.keys(FIELDS).forEach(k => { input(k).disabled = !editing; });
        if (editing) refreshKapisanan();
        $('rm-mode').textContent = editing ? 'Editing' : 'Viewing';
        $('rm-section-title').textContent = editing ? 'Edit details' : 'Details';
        $('record-sub').textContent = editor.classList.contains('is-review')
            ? 'Check each change, then confirm.'
            : editing ? 'Change the fields you need, then review and save.' : 'Read-only. Choose Edit record to make changes.';
        updateDirty();
    }
    function setMode(next) { mode = next; editor.classList.remove('is-review'); applyMode(); }

    function showRecordPanel(on) {
        editor.hidden = !on;
        placeholder.hidden = on;
        if (!on) $('record-sub').textContent = 'Select a record from the results to open it.';
    }

    function isDirty() { return mode === 'edit' && changes().length > 0; }

    async function discardGuard(message) {
        if (!isDirty()) return true;
        return confirmDialog({
            title: 'Discard unsaved changes?',
            message: message || 'You have edits that are not saved. They will be lost.',
            confirmText: 'Discard changes', cancelText: 'Keep editing', danger: true,
        });
    }

    async function requestOpen(m) {
        if (current && current.id === m.id) { focusRecord(); return; }
        if (!(await discardGuard('Opening another record will discard the edits you have not saved.'))) return;
        openMember(m);
    }

    function openMember(m) {
        fillRecord(m);
        refreshKapisanan();
        const twins = searchMatches.filter(x => x.id !== m.id && norm(x.name) === norm(m.name));
        const dup = $('rm-dup');
        dup.hidden = !twins.length;
        if (twins.length) {
            const matches = twins.map(t => `${t.registry_number || 'no registry'} (${t.status || 'status unavailable'})`).join('; ');
            dup.textContent = `Name match only. This is a warning, not an error. Other record${twins.length === 1 ? '' : 's'} with this name: ${matches}. Check the registry number and status before treating it as a duplicate.`;
        }
        clearErrors(); clearRegDup();
        $('reg-save-msg').textContent = '';
        showRecordPanel(true);
        setMode('view');
        renderList();
        setListCollapsed(true);
        focusRecord();
    }
    function focusRecord() {
        if (window.matchMedia('(max-width: 1100px)').matches) {
            $('reg-record').scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
        }
        setTimeout(() => $('reg-edit-btn').focus({ preventScroll: true }), 60);
    }

    async function closeRecord() {
        if (editor.hidden) return;
        if (!(await discardGuard())) return;
        current = null; original = null; mode = 'view';
        editor.classList.remove('is-review');
        showRecordPanel(false);
        setListCollapsed(false);
        renderList();
        searchInput.focus();
    }
    $('reg-modal-close').addEventListener('click', closeRecord);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.hidden && !editor.hidden && editor.contains(document.activeElement)) closeRecord();
    });

    $('reg-edit-btn').addEventListener('click', () => {
        setMode('edit');
        setTimeout(() => input('name').focus({ preventScroll: true }), 30);
    });
    $('reg-cancel-btn').addEventListener('click', async () => {
        if (!(await discardGuard())) return;
        Object.keys(FIELDS).forEach(k => setVal(k, original[k]));
        clearErrors(); clearRegDup(); setMode('view');
        $('reg-edit-btn').focus();
    });
    $('rm-r210-toggle').addEventListener('click', () => {
        r210Shown = !r210Shown;
        $('rm-r210-display').textContent = r210Shown ? r210 : '••••••••••';
        $('rm-r210-toggle').textContent = r210Shown ? 'Hide' : 'Show';
    });

    /* ---------------- Change tracking ---------------- */
    function normalizedValue(key, value) {
        const normalized = String(value ?? '').trim();
        if (key === 'registry_number') return normalized.toUpperCase();
        if (key === 'is_married') return normalized === '1' || normalized.toLowerCase() === 'true' ? '1' : '0';
        if (key === 'grupo' && normalized !== '') {
            const number = Number(normalized);
            if (Number.isFinite(number)) return String(number);
        }
        return normalized;
    }
    function refreshKapisanan() {
        const birthday = readVal('date_of_birth');
        const kapisanan = input('kapisanan');
        const hint = $('rm-kapisanan-hint');
        if ($('rm-married').checked) {
            kapisanan.value = 'Buklod'; kapisanan.disabled = true;
            hint.textContent = 'Set automatically to Buklod because Married is checked.';
            return;
        }
        if (!birthday) {
            kapisanan.disabled = mode !== 'edit';
            hint.textContent = 'Enter a birthday or mark Married to set this automatically; otherwise type it.';
            return;
        }
        const [year, month, day] = birthday.split('-').map(Number);
        const today = new Date();
        let age = today.getFullYear() - year;
        if (today.getMonth() + 1 < month || (today.getMonth() + 1 === month && today.getDate() < day)) age--;
        kapisanan.value = age < 18 ? 'Binhi' : 'Kadiwa';
        kapisanan.disabled = true;
        hint.textContent = `Set automatically to ${kapisanan.value} from the birthday.`;
    }
    function changes() {
        if (!original) return [];
        return Object.keys(FIELDS).filter(k => normalizedValue(k, readVal(k)) !== normalizedValue(k, original[k]))
            .map(k => ({ key: k, label: FIELDS[k][1], from: String(original[k] ?? '') || '(empty)', to: readVal(k) || '(empty)' }));
    }
    function updateDirty() {
        const list = changes(), keys = new Set(list.map(c => c.key));
        Object.keys(FIELDS).forEach(k => input(k).closest('.f').classList.toggle('is-changed', mode === 'edit' && keys.has(k)));
        $('reg-save-btn').disabled = !list.length;
        $('reg-reset-btn').disabled = !list.length;
        $('reg-save-btn').textContent = list.length ? `Save changes (${list.length})` : 'Save changes';
    }

    /* ---------------- Registry number: length hint + duplicate warning ---------------- */
    function updateRegistryHint() {
        const hint = $('rm-registry-hint');
        const len = readVal('registry_number').length;
        hint.className = 'hint keep' + (len !== REGISTRY_LENGTH && mode === 'edit' ? ' is-bad' : '');
        hint.textContent = `${len}/${REGISTRY_LENGTH} characters. The registry number must be exactly ${REGISTRY_LENGTH} characters.`;
    }
    function clientRegistryDuplicates() {
        const reg = normReg(readVal('registry_number'));
        if (!reg || !current) return [];
        return searchMatches.filter(x => x.id !== current.id && normReg(x.registry_number) === reg);
    }
    function clearRegDup() { const p = $('rm-regdup'); p.hidden = true; p.replaceChildren(); }
    // list: records the server or the visible results say already use this registry number
    function renderRegDup(list) {
        const panel = $('rm-regdup');
        if (!list.length) { clearRegDup(); return; }
        panel.replaceChildren(el('strong', '', `This registry number is already used by ${list.length === 1 ? 'another record' : list.length + ' other records'}.`));
        list.forEach(r => {
            const item = el('div', 'regdup-item');
            const who = el('div', 'who');
            who.append(el('b', '', r.name || 'Name unavailable'), el('small', '', `${r.registry_number || '--'} · ${r.status || 'status unavailable'}`));
            const open = el('button', 'btn btn-ghost btn-sm', 'Open this record'); open.type = 'button';
            open.addEventListener('click', () => openById(r));
            item.append(who, badge(r.status), open);
            panel.append(item);
        });
        panel.hidden = false;
    }
    async function openById(ref) {
        let m = searchMatches.find(x => x.id === ref.id);
        if (!m) {
            try {
                const found = await fetchRecords(ref.registry_number || ref.name || '');
                m = found.find(x => x.id === ref.id);
                if (m) { searchMatches = found; lastQuery = norm(ref.registry_number || ref.name); statusFilter = 'all'; page = 1; showList('results'); }
            } catch (e) { /* handled below */ }
        }
        if (!m) { snack('That record could not be opened. Search for it by registry number.', true); return; }
        requestOpen(m);
    }

    form.addEventListener('input', () => { clearErrors(); updateDirty(); updateRegistryHint(); });
    form.addEventListener('change', () => { clearErrors(); updateDirty(); });
    $('rm-registry').addEventListener('input', () => {
        const el2 = $('rm-registry');
        const pos = el2.selectionStart;
        el2.value = el2.value.toUpperCase();
        try { el2.setSelectionRange(pos, pos); } catch (e) { /* not supported for this input */ }
        updateRegistryHint();
        renderRegDup(clientRegistryDuplicates());
    });
    $('rm-married').addEventListener('change', () => { refreshKapisanan(); updateDirty(); });
    $('rm-birthday').addEventListener('change', () => { refreshKapisanan(); updateDirty(); });
    $('reg-reset-btn').addEventListener('click', () => {
        Object.keys(FIELDS).forEach(k => setVal(k, original[k]));
        refreshKapisanan(); clearErrors(); clearRegDup(); updateDirty(); updateRegistryHint();
    });

    /* ---------------- Validation (UI hints only; the server stays authoritative) ---------------- */
    function clearErrors() {
        form.querySelectorAll('[data-err]').forEach(p => { p.textContent = ''; });
        form.querySelectorAll('.f').forEach(f => f.classList.remove('has-error'));
        $('reg-review-err').textContent = '';
    }
    function showFieldErrors(errs) {
        Object.entries(errs).forEach(([k, msg]) => {
            const p = form.querySelector(`[data-err="${k}"]`);
            if (p) { p.textContent = msg; p.closest('.f').classList.add('has-error'); }
        });
        const first = Object.keys(errs).find(k => FIELDS[k]);
        if (first) input(first).focus();
    }
    function validate() {
        const errs = {};
        if (readVal('name').length < 2) errs.name = 'Enter the full name.';

        const reg = readVal('registry_number');
        if (!reg) errs.registry_number = 'Registry number is required.';
        else if (reg.length !== REGISTRY_LENGTH && normReg(reg) !== normReg(original.registry_number)) {
            errs.registry_number = `Registry number must be exactly ${REGISTRY_LENGTH} characters. You entered ${reg.length}.`;
        } else if (clientRegistryDuplicates().length) {
            errs.registry_number = 'Another record already uses this registry number. Open that record instead of overwriting it.';
        }

        const g = Number(readVal('grupo'));
        if (readVal('grupo') && (!Number.isInteger(g) || g < Number(input('grupo').min) || g > Number(input('grupo').max)))
            errs.grupo = `Grupo must be ${input('grupo').min} to ${input('grupo').max} in the current Purok.`;
        if (!readVal('gender')) errs.gender = 'Choose a gender.';
        const d = readVal('date_of_baptism');
        if (d && new Date(d + 'T00:00:00') > new Date()) errs.date_of_baptism = 'The date cannot be in the future.';
        const birthday = readVal('date_of_birth');
        if (birthday && new Date(birthday + 'T00:00:00') > new Date()) errs.date_of_birth = 'The date cannot be in the future.';
        showFieldErrors(errs);
        return Object.keys(errs).length === 0;
    }

    /* ---------------- Save: review, then confirm ---------------- */
    async function saveMember(id, payload) {
        const response = await fetch(UPDATE_URL.replace('__RECORD_ID__', encodeURIComponent(id)), {
            method: 'PUT',
            headers: {
                'Accept': 'application/json', 'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        let body = {};
        try { body = await response.json(); } catch (e) { /* non-JSON error page */ }
        if (!response.ok) {
            const message = body.errors ? Object.values(body.errors).flat()[0] : body.message;
            const error = new Error(message || `Could not save the record (error ${response.status}).`);
            error.body = body;
            throw error;
        }
        return body.data;
    }

    form.addEventListener('submit', e => {
        e.preventDefault();
        if (mode !== 'edit' || !changes().length || !validate()) return;
        $('reg-review-list').replaceChildren(...changes().map(c => {
            const li = el('li'), row = el('span');
            row.append(el('span', 'from', c.from), ' → ', el('span', 'to', c.to));
            li.append(el('span', 'k', c.label), row);
            return li;
        }));
        $('reg-review-err').textContent = '';
        editor.classList.add('is-review'); applyMode();
        $('reg-confirm-btn').focus();
    });
    $('reg-back-btn').addEventListener('click', () => { editor.classList.remove('is-review'); applyMode(); });

    $('reg-confirm-btn').addEventListener('click', async () => {
        const list = changes(), payload = {};
        list.forEach(c => {
            payload[c.key] = c.key === 'is_married' ? Number(readVal(c.key))
                : c.key === 'registry_number' ? readVal(c.key).toUpperCase() : readVal(c.key);
        });
        const btn = $('reg-confirm-btn'); btn.disabled = true; btn.textContent = 'Saving...';
        try {
            const updated = await saveMember(current.id, payload);
            const savedName = (updated && updated.name) || payload.name || current.name;
            fillRecord({ ...current, ...(updated || {}) });
            searchMatches = searchMatches.map(x => x.id === current.id ? { ...x, ...current } : x);
            clearErrors(); clearRegDup(); refreshKapisanan();
            setMode('view');
            renderList();
            snack(`Saved: ${savedName} (${list.length} ${list.length === 1 ? 'change' : 'changes'})`);
            $('reg-edit-btn').focus();
            refreshResults(); // reload from the server so the table shows what was actually stored
        } catch (err) {
            console.error(err);
            const body = err.body || {};
            const dups = Array.isArray(body.duplicates) ? body.duplicates : [];
            // Go back to the form so the problem is shown next to the field that caused it.
            editor.classList.remove('is-review'); applyMode();
            if (body.errors) showFieldErrors(Object.fromEntries(Object.entries(body.errors).map(([k, v]) => [k, [].concat(v)[0]])));
            if (dups.length) renderRegDup(dups);
            $('reg-save-msg').textContent = '';
            snack(err.message || 'Could not save the changes. Nothing was changed.', true);
            $('reg-review-err').textContent = err.message || '';
        } finally { btn.disabled = false; btn.textContent = 'Confirm and save'; }
    });

    /* ---------------- Smart Helper ---------------- */
    const thread = $('smart-helper-thread');
    const smartStatus = $('smart-helper-status');
    const scanBtn = $('smart-helper-scan');
    let hasScanned = false;

    const rootEl = document.querySelector('.reg-root');
    const helperEl = $('helper'), helperOpenBtn = $('helper-open'), helperCloseBtn = $('helper-toggle');
    function setHelper(open) {
        helperEl.hidden = !open;
        rootEl.classList.toggle('helper-open', open);
        helperOpenBtn.setAttribute('aria-expanded', String(open));
        helperOpenBtn.classList.toggle('is-on', open);
        if (open) thread.scrollTop = thread.scrollHeight;
    }
    helperOpenBtn.addEventListener('click', () => {
        const open = helperEl.hidden;
        setHelper(open);
        if (open) {
            if (window.matchMedia('(max-width: 1100px)').matches) {
                helperEl.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
            }
            scanBtn.focus({ preventScroll: true });
        }
    });
    helperCloseBtn.addEventListener('click', () => { setHelper(false); helperOpenBtn.focus(); });

    function addMsg(role, content) {
        const msg = el('div', 'msg msg-' + role);
        msg.append(el('span', 'msg-who', role === 'bot' ? 'Smart Helper' : 'You'));
        const body = el('div', 'msg-body');
        if (typeof content === 'string') body.append(el('p', '', content));
        else if (content) body.append(content);
        msg.append(body);
        thread.append(msg);
        thread.scrollTop = thread.scrollHeight;
        return body;
    }

    function renderFindings(body, findings, scanned) {
        const summary = `Scanned ${scanned} ${scanned === 1 ? 'record' : 'records'}; found ${findings.length} ${findings.length === 1 ? 'possible issue' : 'possible issues'}.`;
        smartStatus.textContent = summary;
        body.replaceChildren(el('p', findings.length ? '' : 'msg-ok', findings.length ? summary : 'No duplicate registry numbers or duplicate/similar names found.'));
        if (!findings.length) return;

        const labels = { duplicate_registry: 'Duplicate registry number', duplicate_name: 'Duplicate name', similar_name: 'Similar names' };
        const parents = new Map();
        const root = id => {
            if (!parents.has(id)) parents.set(id, id);
            if (parents.get(id) !== id) parents.set(id, root(parents.get(id)));
            return parents.get(id);
        };
        findings.forEach(finding => {
            const ids = finding.records.map(record => record.id);
            ids.slice(1).forEach(id => { parents.set(root(id), root(ids[0])); });
        });
        const inactiveGroups = new Set();
        findings.forEach(finding => finding.records.forEach(record => {
            if (statusKey(record.status) === 'inactive') inactiveGroups.add(root(record.id));
        }));

        const wrap = el('div', 'findings');
        findings.forEach(finding => {
            const card = el('article', 'finding');
            let heading = labels[finding.type] || 'Possible duplicate';
            if (finding.type === 'duplicate_registry') heading += `: ${finding.match_value}`;
            if (finding.similarity && finding.similarity < 100) heading += ` (${finding.similarity}% name similarity)`;
            card.append(el('h3', '', heading));

            const hasInactiveRecord = finding.records.some(record => inactiveGroups.has(root(record.id)));
            const list = el('ul');
            finding.records.forEach(record => {
                const item = el('li');
                item.append(el('strong', '', record.name || 'Name unavailable'));
                item.append(el('span', '', `Registry: ${record.registry_number || '--'}`));
                item.append(el('span', '', `Status: ${record.status || '--'} · Kapisanan: ${record.kapisanan || '--'}`));
                if (record.purok || record.grupo) item.append(el('span', '', [record.purok ? `Purok ${record.purok}` : '', record.grupo ? `Grupo ${record.grupo}` : ''].filter(Boolean).join(' · ')));
                if (statusKey(record.status) === 'inactive') {
                    const actions = el('div', 'finding-actions');
                    const deleteButton = el('button', 'finding-delete', 'Delete inactive record');
                    deleteButton.type = 'button';
                    deleteButton.dataset.recordId = record.id;
                    deleteButton.dataset.recordName = record.name || '';
                    actions.append(deleteButton);
                    item.append(actions);
                }
                list.append(item);
            });
            card.append(list);
            if (!hasInactiveRecord) {
                card.append(el('p', 'finding-note', 'No inactive match. To remove this duplicate, change its status to Inactive in Form Generator > Monthly > Deliberation.'));
            }
            wrap.append(card);
        });
        body.append(wrap);
        thread.scrollTop = thread.scrollHeight;
    }

    async function scanSmartHelper(targetBody) {
        scanBtn.disabled = true; scanBtn.textContent = 'Scanning...';
        smartStatus.textContent = 'Scanning all records...';
        let body = targetBody;
        if (body) body.replaceChildren(el('p', '', 'Updating results...'));
        else {
            addMsg('user', hasScanned ? 'Run the search again.' : 'Run a search.');
            body = addMsg('bot', 'Scanning all records for duplicate registry numbers and duplicate or similar names...');
        }
        try {
            const response = await fetch(SMART_HELPER_URL, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Unable to scan records.');
            renderFindings(body, data.data || [], data.records_scanned || 0);
            hasScanned = true;
        } catch (error) {
            console.error(error);
            const message = error.message || 'Unable to scan records. Please try again.';
            body.replaceChildren(el('p', 'msg-error', message));
            smartStatus.textContent = message;
        } finally {
            scanBtn.disabled = false;
            scanBtn.textContent = hasScanned ? 'Run again' : 'Run a search';
        }
    }

    thread.addEventListener('click', async event => {
        const button = event.target.closest('.finding-delete');
        if (!button) return;
        const recordId = button.dataset.recordId;
        const ok = await confirmDialog({
            title: 'Delete this inactive record?',
            message: `${button.dataset.recordName || 'This record'} will be permanently deleted. This cannot be undone.`,
            confirmText: 'Delete record', danger: true,
        });
        if (!ok) return;

        const body = button.closest('.msg-body');
        button.disabled = true; button.textContent = 'Deleting...';
        smartStatus.textContent = 'Deleting inactive record...';
        try {
            const response = await fetch(DELETE_URL.replace('__RECORD_ID__', encodeURIComponent(recordId)), {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Unable to delete this record.');
            searchMatches = searchMatches.filter(x => String(x.id) !== String(recordId));
            if (current && String(current.id) === String(recordId)) {
                current = null; original = null; mode = 'view'; showRecordPanel(false); setListCollapsed(false);
            }
            if (!listWrap.hidden) renderList();
            snack('Inactive record deleted.');
            await scanSmartHelper(body);
        } catch (error) {
            console.error(error);
            smartStatus.textContent = error.message || 'Unable to delete this record. Please scan again.';
            snack(error.message || 'Unable to delete this record.', true);
            button.disabled = false; button.textContent = 'Delete inactive record';
        }
    });
    scanBtn.addEventListener('click', () => scanSmartHelper());

    // Warn before leaving the page with unsaved edits
    window.addEventListener('beforeunload', e => { if (isDirty()) { e.preventDefault(); e.returnValue = ''; } });

    showRecordPanel(false);
});
</script>
@endpush