@extends('layouts.app')

@section('title', 'Inquire / Modify Records')

@section('content')
<div class="reg-root">

    <div class="reg-page">
        <header class="reg-hero">
            <div class="reg-hero-top">
                <div>
                    <h1>Record registry</h1>
                    <p>Look up a record by name or registry number to view or update its information.</p>
                </div>
                <button type="button" class="reg-remind-btn" id="remind-open">Guidance</button>
            </div>

            <div class="reg-search">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <input type="text" id="reg-search-input" placeholder="Full name or registry number" autocomplete="off" aria-label="Search records">
                <button type="button" id="reg-search-btn" class="btn btn-gold">Search</button>
            </div>
            <div id="reg-search-error" class="reg-error" role="alert" hidden></div>
        </header>

        <section id="reg-results" class="reg-results" aria-live="polite" hidden></section>

    </div>

    <section class="smart-helper" aria-label="Smart Helper">
        <div id="smart-helper-panel" class="smart-helper-panel" aria-hidden="true" inert>
            <div class="smart-helper-window">
                <header class="smart-helper-header">
                    <span class="smart-helper-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="m12 2 1.9 6.1L20 10l-6.1 1.9L12 18l-1.9-6.1L4 10l6.1-1.9L12 2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m19 15 .9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z" fill="currentColor"/></svg>
                    </span>
                    <span class="smart-helper-heading"><strong>Smart Helper</strong><small>Duplicate record checker</small></span>
                    <button type="button" id="smart-helper-close" class="smart-helper-close" aria-label="Close Smart Helper">&times;</button>
                </header>
                <div class="smart-helper-inner">
                    <p>Scans all records for exact duplicate registry numbers, duplicate names, and names at least 90% similar. Results may include different status or kapisanan values; no records are changed or removed.</p>
                    <div class="smart-helper-toolbar">
                        <span id="smart-helper-status" role="status">Click Scan to check for duplicate records.</span>
                        <button type="button" id="smart-helper-scan" class="btn btn-gold">Scan records</button>
                    </div>
                    <div id="smart-helper-results" class="smart-helper-results" aria-live="polite"></div>
                </div>
            </div>
        </div>
        <button type="button" id="smart-helper-toggle" class="smart-helper-toggle" aria-expanded="false" aria-controls="smart-helper-panel" aria-label="Open Smart Helper">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3a8 8 0 0 0-6.7 12.4L4 21l5.9-1.7A8 8 0 1 0 12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m12 7 .9 3.1L16 11l-3.1.9L12 15l-.9-3.1L8 11l3.1-.9L12 7Z" fill="currentColor"/></svg>
            <span>Smart Helper</span>
            <span class="smart-helper-badge" aria-hidden="true"></span>
        </button>
    </section>

    {{-- ============ Reminder notification (right side) ============ --}}
    <aside class="reminder" id="reminder" role="dialog" aria-modal="false" aria-labelledby="rem-title" hidden>
        <div class="rem-head">
            <span class="rem-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M12 3a9 9 0 1 0 9 9 9 9 0 0 0-9-9Z" stroke="currentColor" stroke-width="1.8"/><path d="M12 11v5m0-8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </span>
            <div class="rem-title-wrap"><h2 id="rem-title">Record update guide</h2><p>Choose the right portal for each change.</p></div>
            <button type="button" class="rem-x" id="rem-close" aria-label="Close reminder">&times;</button>
        </div>
        <div class="rem-body">
            <section class="rem-guide-card">
                <span class="rem-guide-label">Inquire / Modify</span>
                <p>Correct personal details, such as name, registry number, gender, baptism date, and evangelist. Update a member's Grupo within their current Purok.</p>
            </section>
            <section class="rem-guide-card rem-guide-monthly">
                <span class="rem-guide-label">Form Generator &rsaquo; Monthly &rsaquo; Deliberation</span>
                <p>Change a member's status or move a group between Puroks. Remove duplicate entries here only when <strong>none of the matching records is Inactive</strong>.</p>
            </section>
            <div class="rem-warn"><strong>Duplicate removal:</strong> If a match includes an Inactive record, Smart Helper lets you remove that Inactive record. If all matches are active (for example, MS and UWP), use Monthly Deliberation.</div>
        </div>
        <div class="rem-foot"><button type="button" class="btn btn-ink" id="rem-ok">Got it</button></div>
    </aside>

    {{-- ============ Record dialog ============ --}}
    <div id="reg-modal-overlay" class="reg-overlay" hidden>
        <div class="reg-modal" role="dialog" aria-modal="true" aria-labelledby="rm-name-display">

            <div class="reg-band">
                <div class="reg-avatar" id="rm-avatar" aria-hidden="true">--</div>
                <div class="reg-band-text">
                    <div class="reg-name" id="rm-name-display">--</div>
                    <div class="reg-regno" id="rm-registry-display">--</div>
                </div>
                <span id="rm-status-badge" class="reg-badge">--</span>
                <button type="button" id="reg-modal-close" class="reg-close" aria-label="Close">&times;</button>
            </div>

            <div id="rm-dup" class="reg-alert" hidden></div>

            <form id="reg-form" novalidate>
                @csrf
                <input type="hidden" id="rm-id" name="id">

                <div class="reg-body">
                    <h3>Edit here</h3>
                    <div class="reg-grid">
                        <div class="f span2"><label for="rm-name">Name</label><input type="text" id="rm-name"><p class="err" data-err="name"></p></div>
                        <div class="f"><label for="rm-registry">Registry number</label><input type="text" id="rm-registry" maxlength="13" placeholder="13 letters/numbers or RFID"><p class="err" data-err="registry_number"></p></div>
                        <div class="f"><label for="rm-gender">Gender</label>
                            <select id="rm-gender" required><option value="" disabled>Select gender</option><option value="Male">Male</option><option value="Female">Female</option></select>
                            <p class="err" data-err="gender"></p></div>
                        <div class="f"><label for="rm-birthday">Birthday (Optional)</label><input type="date" id="rm-birthday" max="{{ now()->toDateString() }}"><p class="err" data-err="date_of_birth"></p></div>
                        <div class="f"><label for="rm-married">Marital status</label>
                            <label><input type="checkbox" id="rm-married"> Married <small>(0 = Not Married, default)</small></label>
                        </div>
                        <div class="f"><label for="rm-kapisanan">Kapisanan</label><input type="text" id="rm-kapisanan"><p class="hint" id="rm-kapisanan-hint"></p><p class="err" data-err="kapisanan"></p></div>
                        <div class="f"><label for="rm-grupo">Grupo</label><input type="number" id="rm-grupo" step="1"><p class="hint" id="rm-grupo-hint"></p><p class="err" data-err="grupo"></p></div>
                        <div class="f"><label for="rm-baptism">Date of baptism</label><input type="date" id="rm-baptism"><p class="err" data-err="date_of_baptism"></p></div>
                        <div class="f"><label for="rm-evangelist">Evangelist</label><input type="text" id="rm-evangelist"><p class="err" data-err="evangelist"></p></div>
                    </div>
                    <h3 class="reg-locked-title">Monthly portal only <small>Form Generator › Monthly › Deliberation</small></h3>
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
                    <p class="err" id="reg-review-err"></p>
                </div>

                <div class="reg-actions">
                    <span id="reg-save-msg" class="reg-save-msg"></span>
                    <span class="act-edit">
                        <button type="button" class="btn btn-ghost" id="reg-reset-btn">Reset</button>
                        <button type="button" class="btn btn-ghost" id="reg-cancel-btn">Cancel</button>
                        <button type="submit" class="btn btn-ink" id="reg-save-btn" disabled>Save changes</button>
                    </span>
                    <span class="act-review">
                        <button type="button" class="btn btn-ghost" id="reg-back-btn">Back</button>
                        <button type="button" class="btn btn-gold" id="reg-confirm-btn">Confirm and save</button>
                    </span>
                </div>
            </form>
        </div>
    </div>

    <div class="snack" id="snack" role="status"></div>
