@extends('layouts.app')

@section('title', 'Monthly Modify')

@section('content')
<style>
    /* ---------- Local tokens (theme-aware, work in light and dark) ---------- */
    .monthly-modify,.monthly-modify-dialog {
        --r-md:8px; --r-lg:10px; --r-btn:0;
        --warn:var(--warning,#d97706);
        --ok:var(--success,#16a34a);
        --hover:color-mix(in srgb,var(--text) 6%,var(--bg));
        --ring:color-mix(in srgb,var(--text) 55%,transparent);
        --flip-bg:var(--text); --flip-fg:var(--bg);
        --warn-surface:color-mix(in srgb,var(--warn) 8%,var(--surface));
        --warn-border:color-mix(in srgb,var(--warn) 45%,var(--border));
    }
    .monthly-modify { max-width:900px; margin:0 auto; }

    /* ---------- Header: line, not card ---------- */
    .monthly-modify-hero { margin-bottom:1rem; padding:0 0 1rem; border-bottom:1px solid var(--border); }
    .monthly-modify-hero h1 { margin:.9rem 0 .3rem; font-size:1.5rem; font-weight:600; letter-spacing:-.02em; line-height:1.25; }
    .monthly-modify-hero p { margin:0; max-width:70ch; color:var(--text-muted); font-size:.875rem; line-height:1.55; }
    .monthly-modify-help { margin:.75rem 0 0; color:var(--text-muted); font-size:.78rem; line-height:1.45; }

    .monthly-back-button { display:inline-flex; align-items:center; min-height:32px; padding:.3rem .65rem; border:1px solid var(--border); border-radius:var(--r-btn); background:transparent; color:var(--text-muted); font-size:.8125rem; font-weight:500; text-decoration:none; transition:background-color .12s,border-color .12s,color .12s; }
    .monthly-back-button:hover { background:var(--flip-bg); border-color:var(--flip-bg); color:var(--flip-fg); }
    .monthly-back-button:focus-visible { outline:2px solid var(--ring); outline-offset:2px; }

    /* ---------- Action nav: underline tabs ---------- */
    .monthly-action-nav { display:flex; gap:.25rem; margin:0 0 1.25rem; padding:0; border:0; border-bottom:1px solid var(--border); background:transparent; }
    .monthly-action-nav a { display:inline-flex; align-items:center; min-height:40px; margin-bottom:-1px; padding:.5rem .85rem; border:0; border-bottom:2px solid transparent; border-radius:0; background:transparent; color:var(--text-muted); font-size:.875rem; font-weight:500; text-decoration:none; transition:color .12s,border-color .12s; }
    .monthly-action-nav a:hover { color:var(--text); }
    .monthly-action-nav a[aria-current="page"] { border-bottom-color:var(--text); color:var(--text); font-weight:600; }
    .monthly-action-nav a:focus-visible { outline:2px solid var(--ring); outline-offset:-2px; }

    /* ---------- Notice ---------- */
    .monthly-session-required { margin:0 0 1rem; padding:.7rem .85rem; border:1px solid var(--warn-border); border-radius:var(--r-md); background:var(--warn-surface); color:var(--text); font-size:.84rem; line-height:1.5; }
    .monthly-session-required[hidden] { display:none; }

    /* ---------- Form: no card ---------- */
    .monthly-modify-form { padding:0; border:0; border-radius:0; background:transparent; }
    .monthly-modify-form > label { display:block; margin-bottom:.35rem; font-size:.8125rem; font-weight:500; }
    .monthly-modify-search { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.5rem; }

    .monthly-modify input,.monthly-modify select,.monthly-modify textarea { width:100%; min-height:38px; padding:.5rem .65rem; border:1px solid var(--border); border-radius:var(--r-md); background:var(--bg); color:var(--text); font:inherit; font-size:.875rem; transition:border-color .12s; }
    .monthly-modify textarea { min-height:84px; resize:vertical; line-height:1.5; }
    .monthly-modify input:hover:not(:disabled):not([readonly]),.monthly-modify select:hover:not(:disabled),.monthly-modify textarea:hover:not(:disabled) { border-color:color-mix(in srgb,var(--text) 30%,var(--border)); }
    .monthly-modify input:focus-visible,.monthly-modify select:focus-visible,.monthly-modify textarea:focus-visible { outline:2px solid var(--ring); outline-offset:1px; border-color:var(--text); }
    .monthly-modify input:disabled,.monthly-modify select:disabled,.monthly-modify textarea:disabled { background:color-mix(in srgb,var(--border) 35%,var(--surface)); color:var(--text-muted); cursor:not-allowed; }
    .monthly-modify input[readonly] { background:color-mix(in srgb,var(--border) 30%,var(--surface)); cursor:default; }
    .monthly-modify input[type=number] { font-variant-numeric:tabular-nums; }

    /* ---------- Buttons: square outline, flip on hover ---------- */
    .monthly-modify-search button,.monthly-modify-actions button { min-height:38px; padding:.5rem .9rem; border:1px solid var(--text); border-radius:var(--r-btn); background:transparent; color:var(--text); font:inherit; font-size:.875rem; font-weight:500; cursor:pointer; transition:background-color .12s,border-color .12s,color .12s; }
    .monthly-modify-search button:hover:not(:disabled),.monthly-modify-actions button:hover:not(:disabled) { background:var(--flip-bg); border-color:var(--flip-bg); color:var(--flip-fg); }
    .monthly-modify-search button:focus-visible,.monthly-modify-actions button:focus-visible { outline:2px solid var(--ring); outline-offset:2px; }
    .monthly-modify-search button:disabled,.monthly-modify-actions button:disabled { border-color:var(--border); background:transparent; color:var(--text-muted); cursor:not-allowed; }

    /* ---------- Search results: divided rows ---------- */
    .monthly-modify-suggestions { display:grid; margin:.5rem 0 1rem; border-top:1px solid var(--border); }
    .monthly-modify-suggestions:empty { border-top:0; margin:0; }
    .monthly-modify-suggestions button { display:block; width:100%; min-height:0; padding:.65rem .5rem; border:0; border-bottom:1px solid var(--border); border-radius:0; background:transparent; color:var(--text); font:inherit; font-size:.875rem; text-align:left; cursor:pointer; transition:background-color .12s; }
    .monthly-modify-suggestions button:hover,.monthly-modify-suggestions button:focus-visible { background:var(--hover); outline:none; }
    .monthly-modify-suggestions button span,.monthly-modify-suggestions button strong { display:block; }
    .monthly-modify-suggestions button strong { font-weight:500; }
    .monthly-modify-suggestions button span { margin-top:.2rem; color:var(--text-muted); font-size:.78rem; }

    /* ---------- Fields ---------- */
    .monthly-modify-fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem 1.25rem; margin-top:1rem; padding-top:1rem; border-top:1px solid var(--border); }
    .monthly-modify-fields label { display:grid; gap:.35rem; font-size:.8125rem; font-weight:500; }
    .monthly-modify-fields label[hidden] { display:none; }
    .monthly-modify-fields .wide { grid-column:1/-1; }
    .monthly-modify-actions { display:flex; justify-content:flex-end; margin-top:1.25rem; padding-top:1rem; border-top:1px solid var(--border); }
    .monthly-modify-status { min-height:1.25rem; margin-top:.75rem; color:var(--text-muted); font-size:.84rem; }
    .monthly-modify-status.error { color:var(--danger); }
    .monthly-modify-status.success { color:var(--ok); }

    /* ---------- Dialogs ---------- */
    .monthly-modify-dialog { width:min(440px,calc(100vw - 2rem)); padding:0; border:1px solid var(--border); border-radius:var(--r-lg); color:var(--text); background:var(--surface); box-shadow:0 16px 48px rgba(0,0,0,.3); }
    .monthly-modify-dialog::backdrop { background:rgba(0,0,0,.6); }
    .monthly-modify-dialog-content { padding:1.25rem; }
    .monthly-modify-dialog-content h2 { margin:0 0 .4rem; font-size:1.0625rem; font-weight:600; letter-spacing:-.01em; }
    .monthly-modify-dialog-content p { margin:0; color:var(--text-muted); font-size:.875rem; line-height:1.5; }
    .monthly-modify-dialog-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:1.25rem; }
    .monthly-modify-dialog-actions button { min-height:36px; padding:.45rem .85rem; border:1px solid var(--text); border-radius:var(--r-btn); background:transparent; color:var(--text); font:inherit; font-size:.875rem; font-weight:500; cursor:pointer; transition:background-color .12s,border-color .12s,color .12s; }
    .monthly-modify-dialog-actions button.secondary { border-color:var(--border); }
    .monthly-modify-dialog-actions button:hover { background:var(--flip-bg); border-color:var(--flip-bg); color:var(--flip-fg); }
    .monthly-modify-dialog-actions button:focus-visible { outline:2px solid var(--ring); outline-offset:2px; }
    .monthly-modify-check { display:grid; place-items:center; width:3rem; height:3rem; margin:0 auto 1rem; border:1px solid var(--ok); border-radius:50%; background:transparent; color:var(--ok); font-size:1.5rem; font-weight:600; }
    .monthly-modify-success .monthly-modify-dialog-content { text-align:center; }

    @media(max-width:600px) {
        .monthly-modify-fields { grid-template-columns:1fr; }
        .monthly-modify-fields .wide { grid-column:auto; }
        .monthly-modify-search { grid-template-columns:1fr; }
    }
    @media (prefers-reduced-motion:reduce) {
        .monthly-modify *,.monthly-modify-dialog * { transition:none !important; }
    }
