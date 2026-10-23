@extends('layouts.app')

@section('title', 'Deliberation Form')

@section('content')
<style>
    /* ---------- Local tokens (derive from existing theme vars so light/dark both work) ---------- */
    .deliberation-page {
        --r-sm:6px; --r-md:8px; --r-lg:10px; --r-btn:0;
        --flip-bg:var(--text); --flip-fg:var(--bg);
        --warn:var(--warning,#d97706);
        --ok:var(--success,#16a34a);
        --hover:color-mix(in srgb,var(--text) 6%,var(--bg));
        --ring:color-mix(in srgb,var(--text) 55%,transparent);
        --warn-surface:color-mix(in srgb,var(--warn) 8%,var(--surface));
        --warn-border:color-mix(in srgb,var(--warn) 45%,var(--border));
        max-width:1000px; margin:0 auto;
    }

    /* ---------- Header: line, not card ---------- */
    .deliberation-hero { margin-bottom:1rem; padding:0 0 1rem; border-bottom:1px solid var(--border); }
    .deliberation-hero h1 { margin:.9rem 0 .3rem; font-size:1.5rem; font-weight:600; letter-spacing:-.02em; line-height:1.25; }
    .deliberation-hero p { margin:0; max-width:70ch; color:var(--text-muted); font-size:.875rem; line-height:1.55; }

    .monthly-back-button { display:inline-flex; align-items:center; min-height:32px; padding:.3rem .65rem; border:1px solid var(--border); border-radius:var(--r-btn); background:transparent; color:var(--text-muted); font:inherit; font-size:.8125rem; font-weight:500; text-decoration:none; transition:background-color .12s,border-color .12s,color .12s; }
    .monthly-back-button:hover { background:var(--flip-bg); border-color:var(--flip-bg); color:var(--flip-fg); }

    /* ---------- Tabs: underline style ---------- */
    .deliberation-tabs { display:flex; gap:.25rem; margin-bottom:1.25rem; padding:0; border:0; border-bottom:1px solid var(--border); background:transparent; }
    .deliberation-tab,.deliberation-tab-link { display:inline-flex; align-items:center; min-height:40px; margin-bottom:-1px; padding:.5rem .85rem; border:0; border-bottom:2px solid transparent; border-radius:0; background:transparent; color:var(--text-muted); font:inherit; font-size:.875rem; font-weight:500; text-decoration:none; cursor:pointer; transition:color .12s,border-color .12s; }
    .deliberation-tab:hover,.deliberation-tab-link:hover { color:var(--text); }
    .deliberation-tab[aria-selected="true"],.deliberation-tab-link[aria-current="page"] { border-bottom-color:var(--text); color:var(--text); font-weight:600; }

    /* ---------- Notices ---------- */
    .monthly-session-required { margin:0 0 1rem; padding:.7rem .85rem; border:1px solid var(--warn-border); border-radius:var(--r-md); background:var(--warn-surface); color:var(--text); font-size:.84rem; line-height:1.5; }
    .monthly-session-required[hidden] { display:none; }

    /* ---------- Form: no outer card, structure from spacing and lines ---------- */
    .deliberation-form,.subtract-panel { padding:0; border:0; border-radius:0; background:transparent; box-shadow:none; }
    .deliberation-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem 1.25rem; }
    .delib-field { min-width:0; }
    .delib-field.full { grid-column:1/-1; }
    .delib-field label,.delib-label { display:block; margin-bottom:.35rem; font-size:.8125rem; font-weight:500; color:var(--text); }
    .required-mark { color:var(--danger); font-weight:600; }

    .delib-field input:not([type=radio]):not([type=checkbox]),.delib-field select,
    .subtract-search input { width:100%; min-height:38px; padding:.5rem .65rem; border:1px solid var(--border); border-radius:var(--r-md); background:var(--bg); color:var(--text); font:inherit; font-size:.875rem; transition:border-color .12s; }
    .delib-field input:hover:not(:disabled):not([readonly]),.delib-field select:hover:not(:disabled) { border-color:color-mix(in srgb,var(--text) 30%,var(--border)); }
    .delib-field input:focus-visible,.delib-field select:focus-visible,
    .subtract-search input:focus-visible { outline:2px solid var(--ring); outline-offset:1px; border-color:var(--text); }
    .delib-field select:disabled,.delib-field input:disabled { background:color-mix(in srgb,var(--border) 35%,var(--surface)); color:var(--text-muted); cursor:not-allowed; }
    .delib-field input[type=date] { font-variant-numeric:tabular-nums; }

    .delib-help { margin:.3rem 0 0; color:var(--text-muted); font-size:.75rem; line-height:1.45; }
    .delib-error { min-height:1rem; margin:.2rem 0 0; color:var(--danger); font-size:.75rem; }

    /* ---------- Registry ---------- */
    .registry-field { position:relative; }
    .registry-inputs { display:grid; grid-template-columns:repeat(13,minmax(0,1fr)); gap:.25rem; max-width:700px; }
    .registry-inputs input { width:100%; min-width:0; height:42px; padding:.2rem; border:1px solid var(--border); border-radius:var(--r-sm); background:var(--bg); color:var(--text); font:600 .95rem ui-monospace,Consolas,monospace; font-variant-numeric:tabular-nums; text-align:center; text-transform:uppercase; transition:border-color .12s; }
    .registry-inputs input:focus-visible { outline:2px solid var(--ring); outline-offset:1px; border-color:var(--text); }
    .registry-inputs.is-disabled input { background:color-mix(in srgb,var(--border) 35%,var(--surface)); color:var(--text-muted); cursor:not-allowed; opacity:.75; }
    .registry-options { display:flex; align-items:center; gap:.5rem; margin-top:.6rem; color:var(--text-muted); font-size:.8125rem; font-weight:400; }
    .registry-options input,.delib-choice input { width:15px; height:15px; accent-color:var(--text); }

    /* ---------- Choices ---------- */
    .delib-choice-row { display:flex; flex-wrap:wrap; gap:.5rem; }
    .delib-choice { display:inline-flex; align-items:center; gap:.45rem; min-height:36px; padding:.4rem .75rem; border:1px solid var(--border); border-radius:var(--r-md); background:var(--bg); font-size:.875rem; font-weight:400; cursor:pointer; transition:background-color .12s,border-color .12s; }
    .delib-choice:hover { background:var(--hover); }
    .delib-choice:has(input:checked) { border-color:var(--text); }

    /* ---------- Duplicates and selection ---------- */
    .duplicate-panel { display:grid; gap:.5rem; margin:1rem 0; }
    .duplicate-panel:empty { display:none; }
    .duplicate-alert { padding:.7rem .85rem; border:1px solid var(--warn-border); border-radius:var(--r-md); background:var(--warn-surface); color:var(--text); font-size:.84rem; line-height:1.5; }
    .duplicate-candidate { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:.75rem; padding:.7rem .85rem; border:0; border-bottom:1px solid var(--border); border-radius:0; background:transparent; font-size:.875rem; }
    .duplicate-candidate strong,.duplicate-candidate span { display:block; }
    .duplicate-candidate span { margin-top:.2rem; color:var(--text-muted); font-size:.78rem; }

    .add-selection-message { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin:1rem 0 0; padding:.65rem .85rem; border:1px solid var(--border); border-radius:var(--r-md); background:var(--hover); font-size:.84rem; }
    .add-selection-message[hidden] { display:none; }
    .add-selection-message button { flex-shrink:0; min-height:30px; padding:.3rem .6rem; border:1px solid var(--border); border-radius:var(--r-btn); background:transparent; color:var(--text); font:inherit; font-size:.8125rem; font-weight:500; cursor:pointer; transition:background-color .12s,border-color .12s,color .12s; }
    .add-selection-message button:hover { background:var(--flip-bg); border-color:var(--flip-bg); color:var(--flip-fg); }

    /* ---------- Popups (the only place a shadow is used) ---------- */
    .add-suggestion-popup { position:fixed; z-index:1300; display:grid; gap:0; max-height:300px; overflow:auto; padding:.25rem; border:1px solid var(--border); border-radius:var(--r-lg); background:var(--surface); box-shadow:0 8px 24px rgba(0,0,0,.18); }
    .add-suggestion-popup[hidden] { display:none; }
    .add-suggestion-option { width:100%; padding:.55rem .65rem; border:0; border-radius:var(--r-sm); background:transparent; color:var(--text); text-align:left; font:inherit; font-size:.875rem; cursor:pointer; }
    .add-suggestion-option:hover,.add-suggestion-option:focus-visible { background:var(--hover); outline:none; }
    .add-suggestion-option strong,.add-suggestion-option span { display:block; }
    .add-suggestion-option span { margin-top:.15rem; color:var(--text-muted); font-size:.78rem; }
    .add-suggestion-message { padding:.6rem .65rem; color:var(--text-muted); font-size:.82rem; }

    .registry-duplicate-popup { position:absolute; z-index:15; top:5rem; left:0; width:min(100%,700px); padding:.65rem .8rem; border:1px solid var(--warn-border); border-radius:var(--r-md); background:var(--surface); box-shadow:0 8px 24px rgba(0,0,0,.18); color:var(--text); font-size:.82rem; line-height:1.45; }
    .registry-duplicate-popup[hidden] { display:none; }
    .registry-duplicate-popup strong,.registry-duplicate-popup span { display:block; }
    .registry-duplicate-popup span { margin-top:.25rem; color:var(--text-muted); }

    /* ---------- Buttons: neutral primary ---------- */
    .delib-submit,.subtract-search button { min-height:38px; padding:.5rem .9rem; border:1px solid var(--text); border-radius:var(--r-btn); background:transparent; color:var(--text); font:inherit; font-size:.875rem; font-weight:500; cursor:pointer; transition:background-color .12s,border-color .12s,color .12s; }
    .delib-submit:hover:not(:disabled),.subtract-search button:hover:not(:disabled) { background:var(--flip-bg); border-color:var(--flip-bg); color:var(--flip-fg); }
    .delib-submit:focus-visible,.subtract-search button:focus-visible { outline:2px solid var(--ring); outline-offset:2px; }
    .delib-submit:disabled,.subtract-form-actions button:disabled { border-color:var(--border); background:transparent; color:var(--text-muted); cursor:not-allowed; }

    .delib-form-actions { display:flex; justify-content:flex-end; margin-top:1.25rem; padding-top:1rem; border-top:1px solid var(--border); }
    .delib-status { margin-top:.75rem; color:var(--text-muted); font-size:.84rem; }
    .delib-status.error { color:var(--danger); }
    .delib-status.success { color:var(--ok); }

    /* ---------- Subtract ---------- */
    .subtract-search { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.5rem; }
    .subtract-suggestions { display:grid; margin:.5rem 0 1rem; border-top:1px solid var(--border); }
    .subtract-suggestions:empty { border-top:0; margin:0; }
    .subtract-suggestion { width:100%; padding:.65rem .5rem; border:0; border-bottom:1px solid var(--border); border-radius:0; background:transparent; color:var(--text); text-align:left; font:inherit; font-size:.875rem; cursor:pointer; transition:background-color .12s; }
    .subtract-suggestion:hover,.subtract-suggestion:focus-visible { background:var(--hover); outline:none; }
    .subtract-suggestion strong,.subtract-suggestion span { display:block; }
    .subtract-suggestion span { margin-top:.2rem; color:var(--text-muted); font-size:.78rem; }
    .subtract-record[hidden] { display:none; }
    .subtract-record { margin-top:1rem; padding-top:1rem; border-top:1px solid var(--border); }
    .subtract-record-heading { margin:0 0 .75rem; font-size:.9375rem; font-weight:600; }
    .subtract-record input[readonly] { background:color-mix(in srgb,var(--border) 30%,var(--surface)); cursor:default; }
    .subtract-form-actions { display:flex; justify-content:flex-end; margin-top:1rem; }

    /* ---------- Dialogs ---------- */
    .delib-confirmation { width:min(520px,calc(100% - 2rem)); max-height:85vh; padding:0; overflow:auto; border:1px solid var(--border); border-radius:var(--r-lg); background:var(--surface); color:var(--text); box-shadow:0 16px 48px rgba(0,0,0,.3); }
    .delib-confirmation::backdrop { background:rgba(0,0,0,.6); }
    .confirmation-content { padding:1.25rem; }
    .confirmation-content h2 { margin:0 0 .4rem; font-size:1.0625rem; font-weight:600; letter-spacing:-.01em; }
    .confirmation-content p { margin:0; color:var(--text-muted); font-size:.875rem; line-height:1.5; }
    .confirmation-details { display:grid; grid-template-columns:minmax(100px,.7fr) minmax(0,1.3fr); gap:0 .8rem; margin:1rem 0; padding:0; border:0; border-top:1px solid var(--border); border-radius:0; background:transparent; font-size:.84rem; }
    .confirmation-details dt,.confirmation-details dd { padding:.45rem 0; border-bottom:1px solid var(--border); }
    .confirmation-details dt { color:var(--text-muted); }
    .confirmation-details dd { margin:0; font-weight:500; overflow-wrap:anywhere; }
    .confirmation-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:1rem; }
    .confirmation-actions button { min-height:36px; padding:.45rem .85rem; border:1px solid var(--border); border-radius:var(--r-btn); background:transparent; color:var(--text); font:inherit; font-size:.875rem; font-weight:500; cursor:pointer; transition:background-color .12s,border-color .12s,color .12s; }
    .confirmation-actions button:hover { background:var(--flip-bg); border-color:var(--flip-bg); color:var(--flip-fg); }
    .confirmation-actions button.confirm-primary { border-color:var(--text); background:transparent; color:var(--text); }
    .confirmation-actions button.confirm-primary:hover { background:var(--flip-bg); border-color:var(--flip-bg); color:var(--flip-fg); }
    .confirmation-actions button:focus-visible { outline:2px solid var(--ring); outline-offset:2px; }

    @media(max-width:680px) {
        .deliberation-grid { grid-template-columns:1fr; }
        .delib-field.full { grid-column:auto; }
        .registry-inputs { gap:.15rem; }
        .registry-inputs input { height:38px; font-size:.8rem; }
        .duplicate-candidate { grid-template-columns:1fr; }
        .subtract-search { grid-template-columns:1fr; }
        .deliberation-tabs { overflow-x:auto; }
    }

    @media (prefers-reduced-motion:reduce) {
        .deliberation-page *,.delib-confirmation { transition:none !important; }
    }