</div>
@endsection

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
.reg-root {
    --ink: #10233a; --muted: #5b6b7c; --bg: #eef1f4; --card: #fff; --line: #dde3ea;
    --gold: #c8a24a; --gold-deep: #8a6a1f; --danger: #b3261e; --ok: #2a7d55; --warn: #a5690b;
    --ease: cubic-bezier(.2, .8, .2, 1);
    font-family: 'IBM Plex Sans', system-ui, sans-serif; color: var(--ink);
}
.reg-root h1, .reg-root h2, .reg-root h3, .reg-root p, .reg-root ul, .reg-root ol { margin: 0; padding: 0; }
.reg-root [hidden] { display: none !important; }
.reg-page { max-width: 860px; margin: 0 auto; }

/* Buttons */
.btn { padding: .65rem 1.15rem; border: 0; border-radius: 9px; font: inherit; font-size: .9rem; font-weight: 600; cursor: pointer; white-space: nowrap; transition: background .2s, transform .2s var(--ease), opacity .2s; }
.btn:hover:not(:disabled) { transform: translateY(-1px); }
.btn:disabled { opacity: .45; cursor: not-allowed; }
#reg-save-btn:disabled { background: #cbd5e1; color: #64748b; opacity: 1; }
.btn-ink { background: var(--ink); color: #fff; } .btn-ink:hover:not(:disabled) { background: #1b3a5c; }
.btn-gold { background: var(--gold); color: var(--ink); } .btn-gold:hover:not(:disabled) { background: #dcbc6a; }
.btn-ghost { background: var(--bg); color: var(--ink); } .btn-ghost:hover:not(:disabled) { background: var(--line); }

/* Hero + search */
.reg-hero { position: relative; overflow: hidden; padding: 1.8rem clamp(1.2rem, 4vw, 2.2rem) 1.4rem; border-radius: 16px; color: #fff; background: #101828; }
.reg-hero > * { position: relative; }
.reg-hero-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.3rem; }
.reg-hero h1 { font-family: 'Fraunces', Georgia, serif; font-weight: 700; font-size: clamp(1.8rem, 4vw, 2.4rem); letter-spacing: -.015em; }
.reg-hero p { margin-top: .35rem; max-width: 50ch; color: #c1cde0; font-size: .95rem; line-height: 1.6; }
.reg-remind-btn { flex: none; padding: .5rem .9rem; border: 1px solid rgba(255,255,255,.35); border-radius: 999px; background: transparent; color: #fff; font: inherit; font-size: .82rem; font-weight: 600; cursor: pointer; transition: border-color .2s, color .2s; }
.reg-remind-btn:hover { border-color: var(--gold); color: #e6cf94; }
.reg-search { display: flex; align-items: center; gap: .7rem; padding: .45rem .5rem .45rem 1rem; border-radius: 13px; background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,.25); }
.reg-search svg { width: 20px; height: 20px; flex: none; color: var(--muted); }
.reg-search input { flex: 1; min-width: 0; padding: .6rem .1rem; border: 0; background: transparent; color: var(--ink); font: inherit; font-size: 1.05rem; }
.reg-search input:focus { outline: none; }
.reg-search:focus-within { box-shadow: 0 0 0 3px rgba(200,162,74,.6), 0 10px 30px rgba(0,0,0,.25); }
.reg-error { margin-top: .7rem; padding: .55rem .8rem; border-radius: 8px; background: rgba(179,38,30,.18); color: #ffd7d3; font-size: .88rem; }
/* Results */
.reg-results { margin-top: 1rem; padding: 1rem; border: 1px solid var(--line); border-radius: 14px; background: var(--card); animation: reg-rise .5s var(--ease); }
@keyframes reg-rise { from { opacity: 0; transform: translateY(10px); } }
.reg-results h2 { margin: 0 0 .7rem; font-size: .9rem; font-weight: 600; color: var(--muted); }
.reg-result { display: flex; align-items: center; gap: .9rem; width: 100%; padding: .75rem .8rem; border: 1px solid transparent; border-radius: 10px; background: none; font: inherit; color: inherit; text-align: left; cursor: pointer; transition: background .2s, border-color .2s, transform .25s var(--ease); }
.reg-result:hover { background: var(--bg); border-color: var(--line); transform: translateX(3px); }
.reg-result .who { flex: 1; min-width: 0; }
.reg-result .who b { display: block; font-weight: 600; }
.reg-result .who small { color: var(--muted); font-size: .82rem; }
.reg-dupchip { padding: .15rem .55rem; border-radius: 999px; background: #fbf0de; color: var(--warn); font-size: .72rem; font-weight: 600; }

.reg-avatar { display: grid; place-items: center; flex: none; width: 42px; height: 42px; border-radius: 50%; background: var(--ink); color: #e6cf94; font-weight: 600; font-size: .9rem; }
.reg-badge { display: inline-block; padding: .2rem .7rem; border-radius: 999px; font-size: .78rem; font-weight: 600; white-space: nowrap; }
.reg-badge.is-active { background: #e2f3ea; color: var(--ok); }
.reg-badge.is-inactive { background: #e8ecef; color: var(--muted); }
.reg-badge.is-transferred { background: #fbf0de; color: var(--warn); }

/* Record update guidance */
.reminder { position: fixed; z-index: 980; right: 1.2rem; top: 5rem; width: min(430px, calc(100vw - 2rem)); max-height: calc(100vh - 7rem); display: flex; flex-direction: column; border: 1px solid var(--line); border-radius: 18px; background: var(--card); box-shadow: 0 24px 65px rgba(16,35,58,.3); transform: translateY(12px) scale(.98); opacity: 0; visibility: hidden; transition: transform .25s var(--ease), opacity .2s, visibility .2s; }
.reminder.is-in { transform: none; opacity: 1; visibility: visible; }
.rem-head { display: flex; align-items: center; gap: .8rem; padding: 1.1rem 1.1rem .9rem; border-bottom: 1px solid var(--line); }
.rem-icon { display: grid; place-items: center; flex: none; width: 40px; height: 40px; border-radius: 12px; background: #f5edda; color: var(--gold-deep); }
.rem-icon svg { width: 23px; height: 23px; }
.rem-title-wrap { flex: 1; min-width: 0; }
.rem-title-wrap h2 { font-size: 1rem; font-weight: 700; line-height: 1.25; }
.rem-title-wrap p { margin-top: .2rem; color: var(--muted); font-size: .78rem; }
.rem-x { display: grid; place-items: center; flex: none; width: 32px; height: 32px; border: 1px solid var(--line); border-radius: 9px; background: var(--bg); color: var(--ink); font-size: 1.3rem; line-height: 1; cursor: pointer; }
.rem-body { display: grid; gap: .75rem; padding: 1rem 1.1rem; overflow: auto; font-size: .86rem; line-height: 1.5; }
.rem-guide-card { padding: .85rem .9rem; border: 1px solid var(--line); border-radius: 12px; background: var(--bg); }
.rem-guide-monthly { background: #fffaf0; border-color: #eee0bd; }
.rem-guide-label { display: block; margin-bottom: .35rem; color: var(--gold-deep); font-size: .73rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
.rem-guide-card p { color: var(--ink); }
.rem-warn { padding: .75rem .85rem; border: 1px solid #f0d39a; border-radius: 11px; background: #fff7e6; color: #704b0e; font-size: .81rem; line-height: 1.45; }
.rem-foot { padding: 0 1.1rem 1.1rem; }
.rem-foot .btn { width: 100%; }

/* Dialog */
.reg-overlay { position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 1.2rem; background: rgba(10,19,36,.6); animation: reg-fade .2s; }
@keyframes reg-fade { from { opacity: 0; } }
.reg-modal { width: 100%; max-width: 660px; max-height: 92vh; overflow-y: auto; border-radius: 16px; background: var(--card); box-shadow: 0 30px 70px rgba(0,0,0,.35); animation: reg-pop .35s var(--ease); }
@keyframes reg-pop { from { opacity: 0; transform: translateY(14px) scale(.98); } }
.reg-band { display: flex; align-items: center; gap: .9rem; padding: 1.2rem 1.4rem; background: #101828; color: #fff; }
.reg-band .reg-avatar { background: var(--gold); color: var(--ink); width: 48px; height: 48px; font-size: 1rem; }
.reg-band-text { flex: 1; min-width: 0; }
.reg-name { font-family: 'Fraunces', Georgia, serif; font-size: 1.35rem; font-weight: 700; }
.reg-regno { margin-top: .1rem; color: #b9c6da; font-size: .85rem; letter-spacing: .02em; }
.reg-close { align-self: flex-start; width: 32px; height: 32px; border: 0; border-radius: 8px; background: rgba(255,255,255,.12); color: #fff; font-size: 1.4rem; line-height: 1; cursor: pointer; }
.reg-close:hover { background: rgba(255,255,255,.25); }
.reg-alert { margin: 1rem 1.4rem 0; padding: .7rem .9rem; border-radius: 10px; background: #fbf0de; color: #7a4a05; font-size: .86rem; line-height: 1.5; }

.reg-body, .reg-review { padding: 1.1rem 1.4rem .4rem; }
.reg-body h3, .reg-review h3 { margin-bottom: .8rem; font-size: .8rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
.reg-locked-title { margin-top: 1.4rem !important; }
.reg-locked-title small { margin-left: .5rem; letter-spacing: 0; text-transform: none; font-weight: 500; color: var(--gold-deep); }
.reg-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem 1rem; }
.span2 { grid-column: 1 / -1; }
.f label { display: block; margin-bottom: .3rem; color: var(--muted); font-size: .78rem; font-weight: 600; }
.f input, .f select { width: 100%; padding: .6rem .7rem; border: 1.5px solid var(--line); border-radius: 9px; background: #fff; color: var(--ink); font: inherit; font-size: .95rem; transition: border-color .2s, box-shadow .2s, background .2s; }
.f input:hover, .f select:hover { border-color: #bcc6d1; }
.f input:focus, .f select:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(200,162,74,.25); }
.f input[type="checkbox"] { width: auto; padding: 0; accent-color: var(--gold); }
.f.is-changed input, .f.is-changed select { border-color: var(--gold); background: #fffaf0; }
.f.has-error input, .f.has-error select { border-color: var(--danger); }
.hint { margin-top: .25rem; color: var(--muted); font-size: .76rem; line-height: 1.4; }
.err { margin-top: .25rem; color: var(--danger); font-size: .78rem; line-height: 1.4; }
.err:empty { display: none; }

.reg-locks { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
.lock { display: grid; gap: .15rem; padding: .7rem .85rem; border: 1px dashed #c5cdd6; border-radius: 10px; background: var(--bg); }
.lock-k { display: flex; align-items: center; gap: .35rem; color: var(--muted); font-size: .76rem; font-weight: 600; }
.lock-k::before { content: ""; width: 11px; height: 11px; background: currentColor; -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.4'%3E%3Crect x='5' y='10' width='14' height='10' rx='2'/%3E%3Cpath d='M8 10V7a4 4 0 0 1 8 0v3'/%3E%3C/svg%3E") center/contain no-repeat; mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.4'%3E%3Crect x='5' y='10' width='14' height='10' rx='2'/%3E%3Cpath d='M8 10V7a4 4 0 0 1 8 0v3'/%3E%3C/svg%3E") center/contain no-repeat; }
.lock-v { display: flex; align-items: center; gap: .5rem; font-size: .95rem; font-weight: 500; }
.reg-eye { margin-left: auto; padding: .15rem .55rem; border: 1px solid var(--line); border-radius: 6px; background: #fff; font: inherit; font-size: .76rem; font-weight: 600; cursor: pointer; }

/* Review step */
.reg-review { display: none; }
.reg-modal.is-review .reg-body { display: none; }
.reg-modal.is-review .reg-review { display: block; }
.reg-review ul { list-style: none; display: grid; gap: .5rem; }
.reg-review li { display: grid; gap: .1rem; padding: .65rem .8rem; border: 1px solid var(--line); border-left: 4px solid var(--gold); border-radius: 9px; font-size: .9rem; }
.reg-review .k { color: var(--muted); font-size: .76rem; font-weight: 600; }
.reg-review .from { color: var(--muted); text-decoration: line-through; }
.reg-review .to { font-weight: 600; }

.reg-actions { display: flex; align-items: center; gap: .6rem; padding: 1rem 1.4rem 1.3rem; }
.reg-actions .act-edit, .reg-actions .act-review { display: flex; gap: .5rem; margin-left: auto; }
.reg-modal:not(.is-review) .act-review, .reg-modal.is-review .act-edit { display: none; }
.reg-save-msg { color: var(--ok); font-size: .85rem; }
.snack { position: fixed; z-index: 1200; left: 50%; bottom: max(1.4rem, env(safe-area-inset-bottom)); width: max-content; max-width: calc(100vw - 2rem); padding: .7rem 1.2rem; border-radius: 999px; background: var(--ink); color: #fff; font-size: .9rem; text-align: center; overflow-wrap: anywhere; box-shadow: 0 10px 30px rgba(0,0,0,.3); transform: translate(-50%, 140%); transition: transform .45s var(--ease); pointer-events: none; }
.snack.show { transform: translate(-50%, 0); }

.reg-root :focus-visible { outline: 3px solid #5b9dff; outline-offset: 2px; }
.smart-helper { position: fixed; z-index: 950; right: 1.35rem; bottom: 1.35rem; }
.smart-helper-toggle { display: flex; align-items: center; gap: .6rem; min-height: 54px; padding: .7rem 1rem; border: 0; border-radius: 999px; background: #101828; color: #fff; box-shadow: 0 2px 8px rgba(10,19,36,.18); font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; transition: transform .2s var(--ease), box-shadow .2s var(--ease); }
.smart-helper-toggle:hover { transform: translateY(-2px); box-shadow: 0 14px 32px rgba(10,19,36,.36); }
.smart-helper-toggle > svg { width: 23px; height: 23px; color: #e6cf94; }
.smart-helper-badge { width: 8px; height: 8px; margin-left: .15rem; border-radius: 50%; background: #51d58a; box-shadow: 0 0 0 3px rgba(81,213,138,.18); }
.smart-helper-panel { position: absolute; right: 0; bottom: calc(100% + .85rem); width: min(440px, calc(100vw - 2rem)); max-height: min(660px, calc(100vh - 7rem)); opacity: 0; visibility: hidden; pointer-events: none; transform: translateY(12px) scale(.98); transform-origin: bottom right; transition: opacity .2s ease, transform .25s var(--ease), visibility .2s; }
.smart-helper-panel.is-open { opacity: 1; visibility: visible; pointer-events: auto; transform: translateY(0) scale(1); }
.smart-helper-window { display: flex; flex-direction: column; max-height: min(660px, calc(100vh - 7rem)); overflow: hidden; border: 1px solid var(--line); border-radius: 16px; background: var(--card); box-shadow: 0 24px 60px rgba(10,19,36,.3); }
.smart-helper-header { display: flex; align-items: center; gap: .75rem; padding: .95rem 1rem; background: #101828; color: #fff; }
.smart-helper-icon { display: grid; place-items: center; flex: none; width: 38px; height: 38px; border-radius: 12px; background: rgba(230,207,148,.14); color: #e6cf94; }
.smart-helper-icon svg { width: 24px; height: 24px; }
.smart-helper-heading { display: grid; flex: 1; gap: .12rem; }
.smart-helper-heading strong { font-size: .95rem; }
.smart-helper-heading small { color: #c3cede; font-size: .76rem; }
.smart-helper-close { display: grid; place-items: center; width: 34px; height: 34px; border: 0; border-radius: 9px; background: rgba(255,255,255,.1); color: #fff; font-size: 1.4rem; cursor: pointer; }
.smart-helper-close:hover { background: rgba(255,255,255,.2); }
.smart-helper-inner { min-height: 0; padding: 1rem; overflow-y: auto; }
.smart-helper-inner > p { margin: 0 0 .8rem; color: var(--muted); font-size: .86rem; line-height: 1.5; }
.smart-helper-toolbar { display: flex; align-items: center; gap: .8rem; }
.smart-helper-toolbar [role="status"] { flex: 1; color: var(--muted); font-size: .84rem; }
.smart-helper-results { display: grid; gap: .65rem; margin-top: .9rem; }
.smart-helper-finding { padding: .8rem .9rem; border: 1px solid var(--line); border-left: 4px solid var(--gold); border-radius: 9px; background: #fffdf8; }
.smart-helper-finding h3 { margin: 0 0 .55rem; font-size: .9rem; }
.smart-helper-finding ul { display: grid; gap: .35rem; list-style: none; padding: 0; }
.smart-helper-finding li { display: grid; gap: .12rem; color: var(--muted); font-size: .82rem; }
.smart-helper-finding li strong { color: var(--ink); font-size: .88rem; }
.smart-helper-record-actions { margin-top: .35rem; }
.smart-helper-delete { justify-self: start; padding: .35rem .65rem; border: 1px solid #f0b5b0; border-radius: 7px; background: #fff4f2; color: #a12a20; font: inherit; font-size: .76rem; font-weight: 700; cursor: pointer; }
.smart-helper-delete:hover { background: #fde6e3; }
.smart-helper-delete:disabled { cursor: wait; opacity: .65; }
.smart-helper-delete-warning { margin: .3rem 0 0; color: #8b5a05; font-size: .76rem; line-height: 1.4; }
.smart-helper-empty { color: var(--ok); font-size: .9rem; font-weight: 600; }
@media (max-width: 640px) {
    .reg-grid, .reg-locks { grid-template-columns: 1fr; }
    .reg-search { flex-wrap: wrap; } .reg-search .btn { width: 100%; }
    .reminder { left: 1rem; right: 1rem; top: auto; bottom: 1rem; width: auto; max-height: 75vh; }
    .reg-band { flex-wrap: wrap; }
    .smart-helper { right: 1rem; bottom: 1rem; }
    .smart-helper-toggle { min-height: 50px; padding: .65rem .85rem; }
    .smart-helper-panel, .smart-helper-window { max-height: calc(100vh - 6rem); }
    .smart-helper-toolbar { align-items: stretch; flex-direction: column; }
    .smart-helper-toolbar .btn { width: 100%; }
}
@media (prefers-reduced-motion: reduce) { .reg-root *, .reg-root *::before { animation: none !important; transition: none !important; } }
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
    async function saveMember(id, changes) {
        const response = await fetch(UPDATE_URL.replace('__RECORD_ID__', encodeURIComponent(id)), {
            method: 'PUT',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(changes),
        });
        const body = await response.json();
        if (!response.ok) {
            const message = body.errors ? Object.values(body.errors).flat()[0] : body.message;
            throw new Error(message || 'Could not save the record.');
        }
        return body.data;
    }

    /* ---------------- Elements + helpers ---------------- */
    const FIELDS = {
        name: ['rm-name', 'Name'], registry_number: ['rm-registry', 'Registry number'], gender: ['rm-gender', 'Gender'],
        date_of_birth: ['rm-birthday', 'Birthday'], is_married: ['rm-married', 'Married'],
        kapisanan: ['rm-kapisanan', 'Kapisanan'], grupo: ['rm-grupo', 'Grupo'],
        date_of_baptism: ['rm-baptism', 'Date of baptism'], evangelist: ['rm-evangelist', 'Evangelist'],
    };
    const input = k => $(FIELDS[k][0]);
    const overlay = $('reg-modal-overlay'), modal = overlay.querySelector('.reg-modal'), form = $('reg-form');
    let current = null, original = null, lastTrigger = null, r210 = '', r210Shown = false, searchMatches = [];

    const initials = n => n.split(/\s+/).filter(Boolean).map((w, i, a) => (i === 0 || i === a.length - 1) ? w[0] : '').join('').toUpperCase();
    const fmtDate = iso => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '--';
    const statusClass = s => ({ active: 'is-active', transferred: 'is-transferred' }[(s || '').toLowerCase()] || 'is-inactive');
    const norm = s => s.toLowerCase().replace(/\s+/g, ' ').trim();
    const place = m => [
        m.purok ? `Purok ${m.purok}` : '',
        m.grupo ? `Grupo ${m.grupo}` : '',
    ].filter(Boolean).join(', ') || 'Purok / Grupo unavailable';
    const readVal = k => input(k).type === 'checkbox'
        ? (input(k).checked ? '1' : '0')
        : String(input(k).value).trim();
    const setVal = (k, value) => {
        if (input(k).type === 'checkbox') input(k).checked = value === true || value === 1 || value === '1' || value === 'true';
        else input(k).value = value ?? '';
    };
    function el(tag, cls, text) { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
    let snackTimer;
    function snack(text) { const s = $('snack'); s.textContent = text; s.classList.add('show'); clearTimeout(snackTimer); snackTimer = setTimeout(() => s.classList.remove('show'), 3600); }

    /* ---------------- Smart Helper ---------------- */
    const smartToggle = $('smart-helper-toggle');
    const smartPanel = $('smart-helper-panel');
    const smartStatus = $('smart-helper-status');
    const smartResults = $('smart-helper-results');
    let smartHelperLoaded = false;

    function renderSmartHelper(findings, scanned) {
        smartResults.replaceChildren();
        smartStatus.textContent = `Scanned ${scanned} ${scanned === 1 ? 'record' : 'records'}; found ${findings.length} ${findings.length === 1 ? 'possible issue' : 'possible issues'}.`;

        if (!findings.length) {
            smartResults.append(el('p', 'smart-helper-empty', 'No duplicate registry numbers or duplicate/similar names found.'));
            return;
        }

        const labels = {
            duplicate_registry: 'Duplicate registry number',
            duplicate_name: 'Duplicate name',
            similar_name: 'Similar names',
        };

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
            if ((record.status || '').trim().toLowerCase() === 'inactive') {
                inactiveGroups.add(root(record.id));
            }
        }));

        findings.forEach(finding => {
            const card = el('article', 'smart-helper-finding');
            let heading = labels[finding.type] || 'Possible duplicate';
            if (finding.type === 'duplicate_registry') heading += ` — ${finding.match_value}`;
            if (finding.similarity && finding.similarity < 100) heading += ` (${finding.similarity}% name similarity)`;
            card.append(el('h3', '', heading));

            const hasInactiveRecord = finding.records.some(record => inactiveGroups.has(root(record.id)));
            const list = el('ul');
            finding.records.forEach(record => {
                const item = el('li');
                item.append(el('strong', '', record.name || 'Name unavailable'));
                item.append(el('span', '', `Registry: ${record.registry_number || '—'}`));
                item.append(el('span', '', `Status: ${record.status || '—'} · Kapisanan: ${record.kapisanan || '—'}`));
                if (record.purok || record.grupo) item.append(el('span', '', [record.purok ? `Purok ${record.purok}` : '', record.grupo ? `Grupo ${record.grupo}` : ''].filter(Boolean).join(' · ')));
                const actions = el('div', 'smart-helper-record-actions');
                if ((record.status || '').trim().toLowerCase() === 'inactive') {
                    const deleteButton = el('button', 'smart-helper-delete', 'Delete inactive record');
                    deleteButton.type = 'button';
                    deleteButton.dataset.recordId = record.id;
                    actions.append(deleteButton);
                }
                item.append(actions);
                list.append(item);
            });
            card.append(list);
            if (!hasInactiveRecord) {
                card.append(el(
                    'p',
                    'smart-helper-delete-warning',
                    'No inactive match. To remove this duplicate, change its status to Inactive in Form Generator > Monthly > Deliberation.'
                ));
            }
            smartResults.append(card);
        });
    }

    async function scanSmartHelper() {
        const button = $('smart-helper-scan');
        button.disabled = true;
        button.textContent = 'Scanning...';
        smartStatus.textContent = 'Scanning all records for duplicate values and similar names...';
        smartResults.replaceChildren();

        try {
            const response = await fetch(SMART_HELPER_URL, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const body = await response.json();
            if (!response.ok) throw new Error(body.message || 'Unable to scan records.');
            renderSmartHelper(body.data || [], body.records_scanned || 0);
            smartHelperLoaded = true;
        } catch (error) {
            console.error(error);
            smartStatus.textContent = error.message || 'Unable to scan records. Please try again.';
        } finally {
            button.disabled = false;
            button.textContent = 'Scan records';
        }
    }

    smartResults.addEventListener('click', async event => {
        const button = event.target.closest('.smart-helper-delete');
        if (!button) return;
        const recordId = button.dataset.recordId;
        if (!window.confirm('Delete this inactive record permanently? This cannot be undone.')) return;

        button.disabled = true;
        button.textContent = 'Deleting...';
        smartStatus.textContent = 'Deleting inactive record...';
        try {
            const response = await fetch(DELETE_URL.replace('__RECORD_ID__', encodeURIComponent(recordId)), {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const body = await response.json();
            if (!response.ok) throw new Error(body.message || 'Unable to delete this record.');
            smartHelperLoaded = false;
            await scanSmartHelper();
        } catch (error) {
            console.error(error);
            smartStatus.textContent = error.message || 'Unable to delete this record. Please scan again.';
            button.disabled = false;
            button.textContent = 'Delete inactive record';
        }
    });

    function setSmartHelperOpen(open) {
        smartToggle.setAttribute('aria-expanded', String(open));
        smartToggle.setAttribute('aria-label', open ? 'Close Smart Helper' : 'Open Smart Helper');
        smartPanel.setAttribute('aria-hidden', String(!open));
        smartPanel.inert = !open;
        smartPanel.classList.toggle('is-open', open);
        if (open) $('smart-helper-close').focus();
        else smartToggle.focus();
    }

    smartToggle.addEventListener('click', () => {
        setSmartHelperOpen(smartToggle.getAttribute('aria-expanded') !== 'true');
    });
    $('smart-helper-close').addEventListener('click', () => setSmartHelperOpen(false));
    $('smart-helper-scan').addEventListener('click', scanSmartHelper);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && smartToggle.getAttribute('aria-expanded') === 'true') {
            setSmartHelperOpen(false);
        }
    });

    /* ---------------- Reminder notification ---------------- */
    const rem = $('reminder');
    function showRem() { rem.hidden = false; requestAnimationFrame(() => requestAnimationFrame(() => rem.classList.add('is-in'))); }
    function hideRem() { rem.classList.remove('is-in'); setTimeout(() => { if (!rem.classList.contains('is-in')) rem.hidden = true; }, reduce ? 0 : 450); }
    $('rem-close').addEventListener('click', hideRem);
    $('rem-ok').addEventListener('click', hideRem);
    $('remind-open').addEventListener('click', showRem);

    /* ---------------- Search ---------------- */
    const errBox = $('reg-search-error'), results = $('reg-results'), searchInput = $('reg-search-input');
    let searchTimer = null, activeSearch = null, searchSequence = 0;
    function searchFail(msg) { errBox.textContent = msg; errBox.hidden = false; }

    async function runSearch(raw, showEmptyError = true) {
        const q = norm(raw ?? searchInput.value);
        errBox.hidden = true;
        if (!q) {
            results.hidden = true;
            if (showEmptyError) searchFail('Please enter a name or registry number.');
            return;
        }
        if (q.length < 2) {
            results.hidden = true;
            return;
        }

        activeSearch?.abort();
        activeSearch = new AbortController();
        const controller = activeSearch;
        const sequence = ++searchSequence;
        const button = $('reg-search-btn');
        button.disabled = true;
        button.textContent = 'Searching...';
        try {
            const response = await fetch(`${SEARCH_URL}?q=${encodeURIComponent(q)}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            const body = await response.json();
            if (sequence !== searchSequence) return;
            if (!response.ok) throw new Error(body.message || 'Unable to search records.');
            const found = body.data;
            if (!found.length) return searchFail('No matching record found. Check the spelling or try the registry number.');
            searchMatches = found;
            renderResults(found, q);
        } catch (error) {
            if (error.name === 'AbortError') return;
            if (sequence !== searchSequence) return;
            console.error(error);
            searchFail(error.message || 'Unable to search records. Please try again.');
        } finally {
            if (sequence === searchSequence) {
                button.disabled = false;
                button.textContent = 'Search';
            }
        }
    }
    function renderResults(list, q) {
        const dupNames = new Set(list.filter((m, i) => list.findIndex(x => norm(x.name) === norm(m.name)) !== i).map(m => norm(m.name)));
        results.replaceChildren(el('h2', '', `${list.length} ${list.length === 1 ? 'suggestion' : 'suggestions'} for "${q}". Choose a record to open.`),
            ...list.map(m => {
                const b = el('button', 'reg-result'); b.type = 'button';
                const who = el('span', 'who');
                who.append(
                    el('b', '', m.registry_number ? `${m.name} - ${m.registry_number}` : m.name),
                    el('small', '', place(m))
                );
                b.append(el('span', 'reg-avatar', initials(m.name)), who);
                if (dupNames.has(norm(m.name))) b.append(el('span', 'reg-dupchip', 'Same name'));
                if (m.status) b.append(el('span', 'reg-badge ' + statusClass(m.status), m.status));
                b.addEventListener('click', () => openMember(m));
                return b;
            }));
        results.hidden = false;
    }
    function scheduleSearch() {
        clearTimeout(searchTimer);
        activeSearch?.abort();
        activeSearch = null;
        searchSequence++;
        $('reg-search-btn').disabled = false;
        $('reg-search-btn').textContent = 'Search';
        errBox.hidden = true;
        results.hidden = true;
        if (!searchInput.value.trim()) {
            return;
        }
        searchTimer = setTimeout(() => runSearch(searchInput.value, false), 250);
    }
    $('reg-search-btn').addEventListener('click', () => {
        clearTimeout(searchTimer);
        runSearch(searchInput.value);
    });
    searchInput.addEventListener('input', scheduleSearch);
    searchInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(searchTimer);
            runSearch(searchInput.value);
        }
    });
    /* ---------------- Open / close ---------------- */
    function openMember(m) {
        lastTrigger = document.activeElement;
        current = m;
        original = { ...m, is_married: m.is_married ? 1 : 0 };
        Object.keys(FIELDS).forEach(k => setVal(k, original[k]));
        refreshKapisanan();
        $('rm-id').value = m.id;
        $('rm-avatar').textContent = initials(m.name);
        $('rm-name-display').textContent = m.name;
        $('rm-registry-display').textContent = m.registry_number;
        const badge = $('rm-status-badge'); badge.textContent = m.status || 'Unknown'; badge.className = 'reg-badge ' + statusClass(m.status);

        $('rm-status-text').textContent = m.status;
        $('rm-purok-display').textContent = m.purok_name || (m.purok ? `Purok ${m.purok}` : '--');
        $('rm-entry-display').textContent = fmtDate(m.date_of_entry);
        r210 = m.r210_eligible || '--'; r210Shown = false;
        $('rm-r210-display').textContent = '••••••••••'; $('rm-r210-toggle').textContent = 'Show';
        input('grupo').min = m.grupo_min ?? 1;
        input('grupo').max = m.grupo_max ?? 24;
        $('rm-grupo-hint').textContent = `Grupo must stay between ${input('grupo').min} and ${input('grupo').max}${m.purok_name ? ` in ${m.purok_name}` : ' in the current Purok'}.`;
        const twins = searchMatches.filter(x => x.id !== m.id && norm(x.name) === norm(m.name));
        const dup = $('rm-dup');
        dup.hidden = !twins.length;
        if (twins.length) {
            const matches = twins.map(t =>
                `${t.registry_number || 'no registry'} · ${t.status || 'status unavailable'}`
            ).join('; ');
            dup.textContent = `Name match only — this is a warning, not a save error. Other record${twins.length === 1 ? '' : 's'}: ${matches}. Verify the registry and status before treating it as a duplicate.`;
        }

        clearErrors(); modal.classList.remove('is-review'); $('reg-save-msg').textContent = '';
        updateDirty();
        overlay.hidden = false;
        if (window.innerWidth < 1100) hideRem();
        setTimeout(() => input('name').focus(), 60);
    }
    function closeModal(force) {
        if (!force && changes().length && !confirm('Discard your unsaved changes?')) return;
        overlay.hidden = true; current = null;
        if (lastTrigger && lastTrigger.focus) lastTrigger.focus();
    }
    $('reg-modal-close').addEventListener('click', () => closeModal());
    $('reg-cancel-btn').addEventListener('click', () => closeModal());
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !overlay.hidden) closeModal(); });
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
        if ($('rm-married').checked) {
            kapisanan.value = 'Buklod';
            kapisanan.disabled = true;
            $('rm-kapisanan-hint').textContent = 'Automatically selected: Buklod. Married is checked, so Kapisanan cannot be edited manually.';
            return;
        }
        if (!birthday) {
            kapisanan.disabled = false;
            $('rm-kapisanan-hint').textContent = 'Enter a birthday or mark Married to auto-select Kapisanan; otherwise you can enter it manually.';
            return;
        }
        const [year, month, day] = birthday.split('-').map(Number);
        const today = new Date();
        let age = today.getFullYear() - year;
        if (today.getMonth() + 1 < month || (today.getMonth() + 1 === month && today.getDate() < day)) age--;
        kapisanan.value = age < 18 ? 'Binhi' : 'Kadiwa';
        kapisanan.disabled = true;
        $('rm-kapisanan-hint').textContent = `Automatically selected: ${kapisanan.value}. Birthday is set, so Kapisanan cannot be edited manually.`;
    }
    function changes() {
        if (!original) return [];
        return Object.keys(FIELDS).filter(k => normalizedValue(k, readVal(k)) !== normalizedValue(k, original[k]))
            .map(k => ({ key: k, label: FIELDS[k][1], from: String(original[k] ?? '') || '(empty)', to: readVal(k) || '(empty)' }));
    }
    function updateDirty() {
        const list = changes(), keys = new Set(list.map(c => c.key));
        Object.keys(FIELDS).forEach(k => input(k).closest('.f').classList.toggle('is-changed', keys.has(k)));
        $('reg-save-btn').disabled = !list.length;
        $('reg-reset-btn').disabled = !list.length;
        $('reg-save-btn').textContent = list.length ? `Save changes (${list.length})` : 'Save changes';
    }
    form.addEventListener('input', () => { clearErrors(); updateDirty(); });
    form.addEventListener('change', () => { clearErrors(); updateDirty(); });
    $('rm-married').addEventListener('change', () => {
        refreshKapisanan();
        updateDirty();
    });
    $('rm-birthday').addEventListener('change', () => {
        refreshKapisanan();
        updateDirty();
    });
    $('reg-reset-btn').addEventListener('click', () => {
        Object.keys(FIELDS).forEach(k => setVal(k, original[k]));
        refreshKapisanan();
        clearErrors();
        updateDirty();
    });

    /* ---------------- Validation ---------------- */
    function clearErrors() { form.querySelectorAll('[data-err]').forEach(p => { p.textContent = ''; }); form.querySelectorAll('.f').forEach(f => f.classList.remove('has-error')); }
    function validate() {
        const errs = {};
        if (readVal('name').length < 2) errs.name = 'Enter the full name.';
        const g = Number(readVal('grupo'));
        if (readVal('grupo') && (!Number.isInteger(g) || g < Number(input('grupo').min) || g > Number(input('grupo').max)))
            errs.grupo = `Grupo must be ${input('grupo').min} to ${input('grupo').max} in the current Purok.`;
        if (!readVal('gender')) errs.gender = 'Choose a gender.';
        const d = readVal('date_of_baptism');
        if (d && new Date(d + 'T00:00:00') > new Date()) errs.date_of_baptism = 'The date cannot be in the future.';
        const birthday = readVal('date_of_birth');
        if (birthday && new Date(birthday + 'T00:00:00') > new Date()) errs.date_of_birth = 'The date cannot be in the future.';
        Object.entries(errs).forEach(([k, msg]) => {
            const p = form.querySelector(`[data-err="${k}"]`); p.textContent = msg; p.closest('.f').classList.add('has-error');
        });
        const first = Object.keys(errs)[0];
        if (first) input(first).focus();
        return !first;
    }

    /* ---------------- Save: review, then confirm ---------------- */
    form.addEventListener('submit', e => {
        e.preventDefault();
        if (!changes().length || !validate()) return;
        $('reg-review-list').replaceChildren(...changes().map(c => {
            const li = el('li'), row = el('span');
            row.append(el('span', 'from', c.from), ' → ', el('span', 'to', c.to));
            li.append(el('span', 'k', c.label), row);
            return li;
        }));
        $('reg-review-err').textContent = '';
        modal.classList.add('is-review');
    });
    $('reg-back-btn').addEventListener('click', () => modal.classList.remove('is-review'));
    $('reg-confirm-btn').addEventListener('click', async () => {
        const list = changes(), payload = {};
        list.forEach(c => {
            payload[c.key] = c.key === 'is_married' ? Number(readVal(c.key))
                : c.key === 'registry_number' ? readVal(c.key).toUpperCase() : readVal(c.key);
        });
        const btn = $('reg-confirm-btn'); btn.disabled = true; btn.textContent = 'Saving...';
        try {
            const updated = await saveMember(current.id, payload);
            current = updated;
            const name = payload.name || current.name;
            closeModal(true);
            snack(`Record updated: ${name} (${list.length} ${list.length === 1 ? 'change' : 'changes'})`);
            results.hidden = true;
        } catch (err) {
            console.error(err);
            $('reg-review-err').textContent = err.message || 'Could not save the changes. Please try again.';
        } finally { btn.disabled = false; btn.textContent = 'Confirm and save'; }
    });
});
</script>
@endpush