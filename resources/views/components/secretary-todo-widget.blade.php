@push('styles')
<style>
    .todo-launcher {
        position:fixed; right:24px; bottom:96px; z-index:70; display:flex; align-items:center; gap:.55rem;
        min-height:54px; padding:.45rem 1rem .45rem .55rem; border:0; border-radius:999px;
        background:#0a6bd6; color:#fff; font:inherit; font-weight:750;
        box-shadow:0 2px 8px rgba(10,107,214,.2); cursor:pointer;
    }
    .todo-launcher svg { width:40px; height:40px; padding:9px; border-radius:50%; background:rgba(255,255,255,.15); }
    .todo-launcher:hover { transform:translateY(-2px); }
    .todo-backdrop { position:fixed; inset:0; z-index:80; background:rgba(9,18,33,.42); backdrop-filter:blur(2px); }
    .todo-backdrop[hidden] { display:none; }
    .todo-panel {
        position:fixed; top:50%; left:50%; z-index:81; display:flex; width:min(660px,calc(100vw - 40px));
        height:min(760px,calc(100vh - 64px)); min-height:360px; flex-direction:column; overflow:hidden;
        transform:translate(-50%,-50%);
        border:1px solid var(--border); border-radius:18px; background:var(--surface); color:var(--text);
        box-shadow:0 20px 65px rgba(15,23,42,.24);
    }
    .todo-panel[hidden] { display:none; }
    .todo-panel.is-expanded { width:min(1120px,calc(100vw - 48px)); height:calc(100vh - 48px); }
    .todo-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 1.1rem; border-bottom:1px solid var(--border); }
    .todo-head h2 { margin:0; font-size:1.05rem; }
    .todo-head p { margin:.25rem 0 0; color:var(--text-muted); font-size:.78rem; }
    .todo-head-actions { display:flex; align-items:center; gap:.45rem; }
    .todo-expand,.todo-close { display:grid; width:36px; height:36px; place-items:center; border:1px solid var(--border); border-radius:9px; background:var(--bg); color:var(--text); font-size:1.05rem; cursor:pointer; }
    .todo-expand svg { width:17px; height:17px; }
    .todo-tabs { display:grid; grid-template-columns:1fr 1fr; gap:.35rem; padding:.65rem .8rem; border-bottom:1px solid var(--border); }
    .todo-tab { padding:.6rem; border:0; border-radius:8px; background:transparent; color:var(--text-muted); font:inherit; font-size:.83rem; font-weight:750; cursor:pointer; }
    .todo-tab[aria-selected="true"] { background:color-mix(in srgb,var(--accent) 12%,var(--surface)); color:var(--accent); }
    .todo-form { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.5rem; padding:.8rem; border-bottom:1px solid var(--border); }
    .todo-form textarea,.todo-form input { min-width:0; padding:.65rem .7rem; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text); font:inherit; font-size:.83rem; }
    .todo-form textarea { min-height:68px; max-height:180px; resize:vertical; line-height:1.45; }
    .todo-form input[type="date"] { grid-column:1; }
    .todo-add { grid-column:2; grid-row:1 / span 2; align-self:stretch; padding:.55rem .8rem; border:0; border-radius:8px; background:var(--accent); color:#fff; font:inherit; font-size:.8rem; font-weight:750; cursor:pointer; }
    .todo-add:disabled { opacity:.55; cursor:wait; }
    .todo-items { display:grid; align-content:start; gap:.6rem; flex:1; min-height:0; overflow-y:auto; padding:.8rem; }
    .todo-item { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.45rem .55rem; padding:.75rem; border:1px solid var(--border); border-radius:11px; background:var(--surface); }
    .todo-open { display:block; width:100%; min-width:0; padding:.2rem 0; border:0; background:transparent; color:var(--text); font:inherit; text-align:left; cursor:pointer; }
    .todo-open-title { display:-webkit-box; overflow:hidden; -webkit-box-orient:vertical; -webkit-line-clamp:3; white-space:pre-wrap; overflow-wrap:anywhere; font-size:.92rem; line-height:1.5; }
    .todo-open:hover .todo-open-title { color:var(--accent); }
    .todo-date-line { grid-column:1; display:flex; align-items:center; flex-wrap:wrap; gap:.4rem; color:var(--text-muted); font-size:.69rem; }
    .todo-deadline { max-width:145px; padding:.3rem .4rem; border:1px solid var(--border); border-radius:6px; background:var(--bg); color:var(--text-muted); font:inherit; font-size:.7rem; }
    .todo-complete { grid-column:2; grid-row:1 / span 2; align-self:center; padding:.45rem .65rem; border:1px solid color-mix(in srgb,#16a34a 35%,var(--border)); border-radius:7px; background:transparent; color:#15803d; font:inherit; font-size:.75rem; font-weight:750; cursor:pointer; }
    .todo-detail { display:flex; flex:1; min-height:0; flex-direction:column; padding:.85rem; }
    .todo-detail[hidden],.todo-list-view[hidden] { display:none; }
    .todo-detail-back { align-self:flex-start; margin-bottom:.75rem; padding:.45rem .65rem; border:1px solid var(--border); border-radius:7px; background:var(--bg); color:var(--text); font:inherit; font-size:.8rem; font-weight:700; cursor:pointer; }
    .todo-detail-heading { margin:0 0 .3rem; font-size:1rem; }
    .todo-detail-meta { margin:0 0 .75rem; color:var(--text-muted); font-size:.77rem; }
    .todo-detail-title { width:100%; min-height:180px; max-height:none; flex:1; resize:vertical; padding:.9rem; border:1px solid var(--border); border-radius:10px; outline:0; background:var(--bg); color:var(--text); font:inherit; font-size:.98rem; line-height:1.6; }
    .todo-detail-title:focus { border-color:var(--accent); box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 14%,transparent); }
    .todo-detail-footer { display:flex; align-items:center; justify-content:space-between; gap:.8rem; padding-top:.75rem; }
    .todo-detail-deadline { display:grid; gap:.25rem; color:var(--text-muted); font-size:.75rem; }
    .todo-detail-deadline input { padding:.5rem; border:1px solid var(--border); border-radius:7px; background:var(--bg); color:var(--text); font:inherit; }
    .todo-detail-done { padding:.6rem .8rem; border:1px solid color-mix(in srgb,#16a34a 35%,var(--border)); border-radius:8px; background:transparent; color:#15803d; font:inherit; font-weight:750; cursor:pointer; }
    .todo-status { min-height:1.15rem; padding:0 .85rem .5rem; color:var(--text-muted); font-size:.74rem; }
    .todo-status.error { color:var(--danger); }
    .todo-empty { margin:auto; max-width:250px; padding:1rem; color:var(--text-muted); font-size:.85rem; line-height:1.5; text-align:center; }
    @media(max-width:520px) {
        .todo-launcher { right:16px; bottom:80px; min-height:50px; }
        .todo-panel,.todo-panel.is-expanded { width:calc(100vw - 20px); height:calc(100vh - 32px); min-height:320px; }
        .todo-panel.is-expanded { width:calc(100vw - 12px); height:calc(100vh - 12px); }
        .todo-form { grid-template-columns:minmax(0,1fr) auto; }
    }
</style>
@endpush

<div id="secretaryTodoWidget"
    data-list-url="{{ route('secretary-todos.index') }}"
    data-create-url="{{ route('secretary-todos.store') }}"
    data-update-url="{{ route('secretary-todos.update', ['todo' => '__ID__']) }}"
    data-complete-url="{{ route('secretary-todos.complete', ['todo' => '__ID__']) }}"
    data-csrf="{{ csrf_token() }}">
    <button class="todo-launcher" type="button" id="todoLauncher" aria-expanded="false" aria-controls="todoPanel" aria-label="Open secretary to-do list">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M8 6h12M8 12h12M8 18h12"/><path d="m3 6 1 1 2-2m-3 7 1 1 2-2m-3 7 1 1 2-2"/>
        </svg>
        <span>To-do</span>
    </button>

    <div class="todo-backdrop" id="todoBackdrop" hidden></div>
    <section class="todo-panel" id="todoPanel" role="dialog" aria-modal="true" aria-labelledby="todoHeading" hidden>
        <header class="todo-head">
            <div><h2 id="todoHeading">Secretary To-Do</h2><p>Tasks and general notes for secretaries.</p></div>
            <div class="todo-head-actions">
                <button class="todo-expand" type="button" id="todoExpand" aria-label="Expand to-do list" aria-pressed="false" title="Expand for longer notes">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3m13-5h3a2 2 0 0 1 2 2v3M3 16v3a2 2 0 0 0 2 2h3m13-5v3a2 2 0 0 1-2 2h-3"/></svg>
                </button>
                <button class="todo-close" type="button" id="todoClose" aria-label="Close to-do list">&times;</button>
            </div>
        </header>
        <div id="todoListView" class="todo-list-view">
            <div class="todo-tabs" role="tablist" aria-label="To-do list type">
                <button class="todo-tab" type="button" role="tab" aria-selected="true" data-scope="private">Private</button>
                <button class="todo-tab" type="button" role="tab" aria-selected="false" data-scope="shared">Shared</button>
            </div>
            <form class="todo-form" id="todoForm">
                <textarea id="todoTitle" maxlength="10000" rows="2" placeholder="Write a task or note…" aria-label="Task or note" required></textarea>
                <input id="todoDeadline" type="date" aria-label="Deadline (optional)">
                <button class="todo-add" id="todoAdd" type="submit">Add task</button>
            </form>
            <div class="todo-status" id="todoStatus" role="status" aria-live="polite"></div>
            <div class="todo-items" id="todoItems" aria-live="polite"><p class="todo-empty">Open a list to view tasks.</p></div>
        </div>
        <section class="todo-detail" id="todoDetail" aria-label="Task details" hidden>
            <button class="todo-detail-back" type="button" id="todoDetailBack">&larr; Back to tasks</button>
            <h3 class="todo-detail-heading" id="todoDetailHeading">Edit task or note</h3>
            <p class="todo-detail-meta" id="todoDetailMeta"></p>
            <textarea class="todo-detail-title" id="todoDetailTitle" maxlength="10000" aria-label="Edit task or note content"></textarea>
            <div class="todo-detail-footer">
                <label class="todo-detail-deadline">Deadline
                    <input id="todoDetailDeadline" type="date" aria-label="Edit task deadline">
                </label>
                <button class="todo-detail-done" type="button" id="todoDetailDone">Mark done</button>
            </div>
        </section>
    </section>
</div>

@push('scripts')
<script>
(() => {
    const widget = document.getElementById('secretaryTodoWidget');
    if (!widget) return;

    const launcher = document.getElementById('todoLauncher');
    const panel = document.getElementById('todoPanel');
    const backdrop = document.getElementById('todoBackdrop');
    const close = document.getElementById('todoClose');
    const expand = document.getElementById('todoExpand');
    const listView = document.getElementById('todoListView');
    const detailView = document.getElementById('todoDetail');
    const detailBack = document.getElementById('todoDetailBack');
    const detailTitle = document.getElementById('todoDetailTitle');
    const detailDeadline = document.getElementById('todoDetailDeadline');
    const detailMeta = document.getElementById('todoDetailMeta');
    const detailDone = document.getElementById('todoDetailDone');
    const tabs = [...widget.querySelectorAll('.todo-tab')];
    const form = document.getElementById('todoForm');
    const titleInput = document.getElementById('todoTitle');
    const deadlineInput = document.getElementById('todoDeadline');
    const addButton = document.getElementById('todoAdd');
    const items = document.getElementById('todoItems');
    const status = document.getElementById('todoStatus');
    let scope = 'private';
    let tasks = [];
    let saveTimers = new Map();
    let selectedTaskId = null;
    let detailSaveInFlight = null;

    function showStatus(message, isError = false) {
        status.textContent = message;
        status.classList.toggle('error', isError);
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': widget.dataset.csrf,
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                ...options.headers,
            },
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validationError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
            throw new Error(validationError || payload.message || 'Could not save this task.');
        }
        return payload;
    }

    function formatWrittenAt(value) {
        if (!value) return 'Added just now';
        return `Added ${new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))}`;
    }

    function renderTasks() {
        items.replaceChildren();
        if (!tasks.length) {
            const empty = document.createElement('p');
            empty.className = 'todo-empty';
            empty.textContent = 'No active tasks in this list. Add a task or note above.';
            items.append(empty);
            return;
        }

        tasks.forEach(task => {
            const card = document.createElement('article');
            card.className = 'todo-item';
            const open = document.createElement('button');
            open.type = 'button';
            open.className = 'todo-open';
            open.setAttribute('aria-label', `Open task: ${task.title}`);
            const title = document.createElement('span');
            title.className = 'todo-open-title';
            title.textContent = task.title;
            open.append(title);
            const details = document.createElement('div');
            details.className = 'todo-date-line';
            const written = document.createElement('span');
            written.textContent = formatWrittenAt(task.created_at);
            const deadline = document.createElement('input');
            deadline.className = 'todo-deadline';
            deadline.type = 'date';
            deadline.value = task.deadline || '';
            deadline.setAttribute('aria-label', 'Edit deadline');
            deadline.title = 'Change deadline';
            const done = document.createElement('button');
            done.type = 'button';
            done.className = 'todo-complete';
            done.textContent = 'Done';
            details.append(written, deadline);
            card.append(open, done, details);
            items.append(card);

            open.addEventListener('click', () => openTask(task.id));
            deadline.addEventListener('change', () => saveTask(task, { deadline: deadline.value || null }, deadline));
            done.addEventListener('click', async () => {
                done.disabled = true;
                try {
                    await request(widget.dataset.completeUrl.replace('__ID__', task.id), { method: 'POST' });
                    tasks = tasks.filter(item => item.id !== task.id);
                    renderTasks();
                    if (selectedTaskId === task.id) showListView();
                    showStatus('Task marked done.');
                } catch (error) {
                    done.disabled = false;
                    showStatus(error.message, true);
                }
            });
        });
    }

    async function saveTask(task, changes, field) {
        const currentTask = tasks.find(item => item.id === task.id) || task;
        const value = changes.title ?? '';
        if ('title' in changes && !value.trim()) {
            field.value = currentTask.title;
            showStatus('A task cannot be blank.', true);
            return false;
        }
        try {
            const updated = await request(widget.dataset.updateUrl.replace('__ID__', task.id), {
                method: 'PATCH',
                body: JSON.stringify(changes),
            });
            tasks = tasks.map(item => item.id === updated.id ? updated : item);
            if ('title' in changes && field === document.activeElement) field.value = updated.title;
            if ('deadline' in changes && field === document.activeElement) field.value = updated.deadline || '';
            showStatus('Changes saved.');
            return true;
        } catch (error) {
            if ('title' in changes) field.value = currentTask.title;
            showStatus(error.message, true);
            return false;
        }
    }

    async function loadTasks() {
        showStatus('Loading tasks…');
        items.replaceChildren();
        try {
            tasks = await request(`${widget.dataset.listUrl}?scope=${encodeURIComponent(scope)}`);
            renderTasks();
            if (selectedTaskId && !tasks.some(task => task.id === selectedTaskId)) showListView();
            showStatus('');
        } catch (error) {
            showStatus(error.message, true);
            items.replaceChildren();
        }
    }

    function setScope(nextScope) {
        scope = nextScope;
        tabs.forEach(tab => tab.setAttribute('aria-selected', String(tab.dataset.scope === scope)));
        loadTasks();
    }

    function resizeTextArea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = `${Math.min(textarea.scrollHeight, 220)}px`;
    }

    function openTask(taskId) {
        const task = tasks.find(item => item.id === taskId);
        if (!task) return;
        selectedTaskId = task.id;
        detailTitle.value = task.title;
        detailDeadline.value = task.deadline || '';
        detailMeta.textContent = `${scope === 'shared' ? 'Shared note' : 'Private note'} · ${formatWrittenAt(task.created_at)}`;
        resizeTextArea(detailTitle);
        listView.hidden = true;
        detailView.hidden = false;
        detailTitle.focus();
    }

    function showListView() {
        selectedTaskId = null;
        listView.hidden = false;
        detailView.hidden = true;
        renderTasks();
    }

    async function saveSelectedTitle() {
        clearTimeout(saveTimers.get(selectedTaskId));
        if (detailSaveInFlight) await detailSaveInFlight;
        const task = tasks.find(item => item.id === selectedTaskId);
        if (!task) return true;
        if (!detailTitle.value.trim()) {
            detailTitle.value = task.title;
            showStatus('A task cannot be blank.', true);
            return false;
        }
        if (detailTitle.value === task.title) return true;
        detailSaveInFlight = saveTask(task, { title: detailTitle.value }, detailTitle);
        const saved = await detailSaveInFlight;
        detailSaveInFlight = null;

        return saved;
    }

    function setOpen(open) {
        panel.hidden = !open;
        backdrop.hidden = !open;
        launcher.setAttribute('aria-expanded', String(open));
        if (open) {
            loadTasks();
            titleInput.focus();
        } else {
            panel.classList.remove('is-expanded');
            expand.setAttribute('aria-pressed', 'false');
            expand.setAttribute('aria-label', 'Expand to-do list');
            launcher.focus();
        }
    }

    launcher.addEventListener('click', () => {
        setOpen(panel.hidden);
    });
    close.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));
    detailBack.addEventListener('click', async () => {
        await saveSelectedTitle();
        showListView();
    });
    detailTitle.addEventListener('input', () => {
        resizeTextArea(detailTitle);
        const task = tasks.find(item => item.id === selectedTaskId);
        if (!task) return;
        clearTimeout(saveTimers.get(task.id));
        showStatus('Saving…');
        saveTimers.set(task.id, window.setTimeout(saveSelectedTitle, 500));
    });
    detailTitle.addEventListener('blur', () => { saveSelectedTitle(); });
    detailDeadline.addEventListener('change', () => {
        const task = tasks.find(item => item.id === selectedTaskId);
        if (task) saveTask(task, { deadline: detailDeadline.value || null }, detailDeadline);
    });
    detailDone.addEventListener('click', async () => {
        const task = tasks.find(item => item.id === selectedTaskId);
        if (!task) return;
        const saved = await saveSelectedTitle();
        if (!saved) return;
        detailDone.disabled = true;
        try {
            await request(widget.dataset.completeUrl.replace('__ID__', task.id), { method: 'POST' });
            tasks = tasks.filter(item => item.id !== task.id);
            showListView();
            showStatus('Task marked done.');
        } catch (error) {
            showStatus(error.message, true);
        } finally {
            detailDone.disabled = false;
        }
    });
    expand.addEventListener('click', () => {
        const isExpanded = panel.classList.toggle('is-expanded');
        expand.setAttribute('aria-pressed', String(isExpanded));
        expand.setAttribute('aria-label', isExpanded ? 'Restore to-do list size' : 'Expand to-do list');
        expand.title = isExpanded ? 'Restore window size' : 'Expand for longer notes';
    });
    tabs.forEach(tab => tab.addEventListener('click', () => setScope(tab.dataset.scope)));
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!titleInput.value.trim()) return;
        addButton.disabled = true;
        resizeTextArea(titleInput);
        showStatus('Adding task…');
        try {
            const created = await request(widget.dataset.createUrl, {
                method: 'POST',
                body: JSON.stringify({ scope, title: titleInput.value, deadline: deadlineInput.value || null }),
            });
            tasks.unshift(created);
            titleInput.value = '';
            resizeTextArea(titleInput);
            deadlineInput.value = '';
            renderTasks();
            showStatus('Task added.');
            titleInput.focus();
        } catch (error) {
            showStatus(error.message, true);
        } finally {
            addButton.disabled = false;
        }
    });
    document.addEventListener('keydown', async event => {
        if (event.key === 'Escape' && !panel.hidden) {
            if (!detailView.hidden) {
                await saveSelectedTitle();
                showListView();
            } else {
                setOpen(false);
            }
        }
    });
})();
</script>
@endpush
