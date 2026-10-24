<style>
    /* ---------- Local tokens (theme-aware, work in light and dark) ---------- */
    .monthly-session-widget,.monthly-session-dialog,.monthly-session-prompt,.monthly-session-blocker {
        --r-md:8px; --r-lg:10px; --r-btn:0;
        --ok:var(--success,#16a34a);
        --hover:color-mix(in srgb,var(--text) 6%,var(--bg));
        --ring:color-mix(in srgb,var(--text) 55%,transparent);
        --flip-bg:var(--text); --flip-fg:var(--bg);
    }

    /* ---------- Floating widget (draggable) ---------- */
    .monthly-session-widget { position:fixed; z-index:1200; top:5.25rem; right:1rem; display:flex; align-items:center; gap:.6rem; max-width:calc(100vw - 2rem); padding:.4rem .5rem .4rem .3rem; border:1px solid var(--border); border-radius:var(--r-lg); background:var(--surface); box-shadow:0 8px 24px rgba(0,0,0,.18); color:var(--text); font-size:.8125rem; touch-action:none; cursor:grab; user-select:none; }
    .monthly-session-widget[data-dragging="true"] { cursor:grabbing; box-shadow:0 12px 32px rgba(0,0,0,.28); }
    .monthly-session-widget [hidden] { display:none; }
    .monthly-session-grip { display:inline-grid; place-items:center; align-self:stretch; width:18px; border-radius:var(--r-md); color:var(--text-muted); font-size:1rem; line-height:1; cursor:grab; }
    .monthly-session-grip:hover { color:var(--text); }
    .monthly-session-grip:focus-visible { outline:2px solid var(--ring); outline-offset:1px; }
    .monthly-session-dot { flex-shrink:0; width:8px; height:8px; border-radius:50%; background:var(--text-muted); }
    .monthly-session-widget[data-active="true"] .monthly-session-dot { background:var(--ok); }
    #monthlySessionStatus { font-weight:500; }
    .monthly-session-minimized-label { display:none; color:var(--text); font-size:.8125rem; font-weight:500; }
    .monthly-session-widget[data-minimized="true"] { gap:.5rem; }
    .monthly-session-widget[data-minimized="true"] #monthlySessionStatus,.monthly-session-widget[data-minimized="true"] #monthlySessionStart,.monthly-session-widget[data-minimized="true"] #monthlySessionOpen { display:none; }
    .monthly-session-widget[data-minimized="true"] .monthly-session-minimized-label { display:inline; }

    /* ---------- Buttons: square outline, flip on hover ---------- */
    .monthly-session-widget button,.monthly-session-dialog button,.monthly-session-prompt button { min-height:34px; padding:.4rem .8rem; border:1px solid var(--text); border-radius:var(--r-btn); background:transparent; color:var(--text); font:inherit; font-size:.8125rem; font-weight:500; cursor:pointer; transition:background-color .12s,border-color .12s,color .12s; }
    .monthly-session-widget button:hover:not(:disabled),.monthly-session-dialog button:hover:not(:disabled),.monthly-session-prompt button:hover:not(:disabled) { background:var(--flip-bg); border-color:var(--flip-bg); color:var(--flip-fg); }
    .monthly-session-widget button:focus-visible,.monthly-session-dialog button:focus-visible,.monthly-session-prompt button:focus-visible { outline:2px solid var(--ring); outline-offset:2px; }
    .monthly-session-widget button:disabled,.monthly-session-dialog button:disabled,.monthly-session-prompt button:disabled { border-color:var(--border); background:transparent; color:var(--text-muted); cursor:not-allowed; }
    .monthly-session-widget .monthly-session-widget-minimize,.monthly-session-dialog header button,.monthly-session-footer .secondary,.monthly-session-prompt-actions button#monthlySessionPromptCancel { border-color:var(--border); }
    .monthly-session-widget .monthly-session-widget-minimize { width:34px; padding:.25rem; font-size:1rem; }
    .monthly-session-dialog button.monthly-undo,.monthly-session-prompt button.danger { border-color:var(--danger); color:var(--danger); }
    .monthly-session-dialog button.monthly-undo:hover:not(:disabled),.monthly-session-prompt button.danger:hover:not(:disabled) { background:var(--danger); border-color:var(--danger); color:#fff; }

    /* ---------- Activity dialog ---------- */
    .monthly-session-dialog { width:min(900px,calc(100% - 1.5rem)); max-height:85vh; padding:0; overflow:auto; border:1px solid var(--border); border-radius:var(--r-lg); background:var(--surface); color:var(--text); box-shadow:0 16px 48px rgba(0,0,0,.3); }
    .monthly-session-dialog::backdrop { background:rgba(0,0,0,.6); }
    .monthly-session-dialog header { position:sticky; top:0; display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; padding:1rem 1.25rem; border-bottom:1px solid var(--border); background:var(--surface); }
    .monthly-session-dialog h2 { margin:0; font-size:1.0625rem; font-weight:600; letter-spacing:-.01em; }
    .monthly-session-dialog p { margin:.3rem 0 0; color:var(--text-muted); font-size:.84rem; line-height:1.5; }
    .monthly-session-dialog-content { padding:.5rem 1.25rem 1rem; }
    .monthly-session-list { display:grid; max-height:55vh; overflow:auto; border-top:1px solid var(--border); }
    .monthly-session-movement { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:.8rem; padding:.75rem .25rem; border:0; border-bottom:1px solid var(--border); border-radius:0; background:transparent; }
    .monthly-session-movement small { display:block; margin-top:.2rem; color:var(--text-muted); line-height:1.4; }
    .monthly-session-entries { display:grid; gap:.3rem; }
    .monthly-session-entry { display:grid; gap:.08rem; padding:.2rem 0 .2rem .6rem; border:0; border-left:2px solid var(--text-muted); border-radius:0; background:transparent; }
    .monthly-session-entry strong { color:var(--text); font-size:.875rem; font-weight:500; }
    .monthly-session-entry small { margin:0; font-weight:500; font-variant-numeric:tabular-nums; }
    .monthly-session-personal { margin-top:.4rem !important; }
    .monthly-session-empty { padding:1.5rem; color:var(--text-muted); text-align:center; }
    .monthly-session-footer { display:flex; justify-content:flex-end; gap:.5rem; margin-top:.8rem; padding-top:.8rem; border-top:1px solid var(--border); }
    .monthly-session-finish-notice { align-self:center; margin:0 !important; color:var(--text-muted); font-size:.78rem !important; }

    /* ---------- Prompt dialog ---------- */
    .monthly-session-prompt { width:min(520px,calc(100% - 2rem)); padding:0; overflow:hidden; border:1px solid var(--border); border-radius:var(--r-lg); background:var(--surface); color:var(--text); box-shadow:0 16px 48px rgba(0,0,0,.3); }
    .monthly-session-prompt::backdrop { background:rgba(0,0,0,.6); }
    .monthly-session-prompt-content { padding:1.25rem; }
    .monthly-session-prompt h2 { margin:0 0 .5rem; font-size:1.0625rem; font-weight:600; letter-spacing:-.01em; }
    .monthly-session-prompt p { margin:0; color:var(--text-muted); font-size:.875rem; line-height:1.55; white-space:pre-line; }
    .monthly-session-prompt input { width:100%; min-height:38px; margin-top:1rem; padding:.5rem .65rem; border:1px solid var(--border); border-radius:var(--r-md); background:var(--bg); color:var(--text); font:inherit; font-size:.875rem; }
    .monthly-session-prompt input:focus-visible { outline:2px solid var(--ring); outline-offset:1px; border-color:var(--text); }
    .monthly-session-prompt-hint { margin-top:.55rem !important; font-size:.8125rem !important; }
    .monthly-session-prompt-error { margin-top:.35rem !important; color:var(--danger) !important; font-size:.8125rem !important; font-weight:500; }
    .monthly-session-prompt [hidden] { display:none; }
    .monthly-session-prompt-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:1.25rem; }

    /* ---------- Finish blocker ---------- */
    .monthly-session-blocker { position:fixed; z-index:100000; inset:0; display:grid; place-items:center; padding:1rem; background:rgba(0,0,0,.72); color:var(--text); text-align:center; }
    .monthly-session-blocker[hidden] { display:none; }
    .monthly-session-blocker-card { width:min(400px,100%); padding:2rem; border:1px solid var(--border); border-radius:var(--r-lg); background:var(--surface); box-shadow:0 16px 48px rgba(0,0,0,.3); }
    .monthly-session-spinner { width:44px; height:44px; margin:0 auto 1.25rem; border:3px solid var(--border); border-top-color:var(--text); border-radius:50%; animation:monthly-session-spin .8s linear infinite; }
    .monthly-session-success-check { display:grid; width:60px; height:60px; margin:0 auto 1.1rem; place-items:center; border:2px solid var(--ok); border-radius:50%; color:var(--ok); font-size:2rem; font-weight:600; line-height:1; animation:monthly-session-check .35s ease-out both; }
    .monthly-session-blocker h2 { margin:0; font-size:1.25rem; font-weight:600; letter-spacing:-.01em; }
    .monthly-session-blocker p { margin:.5rem 0 0; color:var(--text-muted); font-size:.875rem; line-height:1.5; }
    .monthly-session-blocker[data-state="success"] .monthly-session-spinner { display:none; }
    .monthly-session-blocker[data-state="loading"] .monthly-session-success-check { display:none; }
    .monthly-session-success-button { min-height:38px; margin-top:1.25rem; padding:.5rem 1rem; border:1px solid var(--text); border-radius:var(--r-btn); background:transparent; color:var(--text); font:inherit; font-size:.875rem; font-weight:500; cursor:pointer; transition:background-color .12s,border-color .12s,color .12s; }
    .monthly-session-success-button:hover { background:var(--flip-bg); border-color:var(--flip-bg); color:var(--flip-fg); }
    .monthly-session-success-button:focus-visible { outline:2px solid var(--ring); outline-offset:2px; }
    .monthly-session-success-button[hidden] { display:none; }
    @keyframes monthly-session-spin { to { transform:rotate(360deg); } }
    @keyframes monthly-session-check { from { transform:scale(.4); opacity:0; } to { transform:scale(1); opacity:1; } }

    @media(max-width:520px) {
        .monthly-session-widget { top:4.5rem; right:.5rem; max-width:calc(100vw - 1rem); }
        .monthly-session-widget button { padding:.35rem .55rem; font-size:.78rem; }
        .monthly-session-movement { grid-template-columns:1fr; }
        .monthly-session-movement button { justify-self:end; }
    }
    @media (prefers-reduced-motion:reduce) {
        .monthly-session-widget *,.monthly-session-dialog *,.monthly-session-prompt *,.monthly-session-success-button { transition:none !important; }
        .monthly-session-spinner { animation-duration:2.4s; }
        .monthly-session-success-check { animation:none; }
    }
</style>
@php($currentMonthlySession = $monthlySession ?? null)
@php($monthlySessionRole = mb_strtolower(trim((string) request()->session()->get('login_user.tungkulin', ''))))
@php($canFinishMonthlySession = in_array($monthlySessionRole, ['head secretary', 'secretary head'], true))
<aside class="monthly-session-widget" id="monthlySessionWidget" aria-live="polite" data-minimized="true" data-active="{{ $currentMonthlySession ? 'true' : 'false' }}">
    <span class="monthly-session-grip" id="monthlySessionGrip" role="button" tabindex="0" aria-label="Move the monthly session control. Drag it, or use the arrow keys." title="Drag to move">⠿</span>
    <span class="monthly-session-dot" aria-hidden="true"></span>
    <strong id="monthlySessionStatus">{{ $currentMonthlySession ? 'Session active · '.$currentMonthlySession->period_month?->format('F Y') : 'Monthly session' }}</strong>
    <span class="monthly-session-minimized-label">Monthly Session</span>
    <button type="button" id="monthlySessionStart" @if ($currentMonthlySession) hidden @endif>Start session</button>
    <button type="button" id="monthlySessionOpen" @if (! $currentMonthlySession) hidden @endif>
        Activity (<span id="monthlySessionCount">{{ $currentMonthlySession?->activities_count ?? 0 }}</span>)
    </button>
    <button type="button" class="monthly-session-widget-minimize" id="monthlySessionMinimize" aria-label="Expand monthly session bubble" title="Expand">+</button>
</aside>
<dialog class="monthly-session-dialog" id="monthlySessionDialog" aria-labelledby="monthlySessionDialogTitle">
    <header>
        <div>
            <h2 id="monthlySessionDialogTitle">Monthly session activity</h2>
            <p id="monthlySessionDialogInfo">Review each monthly code by Purok and Grupo.</p>
        </div>
        <button type="button" id="monthlySessionClose" aria-label="Close">Close</button>
    </header>
    <div class="monthly-session-dialog-content">
        <div class="monthly-session-list" id="monthlySessionMovements" aria-live="polite">
            <div class="monthly-session-empty">Loading monthly activity…</div>
        </div>
        <div class="monthly-session-footer">
            <button type="button" class="secondary" id="monthlySessionRefresh">Refresh</button>
            @if ($canFinishMonthlySession)
                <button type="button" id="monthlySessionFinish">Finish monthly session</button>
            @else
                <button type="button" id="monthlySessionFinish" hidden disabled>Finish monthly session</button>
                <p class="monthly-session-finish-notice" id="monthlySessionFinishNotice">Only the Head Secretary can close the monthly session.</p>
            @endif
        </div>
    </div>
</dialog>
<dialog class="monthly-session-prompt" id="monthlySessionPrompt" aria-labelledby="monthlySessionPromptTitle" aria-describedby="monthlySessionPromptMessage">
    <div class="monthly-session-prompt-content">
        <h2 id="monthlySessionPromptTitle"></h2>
        <p id="monthlySessionPromptMessage"></p>
        <input id="monthlySessionPromptInput" type="text" autocomplete="off" aria-describedby="monthlySessionPromptHint monthlySessionPromptError" hidden>
        <p class="monthly-session-prompt-hint" id="monthlySessionPromptHint" hidden></p>
        <p class="monthly-session-prompt-error" id="monthlySessionPromptError" role="alert" hidden></p>
        <div class="monthly-session-prompt-actions">
            <button type="button" id="monthlySessionPromptCancel">Cancel</button>
            <button type="button" class="primary" id="monthlySessionPromptConfirm">Continue</button>
        </div>
    </div>
</dialog>
<div class="monthly-session-blocker" id="monthlySessionBlocker" data-state="loading" role="status" aria-live="assertive" aria-modal="true" hidden>
    <div class="monthly-session-blocker-card">
        <div class="monthly-session-spinner" aria-hidden="true"></div>
        <div class="monthly-session-success-check" aria-hidden="true">✓</div>
        <h2 id="monthlySessionBlockerTitle">Finishing monthly session…</h2>
        <p id="monthlySessionBlockerMessage">Please wait. Do not close or leave this page.</p>
        <button type="button" class="monthly-session-success-button" id="monthlySessionSuccessAcknowledge" hidden>Yay, I understand there's no redos!</button>
    </div>
</div>
<script>
(() => {
    const statusUrl = @json(route('forms.monthly.session.status'));
    const startUrl = @json(route('forms.monthly.session.start'));
    const finishUrl = @json(route('forms.monthly.session.finish'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const status = document.getElementById('monthlySessionStatus');
    const start = document.getElementById('monthlySessionStart');
    const open = document.getElementById('monthlySessionOpen');
    const count = document.getElementById('monthlySessionCount');
    const dialog = document.getElementById('monthlySessionDialog');
    const info = document.getElementById('monthlySessionDialogInfo');
    const movements = document.getElementById('monthlySessionMovements');
    const finish = document.getElementById('monthlySessionFinish');
    const canFinish = @json($canFinishMonthlySession);
    const widget = document.getElementById('monthlySessionWidget');
    const grip = document.getElementById('monthlySessionGrip');
    const minimize = document.getElementById('monthlySessionMinimize');
    const promptDialog = document.getElementById('monthlySessionPrompt');
    const promptTitle = document.getElementById('monthlySessionPromptTitle');
    const promptMessage = document.getElementById('monthlySessionPromptMessage');
    const promptInput = document.getElementById('monthlySessionPromptInput');
    const promptHint = document.getElementById('monthlySessionPromptHint');
    const promptError = document.getElementById('monthlySessionPromptError');
    const promptCancel = document.getElementById('monthlySessionPromptCancel');
    const promptConfirm = document.getElementById('monthlySessionPromptConfirm');
    const blocker = document.getElementById('monthlySessionBlocker');
    const blockerTitle = document.getElementById('monthlySessionBlockerTitle');
    const blockerMessage = document.getElementById('monthlySessionBlockerMessage');
    const successAcknowledge = document.getElementById('monthlySessionSuccessAcknowledge');
    let blockedElements = [];

    const setBlocked = blocked => {
        if (blocked) {
            document.body.append(blocker);
            blockedElements = [...document.body.children]
                .filter(element => element !== blocker)
                .map(element => ({ element, wasInert: element.inert }));
            blockedElements.forEach(({ element }) => { element.inert = true; });
            blocker.hidden = false;
            document.body.style.overflow = 'hidden';
            return;
        }
        blocker.hidden = true;
        blockedElements.forEach(({ element, wasInert }) => { element.inert = wasInert; });
        blockedElements = [];
        document.body.style.overflow = '';
    };
    const showFinishing = () => {
        blocker.dataset.state = 'loading';
        blockerTitle.textContent = 'Finishing monthly session…';
        blockerMessage.textContent = 'Please wait. Do not close or leave this page.';
        successAcknowledge.hidden = true;
        setBlocked(true);
    };
    const showFinished = periodLabel => {
        blocker.dataset.state = 'success';
        blockerTitle.textContent = `Monthly ${periodLabel} done`;
        blockerMessage.textContent = 'Session finalized. No changes can be made to this session.';
        successAcknowledge.hidden = false;
        successAcknowledge.focus();
    };

    const ask = ({ title, message, confirmText = 'Continue', cancelText = 'Cancel', requiredText = null, kind = 'confirm' }) => new Promise(resolve => {
        promptTitle.textContent = title;
        promptMessage.textContent = message;
        promptInput.hidden = requiredText === null;
        promptInput.value = '';
        promptInput.placeholder = requiredText === null ? '' : `Type ${requiredText}`;
        promptInput.setAttribute('aria-label', requiredText === null ? '' : `Type ${requiredText}`);
        promptHint.hidden = requiredText === null;
        promptHint.textContent = requiredText === null ? '' : `Please type the following exactly. It is case-sensitive:\n${requiredText}`;
        promptError.hidden = true;
        promptError.textContent = '';
        promptConfirm.textContent = confirmText;
        promptConfirm.className = kind === 'danger' ? 'danger' : 'primary';
        promptCancel.textContent = cancelText;
        promptConfirm.disabled = false;

        const cleanup = () => {
            promptConfirm.removeEventListener('click', onConfirm);
            promptCancel.removeEventListener('click', onCancel);
            promptDialog.removeEventListener('cancel', onCancelEvent);
            promptDialog.removeEventListener('close', onClose);
            promptInput.removeEventListener('input', updateConfirm);
        };
        const updateConfirm = () => {
            const matches = requiredText === null || promptInput.value === requiredText;
            promptConfirm.disabled = !matches;
            promptError.hidden = matches || promptInput.value.length === 0;
            promptError.textContent = matches || promptInput.value.length === 0
                ? ''
                : 'Please match the entire sentence exactly, including capitalization.';
        };
        const onConfirm = () => {
            if (requiredText !== null && promptInput.value !== requiredText) return;
            promptDialog.close('confirm');
        };
        const onCancel = () => promptDialog.close('cancel');
        const onCancelEvent = event => {
            event.preventDefault();
            promptDialog.close('cancel');
        };
        const onClose = () => {
            const confirmed = promptDialog.returnValue === 'confirm';
            const entered = promptInput.value;
            cleanup();
            resolve(confirmed ? (requiredText === null ? true : entered) : null);
        };

        promptConfirm.addEventListener('click', onConfirm);
        promptCancel.addEventListener('click', onCancel);
        promptDialog.addEventListener('cancel', onCancelEvent);
        promptDialog.addEventListener('close', onClose);
        promptInput.addEventListener('input', updateConfirm);
        promptDialog.showModal();
        (requiredText === null ? promptConfirm : promptInput).focus();
    });

    const notify = (title, message, kind = 'confirm') => ask({
        title,
        message,
        confirmText: 'OK',
        cancelText: 'Close',
        kind,
    });

    const send = async (url, method = 'POST', body = null) => {
        const response = await fetch(url, {
            method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            ...(body ? { body: JSON.stringify(body) } : {})
        });
        const result = await response.json();
        if (!response.ok) {
            const errors = Object.values(result.errors || {}).flat();
            throw new Error(errors.join(' ') || result.message || 'Could not update the monthly session.');
        }
        return result;
    };
    const showAvailability = availability => {
        start.disabled = !availability?.allowed;
        start.title = availability?.message || '';
        start.textContent = availability?.allowed
            ? 'Monthly Session available right now!'
            : 'Monthly session already used';
        status.textContent = availability?.message || 'No active monthly session';
    };
    const renderMovements = rows => {
        movements.replaceChildren();
        if (!rows.length) {
            const empty = document.createElement('div');
            empty.className = 'monthly-session-empty';
            empty.textContent = 'No monthly activity recorded yet.';
            movements.append(empty);
            return;
        }
        rows.forEach(activity => {
            const item = document.createElement('article');
            item.className = 'monthly-session-movement';
            const details = document.createElement('div');
            const entries = document.createElement('div');
            entries.className = 'monthly-session-entries';
            (activity.entries || []).forEach(entry => {
                const row = document.createElement('div');
                row.className = 'monthly-session-entry';
                const member = document.createElement('strong');
                member.textContent = `${entry.name || activity.name || 'Unnamed record'} · ${entry.location || 'Location not set'}`;
                const code = document.createElement('small');
                code.textContent = `${entry.monthly_code || 'No code'}${entry.direction ? ` (${entry.direction})` : ''}`;
                row.append(member, code);
                entries.append(row);
            });
            const personal = document.createElement('small');
            personal.className = 'monthly-session-personal';
            personal.textContent = `${activity.registry || 'No registry'} · ${activity.gender || 'Gender not set'} · ${activity.kapisanan || 'Kapisanan not set'} · ${activity.changed_by || 'Unknown'} · ${activity.changed_at || ''}${activity.description ? ` · ${activity.description}` : ''}`;
            details.append(entries, personal);
            if (activity.undo_url) {
                const undo = document.createElement('button');
                undo.type = 'button';
                undo.className = 'monthly-undo';
                undo.textContent = `Undo ${activity.action}`;
                undo.addEventListener('click', async () => {
                    const confirmed = await ask({
                        title: `Undo monthly ${activity.action.toLowerCase()}?`,
                        message: `Undo ${activity.action.toLowerCase()} for ${activity.name}? This restores the prior monthly record state${(activity.entries || []).length > 1 ? ' and removes both paired activity entries' : ''}.`,
                        confirmText: `Undo ${activity.action}`,
                        kind: 'danger',
                    });
                    if (!confirmed) return;
                    undo.disabled = true;
                    try {
                        await send(activity.undo_url, 'DELETE');
                        await refresh();
                    } catch (error) {
                        await notify(`Could not undo ${activity.action.toLowerCase()}`, error.message, 'danger');
                        undo.disabled = false;
                    }
                });
                item.append(details, undo);
            } else {
                item.append(details);
            }
            movements.append(item);
        });
    };
    const update = result => {
        const session = result.session;
        widget.dataset.active = String(Boolean(session));
        window.dispatchEvent(new CustomEvent('monthly-session-state', {
            detail: { active: Boolean(session) }
        }));
        if (!session) {
            start.hidden = false;
            open.hidden = true;
            finish.disabled = true;
            showAvailability(result.start_availability);
            return;
        }
        status.textContent = `Active · ${session.period_month} · ${session.activity_count} activities`;
        count.textContent = session.activity_count;
        start.hidden = true;
        open.hidden = false;
        finish.disabled = !canFinish;
        info.textContent = canFinish
            ? `For ${session.period_month}. Changes remain temporary until you finish this session.`
            : `For ${session.period_month}. Changes remain temporary; only the Head Secretary can close this session.`;
    };
    let renderedActivityCount = null;
    async function refresh() {
        // The movement/activity list is heavy; only ask for it while the dialog shows it.
        const full = dialog.open;
        const response = await fetch(full ? `${statusUrl}?include_movements=1` : statusUrl, { headers: { 'Accept': 'application/json' } });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Could not load monthly session.');
        update(result);
        if (full && dialog.open) {
            renderMovements(result.activities || []);
            renderedActivityCount = result.session?.activity_count ?? null;
        }
    }
    // A host page that already polls the status endpoint (the Monthly Session page) shares its
    // lightweight result here instead of the widget running a second poll.
    window.addEventListener('monthly-session-status', event => {
        const result = event.detail;
        if (!result) return;
        update(result);
        if (dialog.open && (result.session?.activity_count ?? null) !== renderedActivityCount) {
            refresh().catch(() => {});
        }
    });

    document.getElementById('monthlySessionClose').addEventListener('click', () => dialog.close());
    document.getElementById('monthlySessionRefresh').addEventListener('click', () => refresh().catch(error => notify('Could not refresh session', error.message, 'danger')));
    successAcknowledge.addEventListener('click', async () => {
        setBlocked(false);
        try {
            await refresh();
        } catch (error) {
            await notify('Session finished, but the activity view could not refresh', error.message, 'danger');
        }
    });
    open.addEventListener('click', async () => {
        dialog.showModal();
        try { await refresh(); } catch (error) { await notify('Could not load session', error.message, 'danger'); }
    });
    finish.addEventListener('click', async () => {
        dialog.close();
        const warnings = [
            ['Before you finish', 'Strict rule: finish the monthly session only after you are completely done. Print R4-01 and the Resumen first. Do not proceed if any movement, pahabol, or late submission is still expected.'],
            ['Confirm reports and submissions', 'Confirm that R4-01 and the Resumen are printed, and there are no remaining or late submissions to encode?'],
            ['Final warning', 'Finishing permanently saves this session and clears monthly codes from every record. This cannot be undone. Continue?'],
        ];
        for (const [title, message] of warnings) {
            const confirmed = await ask({ title, message, confirmText: 'Continue', kind: 'danger' });
            if (!confirmed) return;
        }
        const confirmation = await ask({
            title: 'Confirm monthly session finish',
            message: 'To permanently finish this monthly session, type the exact phrase shown below.',
            confirmText: 'Finish session',
            requiredText: 'FINISH MONTHLY SESSION',
            kind: 'danger',
        });
        if (!confirmation) return;
        finish.disabled = true;
        showFinishing();
        let result;
        try {
            result = await send(finishUrl);
        } catch (error) {
            setBlocked(false);
            await notify('Could not finish monthly session', error.message, 'danger');
            finish.disabled = false;
            return;
        }
        showFinished(result.period_label || 'session');
    });
    start.addEventListener('click', async () => {
        const confirmed = await ask({
            title: 'Start monthly session?',
            message: 'Monthly Session is available right now! It is available only once per month and is generally done at the end of the month. Do not start until you are ready to record the monthly movements.',
            confirmText: 'Start session',
        });
        if (!confirmed) return;
        start.disabled = true;
        try {
            await send(startUrl);
            await refresh();
        } catch (error) {
            await notify('Could not start monthly session', error.message, 'danger');
            try { await refresh(); } catch (_) {}
        }
    });
    // Shortened (minimized) by default; expanding it is remembered on this device.
    const minimizedKey = 'monthlySessionWidgetMinimized';
    const setMinimized = minimized => {
        widget.dataset.minimized = String(minimized);
        minimize.textContent = minimized ? '+' : '−';
        minimize.setAttribute('aria-label', minimized ? 'Expand monthly session bubble' : 'Minimize monthly session bubble');
        minimize.title = minimized ? 'Expand' : 'Minimize';
    };
    minimize.addEventListener('click', () => {
        const minimized = widget.dataset.minimized !== 'true';
        setMinimized(minimized);
        try { window.localStorage.setItem(minimizedKey, String(minimized)); } catch (_) {}
    });
    let storedMinimized = null;
    try { storedMinimized = window.localStorage.getItem(minimizedKey); } catch (_) {}
    setMinimized(storedMinimized !== 'false');

    // ---------- Draggable widget (chat-head behavior) ----------
    const positionKey = 'monthlySessionWidgetPosition';
    const edge = 12;
    const dragThreshold = 5;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let position = { side: 'right', top: null };
    let drag = null;
    try {
        const saved = JSON.parse(window.localStorage.getItem(positionKey) || 'null');
        if (saved && (saved.side === 'left' || saved.side === 'right') && Number.isFinite(saved.top)) position = saved;
    } catch (_) {}
    const clampTop = top => Math.min(Math.max(edge, top), Math.max(edge, window.innerHeight - widget.offsetHeight - edge));
    const savePosition = () => {
        try { window.localStorage.setItem(positionKey, JSON.stringify(position)); } catch (_) {}
    };
    const applyPosition = () => {
        if (position.top === null) return;
        widget.style.transition = 'none';
        widget.style.top = `${clampTop(position.top)}px`;
        widget.style.bottom = 'auto';
        if (position.side === 'left') {
            widget.style.left = `${edge}px`;
            widget.style.right = 'auto';
        } else {
            widget.style.right = `${edge}px`;
            widget.style.left = 'auto';
        }
    };
    widget.addEventListener('pointerdown', event => {
        if (event.button !== 0 || event.target.closest('button')) return;
        const rect = widget.getBoundingClientRect();
        drag = { id: event.pointerId, startX: event.clientX, startY: event.clientY, left: rect.left, top: rect.top, moved: false };
        widget.setPointerCapture(event.pointerId);
    });
    widget.addEventListener('pointermove', event => {
        if (!drag || event.pointerId !== drag.id) return;
        const dx = event.clientX - drag.startX;
        const dy = event.clientY - drag.startY;
        if (!drag.moved && Math.hypot(dx, dy) < dragThreshold) return;
        if (!drag.moved) {
            drag.moved = true;
            widget.dataset.dragging = 'true';
            widget.style.transition = 'none';
            widget.style.right = 'auto';
            widget.style.bottom = 'auto';
        }
        const maxLeft = Math.max(edge, window.innerWidth - widget.offsetWidth - edge);
        widget.style.left = `${Math.min(Math.max(edge, drag.left + dx), maxLeft)}px`;
        widget.style.top = `${clampTop(drag.top + dy)}px`;
    });
    const endDrag = event => {
        if (!drag || event.pointerId !== drag.id) return;
        const moved = drag.moved;
        drag = null;
        if (widget.hasPointerCapture(event.pointerId)) widget.releasePointerCapture(event.pointerId);
        delete widget.dataset.dragging;
        if (!moved) return;
        const rect = widget.getBoundingClientRect();
        position = {
            side: rect.left + rect.width / 2 < window.innerWidth / 2 ? 'left' : 'right',
            top: clampTop(rect.top),
        };
        savePosition();
        if (reduceMotion.matches) {
            applyPosition();
            return;
        }
        widget.style.transition = 'left .18s ease';
        widget.style.left = `${position.side === 'left' ? edge : window.innerWidth - rect.width - edge}px`;
        window.setTimeout(applyPosition, 200);
    };
    widget.addEventListener('pointerup', endDrag);
    widget.addEventListener('pointercancel', endDrag);
    grip.addEventListener('keydown', event => {
        if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        event.preventDefault();
        const rect = widget.getBoundingClientRect();
        const currentSide = position.top === null
            ? (rect.left + rect.width / 2 < window.innerWidth / 2 ? 'left' : 'right')
            : position.side;
        position = {
            side: event.key === 'ArrowLeft' ? 'left' : event.key === 'ArrowRight' ? 'right' : currentSide,
            top: clampTop((position.top ?? rect.top) + (event.key === 'ArrowUp' ? -24 : event.key === 'ArrowDown' ? 24 : 0)),
        };
        applyPosition();
        savePosition();
    });
    window.addEventListener('resize', applyPosition);
    applyPosition();

    refresh().catch(error => { status.textContent = error.message; });
    if (@json($monthlySessionWidgetPolls ?? true)) {
        window.setInterval(() => {
            if (document.hidden) return;
            refresh().catch(() => {});
        }, 15000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refresh().catch(() => {});
        });
    }
})();
</script>