</style>
<main class="monthly-modify">
    <section class="monthly-modify-hero">
        <a class="monthly-back-button" href="{{ route('form-generator') }}">&larr; Back to Form Generator</a>
        <h1>Monthly Modify</h1>
        <p>Search for a member to change their status or transfer them to a different configured Purok. Inactive records can be found but cannot be changed.</p>
    </section>
    <nav class="monthly-action-nav" aria-label="Monthly actions">
        <a href="{{ route('forms.monthly.deliberation') }}#add">Add</a>
        <a href="{{ route('forms.monthly.deliberation') }}#subtract">Subtract</a>
        <a href="{{ route('forms.monthly.modify') }}" aria-current="page">Modify</a>
    </nav>
    <p class="monthly-session-required" id="monthlySessionRequired" @if ($monthlySession) hidden @endif>
        Start the monthly session using the session control in the upper-right before modifying records. You can search records, but changes are locked until a session is active.
    </p>
    <section class="monthly-modify-form">
        <label for="monthlyModifySearch"><strong>Find a member</strong></label>
        <div class="monthly-modify-search">
            <input id="monthlyModifySearch" type="search" minlength="2" maxlength="100" autocomplete="off" placeholder="Name or registry number">
            <button id="monthlyModifySearchButton" type="button">Search</button>
        </div>
        <div class="monthly-modify-suggestions" id="monthlyModifySuggestions" role="listbox"></div>
        <form id="monthlyModifyForm" data-session-active="{{ $monthlySession ? 'true' : 'false' }}" hidden>
            @csrf
            <input type="hidden" name="record_id" id="monthlyModifyRecordId">
            <div class="monthly-modify-fields">
                <label class="wide">Selected member
                    <input id="monthlyModifyMember" type="text" readonly>
                </label>
                <label>Current Purok / Grupo
                    <input id="monthlyModifyCurrent" type="text" readonly>
                </label>
                <label>Current status
                    <input id="monthlyModifyStatus" type="text" readonly>
                </label>
                <label class="wide">Change type
                    <select id="monthlyModifyAction" name="action" required>
                        <option value="">Select an action</option>
                        <option value="status">Change status</option>
                        <option value="transfer">Transfer to another Purok</option>
                    </select>
                </label>
                <label id="monthlyModifyStatusField" hidden>New status
                    <select id="monthlyModifyNewStatus" name="status" disabled>
                        <option value="">Select status</option>
                        <option value="MS">MS</option>
                        <option value="UWP">UWP</option>
                        <option value="MK">MK</option>
                    </select>
                </label>
                <label class="wide" id="monthlyModifyEndorsementReasonField" hidden>
                    Reason for changing status to MK <span aria-hidden="true">*</span>
                    <textarea id="monthlyModifyEndorsementReason" name="endorsement_reason" maxlength="2000" rows="3" placeholder="Enter the reason for this R2-10/MK status change" disabled></textarea>
                </label>
                <label id="monthlyModifyPurokField" hidden>Destination Purok
                    <select id="monthlyModifyPurok" name="purok_id" required>
                        <option value="">Select a Purok</option>
                        @foreach ($puroks as $purok)
                            <option value="{{ $purok->id }}" data-min="{{ $purok->grupo_start }}" data-max="{{ $purok->grupo_end }}">
                                {{ $purok->purok_name ?: 'Purok '.$purok->purok_number }} ({{ $purok->grupo_start }}-{{ $purok->grupo_end }})
                            </option>
                        @endforeach
                    </select>
                </label>
                <label id="monthlyModifyGrupoField" hidden>Destination Grupo
                    <input id="monthlyModifyGrupo" name="grupo" type="number" min="1" max="24" step="1" required disabled>
                </label>
                <label class="wide" id="monthlyModifyDescriptionField" hidden>Optional description
                    <input name="description" type="text" maxlength="500" placeholder="Reason or note for the change" disabled>
                </label>
            </div>
            <p class="monthly-modify-help" id="monthlyModifyRangeHelp">Choose the destination Purok to see its allowed Grupo range.</p>
            <div class="monthly-modify-actions"><button id="monthlyModifySave" type="submit" disabled>Review change</button></div>
        </form>
        <div class="monthly-modify-status" id="monthlyModifyMessage" role="status" aria-live="polite"></div>
    </section>
