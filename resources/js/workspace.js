/*
 * Chat window workspace: the Chats and To-Do tabs.
 *
 * - To-Do: each person's own private list (only they can see it).
 * - Tasks assigned by the Head Secretary live on their own page (tasks.js).
 *
 * All user text is written with textContent (never innerHTML).
 */
import { createHelpers } from './workspace-helpers';
import { BACKGROUND_HEADERS } from './background-request';
import './idle-session';

const root = document.getElementById('workspace');

if (root) {
    const data = root.dataset;
    const tabs = [...root.querySelectorAll('[data-workspace-tab]')];
    const panels = [...root.querySelectorAll('[data-workspace-panel]')];
    const TAB_KEY = 'workspaceTab';
    const { el, icon, request, notify, todayIso, dayDiff, dueLabel, setCount, autoGrow } = createHelpers(root);

    /* ---------- Tabs ---------- */
    let activeTab = 'chats';
    function selectTab(name, focus = false) {
        if (!panels.some(panel => panel.dataset.workspacePanel === name)) name = 'chats';
        activeTab = name;
        tabs.forEach(tab => {
            const on = tab.dataset.workspaceTab === name;
            tab.setAttribute('aria-selected', String(on));
            tab.tabIndex = on ? 0 : -1;
            if (on && focus) tab.focus();
        });
        panels.forEach(panel => { panel.hidden = panel.dataset.workspacePanel !== name; });
        try { localStorage.setItem(TAB_KEY, name); } catch (error) { /* storage blocked */ }
        if (name === 'todo') todo.load();
    }
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => selectTab(tab.dataset.workspaceTab));
        tab.addEventListener('keydown', event => {
            const keys = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1 };
            if (!(event.key in keys)) return;
            event.preventDefault();
            selectTab(tabs[(keys[event.key] + tabs.length) % tabs.length].dataset.workspaceTab, true);
        });
    });

    /* ---------- To-Do ---------- */
    const todo = (() => {
        const panel = root.querySelector('[data-workspace-panel="todo"]');
        const list = panel.querySelector('#todoList');
        const form = panel.querySelector('#todoAddForm');
        const input = panel.querySelector('#todoAddInput');
        const due = panel.querySelector('#todoAddDue');
        const statusLine = panel.querySelector('#todoStatusLine');
        const timeline = panel.querySelector('#todoTimeline');
        // Only the private list lives in the chat window; team-wide work is on the Tasks page.
        const scope = 'private';
        let items = [];
        let loading = false;

        const say = (message, isError = false) => {
            statusLine.textContent = message;
            statusLine.classList.toggle('error', isError);
        };

        function render() {
            list.replaceChildren();
            if (!items.length) {
                const empty = el('div', 'ws-empty');
                empty.append(icon(['M9 11l3 3L22 4', 'M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'], 'ws-empty-icon'));
                empty.append(el('strong', '', 'Your list is clear'));
                empty.append(el('span', '', 'Add what you need to do. Only you can see this list.'));
                list.append(empty);
            }
            items.forEach(item => list.append(row(item)));
            renderTimeline();
            setCount('todo', items.length);
        }

        // A line from the earliest to the latest due date, with today marked and each to-do as a dot.
        function renderTimeline() {
            const dated = items.filter(item => item.deadline).sort((a, b) => a.deadline.localeCompare(b.deadline));
            timeline.replaceChildren();
            timeline.hidden = dated.length === 0;
            if (!dated.length) return;
            const today = todayIso();
            const first = [dated[0].deadline, today].sort()[0];
            const last = [dated[dated.length - 1].deadline, today].sort()[1];
            const span = Math.max(1, dayDiff(last) - dayDiff(first));
            const at = iso => ((dayDiff(iso) - dayDiff(first)) / span) * 100;
            const track = el('div', 'ws-tl-track');
            const todayMark = el('span', 'ws-tl-today');
            todayMark.style.left = `${at(today)}%`;
            todayMark.title = 'Today';
            track.append(todayMark);
            dated.forEach(item => {
                const diff = dayDiff(item.deadline);
                const dot = el('button', `ws-tl-dot ${diff < 0 ? 'late' : diff <= 1 ? 'soon' : ''}`.trim());
                dot.type = 'button';
                dot.style.left = `${at(item.deadline)}%`;
                dot.title = `${dueLabel(item.deadline).text}: ${item.title.slice(0, 80)}`;
                dot.setAttribute('aria-label', dot.title);
                dot.addEventListener('click', () => {
                    const target = list.querySelector(`.ws-todo[data-id="${item.id}"]`);
                    target?.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    target?.classList.add('is-flash');
                    setTimeout(() => target?.classList.remove('is-flash'), 1200);
                });
                track.append(dot);
            });
            const fmt = iso => new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
            const ends = el('div', 'ws-tl-ends');
            ends.append(el('span', '', fmt(first)), el('span', '', fmt(last)));
            timeline.append(el('p', 'ws-tl-label', `${dated.length} dated to-do${dated.length === 1 ? '' : 's'}`), track, ends);
        }

        function row(item) {
            const li = el('li', 'ws-todo');
            li.dataset.id = item.id;
            const check = el('button', 'ws-check');
            check.type = 'button';
            check.setAttribute('aria-label', `Mark "${item.title.slice(0, 60)}" as done`);
            check.append(icon(['M5 12.5l4.5 4.5L19 7.5']));
            const body = el('div', 'ws-todo-body');
            const text = el('p', 'ws-todo-text', item.title);
            text.tabIndex = 0;
            text.title = 'Click to edit';
            const meta = el('div', 'ws-todo-meta');
            const chip = dueLabel(item.deadline);
            const dueButton = el('label', `ws-chip ${chip?.tone || ''}`.trim());
            dueButton.append(icon(['M8 3v3M16 3v3M4 9h16', 'M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1Z']), document.createTextNode(chip ? chip.text : 'Add date'));
            const dateInput = document.createElement('input');
            dateInput.type = 'date';
            dateInput.className = 'ws-chip-date';
            dateInput.value = item.deadline || '';
            dateInput.setAttribute('aria-label', 'Due date');
            dueButton.append(dateInput);
            meta.append(dueButton);
            body.append(text, meta);
            li.append(check, body);

            check.addEventListener('click', async () => {
                check.disabled = true;
                li.classList.add('is-done');
                try {
                    await request(data.todoCompleteUrl.replace('__ID__', item.id), { method: 'POST' });
                    setTimeout(() => {
                        items = items.filter(other => other.id !== item.id);
                        render();
                    }, 380);
                    say('Done. Nice work!');
                } catch (error) {
                    li.classList.remove('is-done');
                    check.disabled = false;
                    say(error.message, true);
                }
            });
            dateInput.addEventListener('change', () => save(item, { deadline: dateInput.value || null }));
            const startEdit = () => {
                if (li.classList.contains('is-editing')) return;
                li.classList.add('is-editing');
                const editor = el('textarea', 'ws-todo-editor');
                editor.value = item.title;
                editor.maxLength = 10000;
                editor.setAttribute('aria-label', 'Edit to-do');
                text.replaceWith(editor);
                autoGrow(editor);
                editor.focus();
                editor.setSelectionRange(editor.value.length, editor.value.length);
                let finished = false;
                const finish = async keep => {
                    if (finished) return;
                    finished = true;
                    const value = editor.value.trim();
                    if (keep && value && value !== item.title) await save(item, { title: value });
                    else render();
                };
                editor.addEventListener('input', () => autoGrow(editor));
                editor.addEventListener('keydown', event => {
                    if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); finish(true); }
                    if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); finish(false); }
                });
                editor.addEventListener('blur', () => finish(true));
            };
            text.addEventListener('click', startEdit);
            text.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); startEdit(); } });
            return li;
        }

        async function save(item, changes) {
            try {
                const updated = await request(data.todoUpdateUrl.replace('__ID__', item.id), { method: 'PATCH', body: JSON.stringify(changes) });
                items = items.map(other => other.id === updated.id ? updated : other);
                say('Saved.');
            } catch (error) {
                say(error.message, true);
            }
            render();
        }

        async function load() {
            if (loading) return;
            loading = true;
            if (!items.length) list.replaceChildren(el('li', 'ws-loading', 'Loading…'));
            try {
                items = await request(`${data.todoListUrl}?scope=${scope}`);
                render();
                say('');
            } catch (error) {
                say(error.message, true);
            } finally {
                loading = false;
            }
        }

        input.addEventListener('input', () => autoGrow(input));
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); }
        });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const title = input.value.trim();
            if (!title) return;
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            try {
                const created = await request(data.todoCreateUrl, { method: 'POST', body: JSON.stringify({ scope, title, deadline: due.value || null }) });
                items.unshift(created);
                input.value = '';
                due.value = '';
                autoGrow(input);
                render();
                say('Added.');
                input.focus();
            } catch (error) {
                say(error.message, true);
            } finally {
                button.disabled = false;
            }
        });

        // Count for the tab badge without opening the tab.
        async function refreshCount() {
            if (activeTab === 'todo') return;
            try { setCount('todo', (await request(`${data.todoListUrl}?scope=${scope}`, { headers: BACKGROUND_HEADERS })).length); } catch (error) { /* badge only */ }
        }

        return { load, refreshCount };
    })();

    /* ---------- Start ---------- */
    root.querySelectorAll('.ws-avatar img').forEach(img => {
        if (img.complete && img.naturalWidth === 0) img.remove();
        else img.addEventListener('error', () => img.remove(), { once: true });
    });
    let initial = 'chats';
    try { initial = new URLSearchParams(window.location.search).get('tab') || localStorage.getItem(TAB_KEY) || 'chats'; } catch (error) { /* storage blocked */ }
    selectTab(initial);
    if (activeTab !== 'todo') todo.refreshCount();
    window.setInterval(() => { if (!document.hidden && activeTab !== 'todo') todo.refreshCount(); }, 30000);
}