</style>

<div class="deliberation-page">
    <header class="deliberation-hero">
        <a class="monthly-back-button" href="{{ route('form-generator') }}#deliberation">&larr; Back to Form Generator</a>
        <h1>Deliberation Form</h1>
        <p>Enter a member's information. Asterisks mark required fields. Existing records can be found, but cannot be changed or reused in Add.</p>
    </header>

    <nav class="deliberation-tabs" aria-label="Deliberation action">
        <button class="deliberation-tab" type="button" id="addTab" aria-selected="true">Add</button>
        <button class="deliberation-tab" type="button" id="subtractTab" aria-selected="false">Subtract</button>
        <a class="deliberation-tab-link" href="{{ route('forms.monthly.modify') }}">Modify</a>
    </nav>
    <p class="monthly-session-required" id="monthlySessionRequired" @if ($monthlySession) hidden @endif>
        Start the monthly session using the session control in the upper-right before adding, subtracting, or modifying records. Changes are locked until a session is active.
    </p>

    <form class="deliberation-form" id="deliberationForm"
        data-session-active="{{ $monthlySession ? 'true' : 'false' }}"
        data-duplicate-url="{{ route('forms.monthly.deliberation.duplicates') }}"
        data-suggestions-url="{{ route('forms.monthly.deliberation.suggestions') }}"
        data-registry-check-url="{{ route('forms.monthly.deliberation.registry-check') }}"
        data-store-url="{{ route('forms.monthly.deliberation.store') }}"
        data-subtract-search-url="{{ route('forms.monthly.deliberation.subtract.search') }}"
        data-subtract-url="{{ route('forms.monthly.deliberation.subtract') }}"
        data-csrf="{{ csrf_token() }}"
        data-puroks="{{ json_encode($puroks->map(fn ($purok): array => ['id' => (string) $purok->id, 'number' => (string) $purok->purok_number, 'name' => $purok->purok_name, 'grupo_start' => $purok->grupo_start, 'grupo_end' => $purok->grupo_end])->values()) }}">
        <div class="deliberation-grid">
            <div class="delib-field full">
                <label for="delib-full-name">Full Name <span class="required-mark" aria-label="required">*</span></label>
                <input id="delib-full-name" name="full_name" type="text" maxlength="150" autocomplete="name" required>
                <div class="add-suggestion-popup" id="addSuggestions" role="listbox" aria-label="Matching existing records" hidden></div>
                <p class="delib-error" data-error="full_name"></p>
            </div>

            <div class="delib-field full registry-field">
                <span class="delib-label">Registry <span class="required-mark" aria-label="required">*</span></span>
                <div class="registry-inputs" id="registryInputs" aria-label="13-character registry number">
                    @for ($index = 0; $index < 13; $index++)
                        <input type="text" maxlength="1" autocomplete="off" aria-label="Registry character {{ $index + 1 }}" data-registry-character="{{ $index }}" {{ $index === 0 ? 'autofocus' : '' }}>
                    @endfor
                </div>
                <input id="delib-registry" name="registry" type="hidden">
                <label class="registry-options" for="delib-rfid">
                    <input id="delib-rfid" name="is_rfid" type="checkbox" value="1">
                    This member has RFID instead of a registry number
                </label>
                <p class="delib-help">Enter exactly 13 letters or numbers, or select RFID.</p>
                <div class="registry-duplicate-popup" id="registryDuplicatePopup" role="alert" aria-live="assertive" hidden></div>
                <p class="delib-error" data-error="registry"></p>
            </div>

            <div class="delib-field full">
                <span class="delib-label">Gender <span class="required-mark" aria-label="required">*</span></span>
                <div class="delib-choice-row">
                    <label class="delib-choice"><input type="radio" name="gender" value="Male" required> Male</label>
                    <label class="delib-choice"><input type="radio" name="gender" value="Female" required> Female</label>
                </div>
                <p class="delib-error" data-error="gender"></p>
            </div>

            <div class="delib-field">
                <label for="delib-purok">Purok <span class="required-mark" aria-label="required">*</span></label>
                <select id="delib-purok" name="purok_id" required>
                    <option value="">Select Purok</option>
                    @foreach ($puroks as $purok)
                        <option value="{{ $purok->id }}">{{ $purok->purok_name ?: 'Purok '.$purok->purok_number }} ({{ $purok->grupo_start }}-{{ $purok->grupo_end }})</option>
                    @endforeach
                </select>
                <p class="delib-error" data-error="purok"></p>
            </div>
            <div class="delib-field">
                <label for="delib-grupo">Grupo <span style="color:var(--text-muted);font-weight:500">(Optional)</span></label>
                <select id="delib-grupo" name="grupo" disabled><option value="">Select Purok first</option></select>
                <p class="delib-error" data-error="grupo"></p>
            </div>

            <div class="delib-field">
                <label for="delib-kapisanan">Kapisanan <span class="required-mark" aria-label="required">*</span></label>
                <select id="delib-kapisanan" name="kapisanan" required>
                    <option value="">Select Kapisanan</option>
                    <option value="Buklod">Buklod</option>
                    <option value="Kadiwa">Kadiwa</option>
                    <option value="Binhi">Binhi</option>
                    <option value="Suliranin">Suliranin</option>
                </select>
                <p class="delib-help" id="kapisananHint">Kapisanan is auto-selected from Date of Birth and marital status; Not Married (0) is the default.</p>
                <p class="delib-error" data-error="kapisanan"></p>
            </div>
            <div class="delib-field">
                <label for="delib-status">Status <span class="required-mark" aria-label="required">*</span></label>
                <select id="delib-status" name="status" required>
                    <option value="">Select Status</option>
                    <option value="MS">MS</option>
                    <option value="UWP">UWP</option>
                </select>
                <p class="delib-error" data-error="status"></p>
            </div>

            <div class="delib-field">
                <label for="delib-baptism">Date of Baptism <span style="color:var(--text-muted);font-weight:500">(Optional)</span></label>
                <input id="delib-baptism" name="date_of_baptism" type="date" max="{{ now()->toDateString() }}">
                <p class="delib-error" data-error="date_of_baptism"></p>
            </div>
            <div class="delib-field">
                <label for="delib-evangelist">Evangelist <span style="color:var(--text-muted);font-weight:500">(Optional)</span></label>
                <input id="delib-evangelist" name="evangelist" type="text" maxlength="150">
                <p class="delib-error" data-error="evangelist"></p>
            </div>
            <div class="delib-field">
                <label for="delib-birth">Date of Birth <span style="color:var(--text-muted);font-weight:500">(Optional)</span></label>
                <input id="delib-birth" name="date_of_birth" type="date" max="{{ now()->toDateString() }}">
                <p class="delib-error" data-error="date_of_birth"></p>
            </div>
            <div class="delib-field">
                <label for="delib-married">Marital status (defaults to Not Married)</label>
                <select id="delib-married" name="is_married">
                    <option value="0" selected>Not Married</option>
                    <option value="1">Married</option>
                </select>
                <p class="delib-error" data-error="is_married"></p>
            </div>
            <div class="delib-field full">
                <label for="delib-description">Brief Description <span style="color:var(--text-muted);font-weight:500">(Optional)</span></label>
                <input id="delib-description" name="description" type="text" maxlength="500" placeholder="Optional note for the log">
                <p class="delib-error" data-error="description"></p>
            </div>
        </div>

        <div class="duplicate-panel" id="duplicatePanel" aria-live="polite"></div>
        <div class="add-selection-message" id="addSelectionMessage" role="status" aria-live="polite" hidden>
            <span id="addSelectionText"></span>
            <button type="button" id="addSelectionClear">Clear selection</button>
        </div>
        <input type="hidden" id="reuseRecordId" name="reuse_record_id">
        <div class="delib-status" id="delibStatus" role="status" aria-live="polite"></div>
        <div class="delib-form-actions">
            <button class="delib-submit" id="delibSubmit" type="submit" @disabled(! $monthlySession)>Add to Deliberation</button>
        </div>
    </form>

    <section class="subtract-panel" id="subtractPanel" hidden>
        <p class="delib-help">Search by full name or registry. Choose a suggestion to load the existing record; its information is read-only.</p>
        <div class="subtract-search">
            <input id="subtractSearchInput" type="search" maxlength="100" autocomplete="off" placeholder="Enter a name or registry">
            <button type="button" id="subtractSearchButton">Search</button>
        </div>
        <div class="subtract-suggestions" id="subtractSuggestions" role="listbox" aria-label="Matching records"></div>
        <div class="delib-status" id="subtractSearchStatus" role="status" aria-live="polite"></div>
        <form id="subtractRecordForm" class="subtract-record" hidden>
            <input type="hidden" id="subtractRecordId">
            <h2 class="subtract-record-heading">Selected member (read-only)</h2>
            <div class="deliberation-grid">
                <div class="delib-field"><label for="subtractName">Full Name</label><input id="subtractName" readonly></div>
                <div class="delib-field"><label for="subtractRegistry">Registry</label><input id="subtractRegistry" readonly></div>
                <div class="delib-field"><label for="subtractGender">Gender</label><input id="subtractGender" readonly></div>
                <div class="delib-field"><label for="subtractPurok">Purok</label><input id="subtractPurok" readonly></div>
                <div class="delib-field"><label for="subtractGrupo">Grupo</label><input id="subtractGrupo" readonly></div>
                <div class="delib-field"><label for="subtractKapisanan">Kapisanan</label><input id="subtractKapisanan" readonly></div>
                <div class="delib-field"><label for="subtractBirth">Date of Birth</label><input id="subtractBirth" readonly></div>
                <div class="delib-field"><label for="subtractBaptism">Date of Baptism</label><input id="subtractBaptism" readonly></div>
                <div class="delib-field"><label for="subtractEvangelist">Evangelist</label><input id="subtractEvangelist" readonly></div>
                <div class="delib-field"><label for="subtractStatus">Current Status</label><input id="subtractStatus" readonly></div>
                <div class="delib-field"><label for="subtractMonthlyCode">Current Monthly Code</label><input id="subtractMonthlyCode" readonly></div>
                <div class="delib-field">
                    <label for="subtractCode">Removal Code</label>
                    <select id="subtractCode" required>
                        <option value="">Select code</option>
                        <option value="D">D</option>
                        <option value="DD">DD</option>
                        <option value="G">G</option>
                        <option value="H">H</option>
                    </select>
                </div>
                <div class="delib-field full">
                    <label for="subtractDescription">Brief Description <span style="color:var(--text-muted);font-weight:500">(Optional)</span></label>
                    <input id="subtractDescription" type="text" maxlength="500" placeholder="Optional reason for changing the status">
                </div>
            </div>
            <div class="delib-status" id="subtractStatusMessage" role="status" aria-live="polite"></div>
            <div class="subtract-form-actions"><button type="submit" class="delib-submit" id="subtractSubmit" @disabled(! $monthlySession)>Mark INACTIVE</button></div>
        </form>
    </section>