</main>
<dialog class="monthly-modify-dialog" id="monthlyModifyConfirmDialog" aria-labelledby="monthlyModifyConfirmTitle">
    <div class="monthly-modify-dialog-content">
        <h2 id="monthlyModifyConfirmTitle">Confirm record modification</h2>
        <p id="monthlyModifyConfirmText"></p>
        <div class="monthly-modify-dialog-actions">
            <button type="button" class="secondary" id="monthlyModifyCancelConfirm">Cancel</button>
            <button type="button" id="monthlyModifyConfirmButton">Confirm change</button>
        </div>
    </div>
</dialog>
<dialog class="monthly-modify-dialog monthly-modify-success" id="monthlyModifySuccessDialog" aria-labelledby="monthlyModifySuccessTitle">
    <div class="monthly-modify-dialog-content">
        <div class="monthly-modify-check" aria-hidden="true">✓</div>
        <h2 id="monthlyModifySuccessTitle">Record modified</h2>
        <p id="monthlyModifySuccessText">The record was updated successfully.</p>
        <div class="monthly-modify-dialog-actions">
            <button type="button" id="monthlyModifySuccessClose">Done</button>
        </div>
    </div>
</dialog>
<script>
(() => {
    const searchInput = document.getElementById('monthlyModifySearch');
    const searchButton = document.getElementById('monthlyModifySearchButton');
    const suggestions = document.getElementById('monthlyModifySuggestions');
    const form = document.getElementById('monthlyModifyForm');
    const message = document.getElementById('monthlyModifyMessage');
    const purok = document.getElementById('monthlyModifyPurok');
    const grupo = document.getElementById('monthlyModifyGrupo');
    const save = document.getElementById('monthlyModifySave');
    const action = document.getElementById('monthlyModifyAction');
    const statusField = document.getElementById('monthlyModifyStatusField');
    const statusSelect = document.getElementById('monthlyModifyNewStatus');
    const endorsementReasonField = document.getElementById('monthlyModifyEndorsementReasonField');
    const endorsementReasonInput = document.getElementById('monthlyModifyEndorsementReason');
    const purokContainer = document.getElementById('monthlyModifyPurokField');
    const grupoContainer = document.getElementById('monthlyModifyGrupoField');
    const descriptionField = document.getElementById('monthlyModifyDescriptionField');
    const descriptionInput = form.elements.description;
    const rangeHelp = document.getElementById('monthlyModifyRangeHelp');
    const confirmDialog = document.getElementById('monthlyModifyConfirmDialog');
    const confirmText = document.getElementById('monthlyModifyConfirmText');
    const confirmButton = document.getElementById('monthlyModifyConfirmButton');
    const cancelConfirmButton = document.getElementById('monthlyModifyCancelConfirm');
    const successDialog = document.getElementById('monthlyModifySuccessDialog');
    const successText = document.getElementById('monthlyModifySuccessText');
    const closeSuccessButton = document.getElementById('monthlyModifySuccessClose');
    const monthlySessionRequired = document.getElementById('monthlySessionRequired');
    let hasMonthlySession = form.dataset.sessionActive === 'true';
    let selectedRecord = null;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || form.querySelector('[name="_token"]').value;
    const setMessage = (text, type = '') => {
        message.textContent = text;
        message.className = `monthly-modify-status ${type}`;
    };
    const confirmModification = text => new Promise(resolve => {
        confirmText.textContent = text;
        const cleanup = () => {
            confirmButton.removeEventListener('click', onConfirm);
            cancelConfirmButton.removeEventListener('click', onCancel);
            confirmDialog.removeEventListener('cancel', onCancelEvent);
        };
        const onConfirm = () => confirmDialog.close('confirm');
        const onCancel = () => confirmDialog.close('cancel');
        const onCancelEvent = event => {
            event.preventDefault();
            confirmDialog.close('cancel');
        };
        confirmDialog.addEventListener('close', () => {
            cleanup();
            resolve(confirmDialog.returnValue === 'confirm');
        }, { once: true });
        confirmButton.addEventListener('click', onConfirm);
        cancelConfirmButton.addEventListener('click', onCancel);
        confirmDialog.addEventListener('cancel', onCancelEvent);
        confirmDialog.showModal();
    });
    const resetModifyForm = () => {
        selectedRecord = null;
        form.reset();
        form.hidden = true;
        searchInput.value = '';
        suggestions.replaceChildren();
        save.disabled = true;
        grupo.min = '1';
        grupo.max = '24';
        setMessage('');
    };
    const refreshAction = () => {
        const unavailable = !hasMonthlySession || !selectedRecord
            || (selectedRecord.status || '').toUpperCase() === 'INACTIVE'
            || Boolean(selectedRecord.monthly_code)
            || !['MS', 'UWP', 'MK'].includes((selectedRecord.status || '').toUpperCase());
        const transferring = action.value === 'transfer';
        const changingStatus = action.value === 'status';
        const samePurok = transferring && Boolean(selectedRecord?.purok_id)
            && purok.value === String(selectedRecord.purok_id);
        action.disabled = unavailable;
        statusField.hidden = !changingStatus;
        statusSelect.disabled = unavailable || !changingStatus;
        const changingToMk = changingStatus && statusSelect.value === 'MK';
        endorsementReasonField.hidden = unavailable || !changingToMk;
        endorsementReasonInput.disabled = unavailable || !changingToMk;
        endorsementReasonInput.required = !unavailable && changingToMk;
        purokContainer.hidden = !transferring;
        purok.disabled = unavailable || !transferring;
        grupoContainer.hidden = !transferring;
        grupo.disabled = unavailable || !transferring;
        descriptionField.hidden = unavailable || (!transferring && !changingStatus);
        descriptionInput.disabled = unavailable || (!transferring && !changingStatus);
        [...purok.options].forEach(option => {
            option.disabled = Boolean(option.value && selectedRecord?.purok_id && option.value === String(selectedRecord.purok_id));
        });
        const currentStatus = (selectedRecord?.status || '').toUpperCase();
        // R2-10 endorsed MS/UWP records may only move to MK (the server enforces this too).
        const endorsedSwapBlocked = Boolean(selectedRecord?.r2_10_endorsed) && ['MS', 'UWP'].includes(currentStatus);
        [...statusSelect.options].forEach(option => {
            option.disabled = Boolean(option.value && (
                (currentStatus === 'MK' && option.value !== 'MK')
                || (endorsedSwapBlocked && ['MS', 'UWP'].includes(option.value) && option.value !== currentStatus)
            ));
        });
        if (!hasMonthlySession) {
            rangeHelp.textContent = 'Start a monthly session before modifying records.';
        } else if (unavailable) {
            rangeHelp.textContent = selectedRecord?.monthly_code
                ? `Blocked: this record already has monthly code "${selectedRecord.monthly_code}". Clear it in Inquire / Modify before proceeding.`
                : (selectedRecord?.status || '').toUpperCase() === 'INACTIVE'
                    ? 'Inactive records can be searched but cannot be changed.'
                    : 'Only MS, UWP, and MK records can be changed.';
        } else if (samePurok) {
            rangeHelp.textContent = 'Select a different configured Purok. For a Grupo-only correction, use Inquire / Modify.';
        } else if (changingStatus && endorsedSwapBlocked) {
            rangeHelp.textContent = `You can't change ${currentStatus} to ${currentStatus === 'MS' ? 'UWP' : 'MS'} since it's already R2-10 endorsed. It can only move to MK.`;
        }
        save.disabled = unavailable || (!transferring && !changingStatus)
            || samePurok
            || (transferring && (!purok.value || !grupo.value || !grupo.checkValidity()))
            || (changingStatus && (!statusSelect.value
                || statusSelect.value === selectedRecord?.status
                || (changingToMk && !endorsementReasonInput.value.trim())));
        save.textContent = changingStatus ? 'Review status change' : 'Review transfer';
    };
    window.addEventListener('monthly-session-state', event => {
        hasMonthlySession = Boolean(event.detail?.active);
        monthlySessionRequired.hidden = hasMonthlySession;
        refreshAction();
    });
    const showRecord = record => {
        selectedRecord = record;
        document.getElementById('monthlyModifyRecordId').value = record.id;
        document.getElementById('monthlyModifyMember').value = `${record.name}${record.registry ? ` (${record.registry})` : ''}`;
        document.getElementById('monthlyModifyCurrent').value = `${record.purok_name} / Grupo ${record.grupo || 'Not set'}`;
        document.getElementById('monthlyModifyStatus').value = record.status || 'Not set';
        action.value = '';
        statusSelect.value = '';
        purok.value = '';
        grupo.value = '';
        descriptionInput.value = '';
        endorsementReasonInput.value = '';
        form.hidden = false;
        suggestions.replaceChildren();
        searchInput.value = record.name;
        refreshAction();
        setMessage('');
    };
    const search = async () => {
        const q = searchInput.value.trim();
        suggestions.replaceChildren();
        form.hidden = true;
        selectedRecord = null;
        save.disabled = true;
        setMessage('');
        if (q.length < 2) {
            setMessage('Enter at least two characters to search.', 'error');
            return;
        }
        searchButton.disabled = true;
        try {
            const response = await fetch(`{{ route('forms.monthly.modify.search') }}?q=${encodeURIComponent(q)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Could not search records.');
            if (!payload.records.length) {
                setMessage('No matching records found.');
                return;
            }
            payload.records.forEach(record => {
                const button = document.createElement('button');
                button.type = 'button';
                button.setAttribute('role', 'option');
                const name = document.createElement('strong');
                name.textContent = record.name;
                const detail = document.createElement('span');
                detail.textContent = `${record.registry || 'No registry'} | ${record.purok_name} / Grupo ${record.grupo || 'Not set'} | ${record.status || 'No status'}${record.monthly_code ? ` | Monthly code: ${record.monthly_code}` : ''}`;
                button.append(name, detail);
                button.addEventListener('click', () => showRecord(record));
                suggestions.append(button);
            });
            setMessage('Select the correct member from the results.');
        } catch (error) {
            setMessage(error.message || 'Search failed.', 'error');
        } finally {
            searchButton.disabled = false;
        }
    };
    const updateRange = () => {
        const option = purok.selectedOptions[0];
        const min = option?.dataset.min || '1';
        const max = option?.dataset.max || '24';
        grupo.min = min;
        grupo.max = max;
        grupo.step = '1';
        if (grupo.value && (Number(grupo.value) < Number(min) || Number(grupo.value) > Number(max))) {
            grupo.value = '';
        }
        if (option) rangeHelp.textContent = `Grupo must be between ${min} and ${max} for the selected Purok.`;
        else rangeHelp.textContent = 'Choose the destination Purok to see its allowed Grupo range.';
        refreshAction();
    };
    searchButton.addEventListener('click', search);
    searchInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); search(); }
    });
    purok.addEventListener('change', updateRange);
    action.addEventListener('change', refreshAction);
    statusSelect.addEventListener('change', refreshAction);
    endorsementReasonInput.addEventListener('input', refreshAction);
    grupo.addEventListener('input', refreshAction);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!hasMonthlySession) {
            setMessage('Start a monthly session before modifying records.', 'error');
            return;
        }
        if (!selectedRecord) return;
        const changeStatus = action.value === 'status';
        const confirmationText = changeStatus
                ? `Are you sure? ${selectedRecord.name} will change from ${selectedRecord.status} to ${statusSelect.value}. The change will be saved to the database.`
                : `Are you sure? ${selectedRecord.name} will transfer from ${selectedRecord.purok_name} / Grupo ${selectedRecord.grupo} to ${purok.selectedOptions[0].textContent.trim()} / Grupo ${grupo.value}. The change will be saved to the database.`;
        if (!await confirmModification(confirmationText)) return;
        save.disabled = true;
        setMessage('Saving change...');
        try {
            const formData = new FormData();
            formData.set('record_id', String(selectedRecord.id));
            formData.set('action', action.value);
            if (changeStatus) {
                formData.set('status', statusSelect.value);
                if (statusSelect.value === 'MK') {
                    formData.set('endorsement_reason', endorsementReasonInput.value.trim());
                }
            } else {
                formData.set('purok_id', purok.value);
                formData.set('grupo', grupo.value);
            }
            if (descriptionInput.value.trim()) formData.set('description', descriptionInput.value.trim());
            const response = await fetch(`{{ route('forms.monthly.modify.save') }}`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: formData
            });
            const result = await response.json();
            if (!response.ok) {
                const errors = Object.values(result.errors || {}).flat();
                throw new Error(errors.join(' ') || result.message || 'Could not save the transfer.');
            }
            successText.textContent = result.message || 'The record was updated successfully.';
            resetModifyForm();
            successDialog.showModal();
        } catch (error) {
            setMessage(error.message || 'Could not save the transfer.', 'error');
            save.disabled = false;
        }
    });
    closeSuccessButton.addEventListener('click', () => successDialog.close());
    successDialog.addEventListener('close', () => searchInput.focus());
})();
</script>
@include('forms.monthly-session-widget')
@endsection