</div>

<dialog class="delib-confirmation" id="addConfirmDialog" aria-labelledby="addConfirmTitle">
    <div class="confirmation-content">
        <h2 id="addConfirmTitle">Confirm Add</h2>
        <p>Are you sure you want to add this member with the following details?</p>
        <dl class="confirmation-details" id="addConfirmDetails"></dl>
        <div class="confirmation-actions">
            <button type="button" data-dialog-cancel>Cancel</button>
            <button type="button" class="confirm-primary" id="confirmAddButton">Yes, add member</button>
        </div>
    </div>
</dialog>
<dialog class="delib-confirmation" id="subtractConfirmDialog" aria-labelledby="subtractConfirmTitle">
    <div class="confirmation-content">
        <h2 id="subtractConfirmTitle">Confirm Subtract</h2>
        <p id="subtractConfirmText">Are you sure you want to make this record inactive?</p>
        <div class="confirmation-actions">
            <button type="button" data-dialog-cancel>No</button>
            <button type="button" class="confirm-primary" id="confirmSubtractButton">Yes</button>
        </div>
    </div>
</dialog>
@include('forms.monthly-session-widget')
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('deliberationForm');
    const registryBoxes = [...document.querySelectorAll('[data-registry-character]')];
    const registryValue = document.getElementById('delib-registry');
    const rfid = document.getElementById('delib-rfid');
    const registryInputs = document.getElementById('registryInputs');
    const registryDuplicatePopup = document.getElementById('registryDuplicatePopup');
    const nameInput = document.getElementById('delib-full-name');
    const addSuggestions = document.getElementById('addSuggestions');
    const addSelectionMessage = document.getElementById('addSelectionMessage');
    const addSelectionText = document.getElementById('addSelectionText');
    const addSelectionClear = document.getElementById('addSelectionClear');
    const reuseRecordId = document.getElementById('reuseRecordId');
    const purokInput = document.getElementById('delib-purok');
    const grupoInput = document.getElementById('delib-grupo');
    const birthInput = document.getElementById('delib-birth');
    const marriedInput = document.getElementById('delib-married');
    const kapisananInput = document.getElementById('delib-kapisanan');
    const duplicatePanel = document.getElementById('duplicatePanel');
    const statusMessage = document.getElementById('delibStatus');
    const submitButton = document.getElementById('delibSubmit');
    const monthlySessionRequired = document.getElementById('monthlySessionRequired');
    let hasMonthlySession = form.dataset.sessionActive === 'true';
    const puroks = JSON.parse(form.dataset.puroks);
    let duplicates = [];
    let suggestionTimer;
    let suggestionSequence = 0;
    let activeSuggestionRequest = null;
    let registryCheckTimer;
    let registryCheckSequence = 0;
    let registryHasDuplicate = false;
    let selectedAddRecord = null;
    let requestVersion = 0;
    const subtractPanel = document.getElementById('subtractPanel');
    const addTab = document.getElementById('addTab');
    const subtractTab = document.getElementById('subtractTab');
    const subtractSearchInput = document.getElementById('subtractSearchInput');
    const subtractSearchButton = document.getElementById('subtractSearchButton');
    const subtractSuggestions = document.getElementById('subtractSuggestions');
    const subtractRecordForm = document.getElementById('subtractRecordForm');
    const subtractMessage = document.getElementById('subtractStatusMessage');
    const subtractSearchStatus = document.getElementById('subtractSearchStatus');
    const subtractSubmit = document.getElementById('subtractSubmit');
    const subtractCode = document.getElementById('subtractCode');
    const addConfirmDialog = document.getElementById('addConfirmDialog');
    const subtractConfirmDialog = document.getElementById('subtractConfirmDialog');
    let subtractSearchTimer;
    let subtractSearchSequence = 0;
    let activeSubtractSearch = null;
    let selectedSubtractAllowed = false;

    function updateSubtractAction() {
        subtractSubmit.disabled = !hasMonthlySession || !selectedSubtractAllowed;
    }

    function updateAddAction() {
        const selectedCanReactivate = selectedAddRecord?.status === 'INACTIVE'
            && !selectedAddRecord.monthly_code;
        submitButton.disabled = !hasMonthlySession
            || (selectedAddRecord ? !selectedCanReactivate : duplicates.length > 0 || registryHasDuplicate);
    }

    window.addEventListener('monthly-session-state', event => {
        hasMonthlySession = Boolean(event.detail?.active);
        monthlySessionRequired.hidden = hasMonthlySession;
        updateAddAction();
        updateSubtractAction();
    });

    function selectPanel(panel) {
        const isSubtract = panel === 'subtract';
        form.hidden = isSubtract;
        subtractPanel.hidden = !isSubtract;
        addTab.setAttribute('aria-selected', String(!isSubtract));
        subtractTab.setAttribute('aria-selected', String(isSubtract));
        const url = new URL(window.location.href);
        if (isSubtract) url.searchParams.set('mode', 'subtract');
        else url.searchParams.delete('mode');
        window.history.replaceState({}, '', url);
        window.dispatchEvent(new CustomEvent('deliberation-mode', { detail: { mode: isSubtract ? 'subtract' : 'add' } }));
    }

    function setStatus(message, kind = '') {
        statusMessage.textContent = message;
        statusMessage.className = `delib-status${kind ? ` ${kind}` : ''}`;
    }

    function setError(field, message) {
        const target = form.querySelector(`[data-error="${field}"]`);
        if (target) target.textContent = message || '';
    }

    function confirmDialog(dialog, confirmButton, confirmValue) {
        return new Promise(resolve => {
            const cancelButton = dialog.querySelector('[data-dialog-cancel]');
            const onConfirm = () => dialog.close(confirmValue);
            const onCancel = () => dialog.close('cancel');
            const onCancelEvent = event => {
                event.preventDefault();
                dialog.close('cancel');
            };
            const onClose = () => {
                confirmButton.removeEventListener('click', onConfirm);
                cancelButton.removeEventListener('click', onCancel);
                dialog.removeEventListener('cancel', onCancelEvent);
                resolve(dialog.returnValue === confirmValue);
            };
            dialog.addEventListener('close', onClose, { once: true });
            confirmButton.addEventListener('click', onConfirm);
            cancelButton.addEventListener('click', onCancel);
            dialog.addEventListener('cancel', onCancelEvent);
            dialog.showModal();
        });
    }

    function populateAddConfirmation() {
        syncRegistry();
        const gender = form.querySelector('[name="gender"]:checked')?.value || '';
        const values = [
            ['Full Name', nameInput.value.trim()],
            ['Registry', rfid.checked ? 'RFID' : registryValue.value],
            ['Gender', gender],
            ['Purok', purokInput.selectedOptions[0]?.textContent.trim() || ''],
            ['Grupo', grupoInput.value || 'Not provided'],
            ['Kapisanan', kapisananInput.value],
            ['Status', document.getElementById('delib-status').value],
            ['Date of Baptism', document.getElementById('delib-baptism').value || 'Not provided'],
            ['Evangelist', document.getElementById('delib-evangelist').value.trim() || 'Not provided'],
            ['Date of Birth', birthInput.value || 'Not provided'],
            ['Marital status', marriedInput.value === '1' ? 'Married (1)' : 'Not Married (0)'],
            ['Brief Description', document.getElementById('delib-description').value.trim() || 'Not provided'],
        ];
        if (selectedAddRecord) {
            values.unshift(['Action', `Reactivate existing record: INACTIVE -> ${document.getElementById('delib-status').value}`]);
        }
        const details = document.getElementById('addConfirmDetails');
        details.replaceChildren();
        values.forEach(([label, value]) => {
            const term = document.createElement('dt');
            term.textContent = label;
            const description = document.createElement('dd');
            description.textContent = value;
            details.append(term, description);
        });
    }

    function syncRegistry() {
        const value = registryBoxes.map(box => box.value).join('').toUpperCase();
        registryValue.value = rfid.checked ? 'RFID' : value;
        return value;
    }

    function applyRegistryMode() {
        registryInputs.classList.toggle('is-disabled', rfid.checked || Boolean(selectedAddRecord));
        registryBoxes.forEach(box => { box.disabled = rfid.checked || Boolean(selectedAddRecord); });
        syncRegistry();
    }

    function clearRegistryDuplicate() {
        registryCheckSequence++;
        clearTimeout(registryCheckTimer);
        registryHasDuplicate = false;
        registryDuplicatePopup.hidden = true;
        registryDuplicatePopup.replaceChildren();
        updateAddAction();
    }

    function showRegistryDuplicates(records) {
        registryHasDuplicate = records.length > 0;
        registryDuplicatePopup.replaceChildren();
        if (!records.length) {
            registryDuplicatePopup.hidden = true;
            updateAddAction();
            return;
        }
        const title = document.createElement('strong');
        title.textContent = 'This registry number is already assigned.';
        registryDuplicatePopup.append(title);
        records.forEach(record => {
            const match = document.createElement('span');
            match.textContent = `${record.full_name} · ${record.status}`
                + (record.purok ? ` · Purok ${record.purok}` : '')
                + (record.grupo ? `-${record.grupo}` : '');
            registryDuplicatePopup.append(match);
        });
        registryDuplicatePopup.hidden = false;
        updateAddAction();
    }

    function checkRegistryDuplicate() {
        clearTimeout(registryCheckTimer);
        const registry = registryBoxes.map(box => box.value).join('').toUpperCase();
        const sequence = ++registryCheckSequence;
        if (rfid.checked || selectedAddRecord || !/^[A-Z0-9]{13}$/.test(registry)) {
            clearRegistryDuplicate();
            return;
        }
        registryCheckTimer = window.setTimeout(async () => {
            try {
                const result = await requestJson(form.dataset.registryCheckUrl, 'POST', {
                    registry,
                    is_rfid: false,
                });
                if (sequence === registryCheckSequence) showRegistryDuplicates(result.duplicates);
            } catch (error) {
                if (sequence !== registryCheckSequence) return;
                registryHasDuplicate = false;
                const message = document.createElement('span');
                message.textContent = error.message || 'Could not verify the registry number.';
                registryDuplicatePopup.replaceChildren(message);
                registryDuplicatePopup.hidden = false;
                updateAddAction();
            }
        }, 180);
    }

    function showSuggestionMessage(message) {
        addSuggestions.replaceChildren();
        const item = document.createElement('div');
        item.className = 'add-suggestion-message';
        item.textContent = message;
        addSuggestions.append(item);
        positionSuggestions();
        addSuggestions.hidden = false;
    }

    function positionSuggestions() {
        const bounds = nameInput.getBoundingClientRect();
        addSuggestions.style.left = `${Math.max(8, bounds.left)}px`;
        addSuggestions.style.top = `${Math.min(window.innerHeight - 310, bounds.bottom + 4)}px`;
        addSuggestions.style.width = `${Math.max(280, bounds.width)}px`;
    }

    function scheduleSuggestions() {
        clearTimeout(suggestionTimer);
        const search = nameInput.value.trim();
        const sequence = ++suggestionSequence;
        activeSuggestionRequest?.abort();
        activeSuggestionRequest = null;
        if (search.length < 2 || selectedAddRecord) {
            addSuggestions.hidden = true;
            return;
        }
        suggestionTimer = window.setTimeout(async () => {
            showSuggestionMessage('Searching existing records…');
            const controller = new AbortController();
            activeSuggestionRequest = controller;
            try {
                const url = new URL(form.dataset.suggestionsUrl, window.location.origin);
                url.searchParams.set('q', search);
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Could not search existing records.');
                if (sequence !== suggestionSequence) return;
                renderAddSuggestions(result.records, search);
            } catch (error) {
                if (error.name === 'AbortError' || sequence !== suggestionSequence) return;
                showSuggestionMessage(error.message || 'Could not search existing records.');
            } finally {
                if (sequence === suggestionSequence) activeSuggestionRequest = null;
            }
        }, 220);
    }

    function renderAddSuggestions(records, query) {
        addSuggestions.replaceChildren();
        if (!records.length) {
            showSuggestionMessage(`No records found for "${query}". Continue entering the new member's details.`);
            return;
        }
        records.forEach(record => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'add-suggestion-option';
            button.setAttribute('role', 'option');
            const name = document.createElement('strong');
            name.textContent = record.full_name || 'Unnamed record';
            const details = document.createElement('span');
            details.textContent = `Registry: ${record.registry || '—'} · Status: ${record.status || 'Unspecified'}`
                + (record.status === 'INACTIVE' ? ' · Can be reactivated' : ' · Already in system');
            button.append(name, details);
            button.addEventListener('click', () => selectAddSuggestion(record));
            addSuggestions.append(button);
        });
        positionSuggestions();
        addSuggestions.hidden = false;
    }

    function selectAddSuggestion(record) {
        clearTimeout(suggestionTimer);
        suggestionSequence++;
        activeSuggestionRequest?.abort();
        activeSuggestionRequest = null;
        selectedAddRecord = record;
        clearRegistryDuplicate();
        duplicates = [];
        duplicatePanel.replaceChildren();
        reuseRecordId.value = String(record.id);
        nameInput.value = record.full_name || '';
        nameInput.readOnly = true;
        const registry = (record.registry || '').toUpperCase();
        rfid.checked = registry === 'RFID';
        registryBoxes.forEach((box, index) => { box.value = registry === 'RFID' ? '' : (registry[index] || ''); });
        applyRegistryMode();
        form.querySelectorAll('[name="gender"]').forEach(input => {
            input.checked = input.value === record.gender;
        });
        purokInput.value = record.purok_id ? String(record.purok_id) : '';
        purokInput.dispatchEvent(new Event('change'));
        grupoInput.value = record.grupo || '';
        kapisananInput.value = record.kapisanan || '';
        birthInput.value = record.date_of_birth || '';
        document.getElementById('delib-baptism').value = record.date_of_baptism || '';
        document.getElementById('delib-evangelist').value = record.evangelist || '';
        marriedInput.value = record.is_married ? '1' : '0';
        document.getElementById('delib-status').value = record.status === 'INACTIVE' ? 'UWP' : record.status;
        refreshKapisanan();
        const canReactivate = record.status === 'INACTIVE' && !record.monthly_code;
        form.querySelectorAll('input, select').forEach(control => {
            if ([reuseRecordId, document.getElementById('delib-status'), document.getElementById('delib-description')].includes(control)) return;
            control.disabled = true;
        });
        if (!canReactivate) document.getElementById('delib-status').disabled = true;
        addSelectionText.textContent = canReactivate
            ? `${record.full_name} is inactive. Choose MS or UWP to reactivate this existing record; no duplicate will be created.`
            : `${record.full_name} is already in the system${record.monthly_code ? ` with pending code ${record.monthly_code}` : ''}. Active records cannot be added again.`;
        addSelectionMessage.hidden = false;
        addSuggestions.hidden = true;
        updateAddAction();
    }

    function clearAddSuggestion() {
        selectedAddRecord = null;
        clearRegistryDuplicate();
        reuseRecordId.value = '';
        form.reset();
        nameInput.readOnly = false;
        form.querySelectorAll('input, select').forEach(control => { control.disabled = false; });
        grupoInput.replaceChildren(new Option('Select Purok first', ''));
        grupoInput.disabled = true;
        registryBoxes.forEach(box => { box.value = ''; });
        registryInputs.classList.remove('is-disabled');
        registryInputs.hidden = false;
        addSelectionMessage.hidden = true;
        addSuggestions.hidden = true;
        duplicatePanel.replaceChildren();
        duplicates = [];
        registryValue.value = '';
        refreshKapisanan();
        applyRegistryMode();
        updateAddAction();
    }

    function refreshKapisanan() {
        const birth = birthInput.value;
        const married = marriedInput.value === '1';
        if (!birth) {
            kapisananInput.disabled = false;
            document.getElementById('kapisananHint').textContent = 'Kapisanan is auto-selected from Date of Birth and marital status; Not Married (0) is the default.';
            return;
        }
        if (married) kapisananInput.value = 'Buklod';
        else {
            const today = new Date();
            const birthDate = new Date(`${birth}T00:00:00`);
            let age = today.getFullYear() - birthDate.getFullYear();
            const birthdayHasPassed = today.getMonth() > birthDate.getMonth()
                || (today.getMonth() === birthDate.getMonth() && today.getDate() >= birthDate.getDate());
            if (!birthdayHasPassed) age--;
            kapisananInput.value = age < 18 ? 'Binhi' : 'Kadiwa';
        }
        kapisananInput.disabled = true;
        document.getElementById('kapisananHint').textContent = `Automatically selected: ${kapisananInput.value}.`;
    }

    function renderDuplicates(found) {
        duplicates = found;
        duplicatePanel.replaceChildren();
        if (selectedAddRecord) {
            updateAddAction();
            return;
        }
        if (!found.length) {
            updateAddAction();
            return;
        }
        updateAddAction();
        const alert = document.createElement('div');
        alert.className = 'duplicate-alert';
        alert.textContent = 'An existing match was found. Select an inactive suggestion to reactivate it; active records cannot be added again.';
        duplicatePanel.append(alert);

        found.forEach(record => {
            const card = document.createElement('div');
            card.className = 'duplicate-candidate';
            const identity = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = record.name || 'Unnamed record';
            const details = document.createElement('span');
            details.textContent = `Registry: ${record.registry || '—'} · Current status: ${record.status || 'Unspecified'}`;
            identity.append(name, details);
            card.append(identity);
            const blocked = document.createElement('span');
            blocked.textContent = record.inactive
                ? (record.monthly_code
                    ? `This inactive record has a pending monthly code (${record.monthly_code}) and cannot be reactivated yet.`
                    : 'Select this inactive member from the suggestions to autofill and reactivate the existing record.')
                : 'This existing record can be found but cannot be changed here.';
            card.append(blocked);
            duplicatePanel.append(card);
        });
    }

    async function requestJson(url, method, payload) {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': form.dataset.csrf,
            },
            body: JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validationErrors = data.errors || {};
            Object.entries(validationErrors).forEach(([field, messages]) => setError(field, messages[0]));
            throw new Error(Object.values(validationErrors).flat()[0] || data.message || 'Could not save the record.');
        }
        return data;
    }

    function clearSubtractSelection() {
        subtractRecordForm.hidden = true;
        subtractRecordForm.reset();
        document.getElementById('subtractRecordId').value = '';
        selectedSubtractAllowed = false;
        updateSubtractAction();
        subtractMessage.textContent = '';
        subtractMessage.className = 'delib-status';
    }

    function chooseSubtractRecord(record) {
        clearTimeout(subtractSearchTimer);
        subtractSearchSequence++;
        activeSubtractSearch?.abort();
        activeSubtractSearch = null;
        subtractSearchButton.disabled = false;
        subtractSearchInput.value = '';
        subtractSuggestions.replaceChildren();
        subtractSearchStatus.textContent = '';
        subtractSearchStatus.className = 'delib-status';
        const fields = {
            subtractName: record.full_name,
            subtractRegistry: record.registry || '—',
            subtractGender: record.gender || '—',
            subtractPurok: [record.purok, record.purok_name].filter(Boolean).join(' - ') || '—',
            subtractGrupo: record.grupo || '—',
            subtractKapisanan: record.kapisanan || '—',
            subtractBirth: record.date_of_birth || '—',
            subtractBaptism: record.date_of_baptism || '—',
            subtractEvangelist: record.evangelist || '—',
            subtractStatus: record.status || 'Unspecified',
            subtractMonthlyCode: record.monthly_code || '—',
        };
        Object.entries(fields).forEach(([id, value]) => {
            document.getElementById(id).value = value;
        });
        document.getElementById('subtractRecordId').value = record.id;
        subtractRecordForm.hidden = false;
        const normalizedStatus = (record.status || '').trim().toUpperCase();
        const allowed = ['MS', 'UWP'].includes(normalizedStatus) && !record.monthly_code && !record.r2_10_endorsed;
        selectedSubtractAllowed = allowed;
        updateSubtractAction();
        if (record.monthly_code) {
            subtractMessage.textContent = `This record has pending monthly code "${record.monthly_code}". Clear it in Inquire / Modify before proceeding; Subtract will not overwrite it.`;
            subtractMessage.className = 'delib-status error';
        } else if (normalizedStatus === 'INACTIVE') {
            subtractMessage.textContent = 'Already Inactive.';
            subtractMessage.className = 'delib-status error';
        } else if (normalizedStatus === 'MK') {
            subtractMessage.textContent = 'MK is not removable.';
            subtractMessage.className = 'delib-status error';
        } else if (record.r2_10_endorsed) {
            subtractMessage.textContent = "You can't subtract this record and make it INACTIVE since it's already R2-10 endorsed.";
            subtractMessage.className = 'delib-status error';
        } else if (!allowed) {
            subtractMessage.textContent = 'Only MS or UWP records can be marked inactive.';
            subtractMessage.className = 'delib-status error';
        } else {
            subtractMessage.textContent = '';
            subtractMessage.className = 'delib-status';
        }
    }

    async function searchSubtractRecords() {
        clearTimeout(subtractSearchTimer);
        const sequence = ++subtractSearchSequence;
        activeSubtractSearch?.abort();
        const controller = new AbortController();
        activeSubtractSearch = controller;
        const query = subtractSearchInput.value.trim();
        clearSubtractSelection();
        subtractSuggestions.replaceChildren();
        if (query.length < 2) {
            subtractSearchStatus.textContent = 'Enter at least two characters to search.';
            subtractSearchButton.disabled = false;
            return;
        }
        subtractSearchStatus.textContent = 'Searching…';
        subtractSearchStatus.className = 'delib-status';
        subtractSearchButton.disabled = true;
        try {
            const url = new URL(form.dataset.subtractSearchUrl, window.location.origin);
            url.searchParams.set('q', query);
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            const result = await response.json();
            if (sequence !== subtractSearchSequence) return;
            if (!response.ok) throw new Error(result.message || 'Could not search records.');
            const uniqueRecords = [...new Map(result.records.map(record => [record.id, record])).values()];
            if (!uniqueRecords.length) {
                subtractSearchStatus.textContent = 'No matching records found.';
                return;
            }
            uniqueRecords.forEach(record => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'subtract-suggestion';
                button.setAttribute('role', 'option');
                const name = document.createElement('strong');
                name.textContent = record.full_name || 'Unnamed record';
                const details = document.createElement('span');
                const location = [record.purok ? `Purok ${record.purok}` : '', record.grupo ? `Grupo ${record.grupo}` : '']
                    .filter(Boolean)
                    .join(' · ');
                details.textContent = `Registry: ${record.registry || '—'} · Status: ${record.status || 'Unspecified'}`
                    + (location ? ` · ${location}` : '')
                    + (record.monthly_code ? ` · Pending code: ${record.monthly_code}` : '');
                button.append(name, details);
                button.addEventListener('click', () => chooseSubtractRecord(record));
                subtractSuggestions.append(button);
            });
            subtractSearchStatus.textContent = `${uniqueRecords.length} matching record${uniqueRecords.length === 1 ? '' : 's'} found. Select the correct member.`;
        } catch (error) {
            if (error.name === 'AbortError' || sequence !== subtractSearchSequence) return;
            subtractSearchStatus.textContent = error.message;
            subtractSearchStatus.className = 'delib-status error';
        } finally {
            if (sequence === subtractSearchSequence) {
                activeSubtractSearch = null;
                subtractSearchButton.disabled = false;
            }
        }
    }

    async function checkDuplicates() {
        const name = nameInput.value.trim();
        const registry = syncRegistry();
        if (name.length < 2) return renderDuplicates([]);
        if (!rfid.checked && registry.length > 0 && registry.length < 13) return renderDuplicates([]);
        const version = ++requestVersion;
        try {
            const result = await requestJson(form.dataset.duplicateUrl, 'POST', {
                full_name: name,
                registry: rfid.checked ? 'RFID' : registry,
                is_rfid: rfid.checked,
            });
            if (version === requestVersion) renderDuplicates(result.duplicates);
        } catch (error) {
            if (version === requestVersion) setStatus(error.message, 'error');
        }
    }

    async function submitRecord() {
        if (!hasMonthlySession) {
            setStatus('Start a monthly session before adding records.', 'error');
            return;
        }
        if (!form.reportValidity()) return;
        form.querySelectorAll('[data-error]').forEach(error => { error.textContent = ''; });
        syncRegistry();
        if (!selectedAddRecord && !rfid.checked && !/^[A-Z0-9]{13}$/.test(registryValue.value)) {
            setError('registry', 'Registry must contain exactly 13 letters or numbers, or select RFID.');
            setStatus('Complete the registry number or select RFID.', 'error');
            registryBoxes[0].focus();
            return;
        }
        const payload = selectedAddRecord ? {
            full_name: selectedAddRecord.full_name,
            registry: selectedAddRecord.registry || '',
            is_rfid: (selectedAddRecord.registry || '').toUpperCase() === 'RFID',
            gender: selectedAddRecord.gender,
            purok_id: selectedAddRecord.purok_id || '',
            grupo: selectedAddRecord.grupo || '',
            kapisanan: selectedAddRecord.kapisanan || 'Kadiwa',
            date_of_baptism: selectedAddRecord.date_of_baptism || '',
            evangelist: selectedAddRecord.evangelist || '',
            status: document.getElementById('delib-status').value,
            date_of_birth: selectedAddRecord.date_of_birth || '',
            is_married: selectedAddRecord.is_married ? '1' : '0',
            description: document.getElementById('delib-description').value,
            reuse_record_id: String(selectedAddRecord.id),
        } : Object.fromEntries(new FormData(form).entries());
        payload.is_rfid = selectedAddRecord
            ? (selectedAddRecord.registry || '').toUpperCase() === 'RFID'
            : rfid.checked;
        payload.registry = selectedAddRecord
            ? selectedAddRecord.registry
            : (rfid.checked ? 'RFID' : registryValue.value);
        payload.kapisanan = selectedAddRecord?.kapisanan || kapisananInput.value;
        submitButton.disabled = true;
        setStatus(selectedAddRecord ? 'Preparing to reactivate the existing member…' : 'Checking for duplicates…');
        try {
            if (!selectedAddRecord) {
                const duplicateCheck = await requestJson(form.dataset.duplicateUrl, 'POST', {
                    full_name: payload.full_name,
                    registry: payload.registry,
                    is_rfid: payload.is_rfid,
                });
                renderDuplicates(duplicateCheck.duplicates);
                if (duplicateCheck.duplicates.length) {
                    setStatus('This person already has a record. Select an inactive match to reactivate it; active records cannot be added again.', 'error');
                    return;
                }
            }
            populateAddConfirmation();
            document.getElementById('addConfirmTitle').textContent = selectedAddRecord
                ? 'Confirm member reactivation'
                : 'Confirm Add';
            document.querySelector('#addConfirmDialog .confirmation-content > p').textContent = selectedAddRecord
                ? 'Confirm the existing inactive record and new status. This updates the existing record; it will not create a duplicate.'
                : 'Are you sure you want to add this member with the following details?';
            document.getElementById('confirmAddButton').textContent = selectedAddRecord
                ? 'Yes, reactivate member'
                : 'Yes, add member';
            const confirmed = await confirmDialog(addConfirmDialog, document.getElementById('confirmAddButton'), 'add');
            if (!confirmed) {
                setStatus('Adding the member was cancelled.');
                return;
            }
            const result = await requestJson(form.dataset.storeUrl, 'POST', payload);
            renderDuplicates([]);
            setStatus(result.message, 'success');
            clearAddSuggestion();
            document.getElementById('kapisananHint').textContent = 'Kapisanan is auto-selected from Date of Birth and marital status; Not Married (0) is the default.';
        } catch (error) {
            setStatus(error.message, 'error');
            checkDuplicates();
        } finally {
            updateAddAction();
        }
    }

    registryBoxes.forEach((box, index) => {
        box.addEventListener('input', () => {
            box.value = box.value.replace(/[^a-z0-9]/gi, '').slice(-1).toUpperCase();
            syncRegistry();
            if (box.value && index < registryBoxes.length - 1) registryBoxes[index + 1].focus();
            checkRegistryDuplicate();
        });
        box.addEventListener('keydown', event => {
            if (event.key === 'Backspace' && !box.value && index > 0) registryBoxes[index - 1].focus();
            if (event.key === 'ArrowLeft' && index > 0) registryBoxes[index - 1].focus();
            if (event.key === 'ArrowRight' && index < registryBoxes.length - 1) registryBoxes[index + 1].focus();
        });
        box.addEventListener('paste', event => {
            const pasted = event.clipboardData?.getData('text')?.toUpperCase().replace(/[^A-Z0-9]/g, '') || '';
            if (!pasted) return;
            event.preventDefault();
            pasted.slice(0, 13).split('').forEach((character, characterIndex) => {
                registryBoxes[characterIndex].value = character;
            });
            syncRegistry();
            registryBoxes[Math.min(pasted.length, 12)].focus();
            checkRegistryDuplicate();
        });
    });

    rfid.addEventListener('change', () => {
        applyRegistryMode();
        clearRegistryDuplicate();
        if (rfid.checked) addSuggestions.hidden = true;
    });
    nameInput.addEventListener('input', () => {
        scheduleSuggestions();
    });
    addSelectionClear.addEventListener('click', clearAddSuggestion);
    addSuggestions.addEventListener('click', event => event.stopPropagation());
    document.addEventListener('click', event => {
        if (!addSuggestions.contains(event.target) && event.target !== nameInput) {
            addSuggestions.hidden = true;
        }
    });
    window.addEventListener('resize', () => {
        if (!addSuggestions.hidden) positionSuggestions();
    });
    window.addEventListener('scroll', () => {
        if (!addSuggestions.hidden) positionSuggestions();
    }, true);
    birthInput.addEventListener('change', refreshKapisanan);
    marriedInput.addEventListener('change', refreshKapisanan);
    kapisananInput.addEventListener('input', () => {
        if (['b', 'buklod'].includes(kapisananInput.value.trim().toLowerCase())) {
            marriedInput.value = '1';
        }
    });
    purokInput.addEventListener('change', () => {
        const selected = puroks.find(purok => purok.id === purokInput.value);
        grupoInput.replaceChildren();
        if (!selected) {
            grupoInput.disabled = true;
            grupoInput.add(new Option('Select Purok first', ''));
            return;
        }
        grupoInput.disabled = false;
        grupoInput.add(new Option('Select Grupo', ''));
        for (let grupo = Number(selected.grupo_start); grupo <= Number(selected.grupo_end); grupo++) {
            grupoInput.add(new Option(String(grupo), String(grupo)));
        }
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        submitRecord();
    });

    addTab.addEventListener('click', () => selectPanel('add'));
    subtractTab.addEventListener('click', () => selectPanel('subtract'));
    subtractSearchButton.addEventListener('click', searchSubtractRecords);
    subtractSearchInput.addEventListener('input', () => {
        clearTimeout(subtractSearchTimer);
        subtractSearchSequence++;
        activeSubtractSearch?.abort();
        activeSubtractSearch = null;
        subtractSearchButton.disabled = false;
        clearSubtractSelection();
        if (subtractSearchInput.value.trim().length >= 2) {
            subtractSearchTimer = window.setTimeout(searchSubtractRecords, 300);
        } else {
            subtractSuggestions.replaceChildren();
            subtractSearchStatus.textContent = '';
        }
    });
    subtractSearchInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchSubtractRecords();
        }
    });
    subtractRecordForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!hasMonthlySession) {
            subtractMessage.textContent = 'Start a monthly session before subtracting records.';
            subtractMessage.className = 'delib-status error';
            return;
        }
        if (!subtractRecordForm.reportValidity()) return;
        const recordId = document.getElementById('subtractRecordId').value;
        const selectedCode = subtractCode.value;
        if (!recordId) return;
        subtractSubmit.disabled = true;
        subtractMessage.textContent = 'Updating the record…';
        subtractMessage.className = 'delib-status';
        try {
            document.getElementById('subtractConfirmText').textContent =
                `Are you sure you want to make ${document.getElementById('subtractName').value} inactive?`;
            const confirmed = await confirmDialog(subtractConfirmDialog, document.getElementById('confirmSubtractButton'), 'subtract');
            if (!confirmed) {
                subtractMessage.textContent = 'Subtract was cancelled.';
                subtractMessage.className = 'delib-status';
                updateSubtractAction();
                return;
            }
            const result = await requestJson(form.dataset.subtractUrl, 'POST', {
                record_id: recordId,
                code: selectedCode,
                description: document.getElementById('subtractDescription').value,
            });
            document.getElementById('subtractStatus').value = result.record.status;
            document.getElementById('subtractMonthlyCode').value = result.record.monthly_code || '—';
            subtractMessage.textContent = result.message;
            subtractMessage.className = 'delib-status success';
            subtractSubmit.disabled = true;
        } catch (error) {
            subtractMessage.textContent = error.message;
            subtractMessage.className = 'delib-status error';
            updateSubtractAction();
        }
    });

    selectPanel(
        window.location.hash === '#subtract' || new URLSearchParams(window.location.search).get('mode') === 'subtract'
            ? 'subtract'
            : 'add'
    );
})();
</script>
@endpush