/*
 * Tasks page: tasks the Head Secretary assigns to secretaries, shown as a Facebook-style feed with comments.
 * The server decides every permission; this file only shows what the server allows.
 * Opening this page marks the visible tasks as seen, which stops the "new tasks" pop-up.
 */
import { createHelpers } from './workspace-helpers';
import { createMentions } from './mentions';

const root = document.getElementById('tasksApp');

if (root) {
    const data = root.dataset;
    const { el, icon, request, notify, todayIso, dayDiff, dueLabel, timeAgo, avatar, setCount, autoGrow } = createHelpers(root);
    const REACTIONS = [
        { type: 'like', emoji: '👍', label: 'Like' },
        { type: 'love', emoji: '❤️', label: 'Love' },
        { type: 'haha', emoji: '😆', label: 'Haha' },
        { type: 'wow', emoji: '😮', label: 'Wow' },
        { type: 'sad', emoji: '😢', label: 'Sad' },
        { type: 'angry', emoji: '😡', label: 'Angry' },
    ];
    const REACTION = Object.fromEntries(REACTIONS.map(reaction => [reaction.type, reaction]));
    // @mentions: the picker offers only people who can see the task; the author is never offered.
    const myId = Number(data.userId);
    const mentions = createMentions({ root, request, peopleUrl: data.taskPeopleUrl, el, avatar, me: myId });
    const canSeeTask = (task, person) => person.is_head || task.visibility === 'everyone' || task.members.some(member => member.id === person.id);
    const mentionableFor = task => () => mentions.people().filter(person => person.id !== myId && canSeeTask(task, person));
    const panel = root;
    const feed = panel.querySelector('#taskFeed');
    const composer = panel.querySelector('#taskComposer');
    const statusLine = panel.querySelector('#taskStatusLine');
    const critical = panel.querySelector('#taskCritical');
    const calendar = panel.querySelector('#taskCalendar');
    let state = { is_head: data.isHead === 'true', attention: 0, tasks: [] };
    let secretaries = null;
    let editingId = null;
    let loading = false;
    const openComments = new Set();
    const drafts = new Map();
    const replyingTo = new Map();   // task id -> top-level comment id with an open reply box

    const say = (message, isError = false) => {
        statusLine.textContent = message;
        statusLine.classList.toggle('error', isError);
    };

    /* Head Secretary composer: title, details, due date, who can see / comment / mark done */
    function buildComposer() {
        if (!composer) return;
        const toggle = composer.querySelector('#taskComposeToggle');
        const form = composer.querySelector('#taskComposeForm');
        const title = form.querySelector('#taskTitle');
        const body = form.querySelector('#taskBody');
        const due = form.querySelector('#taskDue');
        const people = form.querySelector('#taskPeople');
        const allToggle = form.querySelector('#taskSelectAll');
        const visibilityInputs = [...form.querySelectorAll('input[name="taskVisibility"]')];
        const othersComment = form.querySelector('#taskOthersComment');
        const othersRow = form.querySelector('#taskOthersRow');
        const syncVisibility = () => {
            const everyone = visibilityInputs.find(input => input.checked)?.value === 'everyone';
            othersRow.hidden = !everyone;
        };
        visibilityInputs.forEach(input => input.addEventListener('change', syncVisibility));
        const submit = form.querySelector('#taskSubmit');
        const cancel = form.querySelector('#taskCancel');
        form.querySelectorAll('[data-due-offset]').forEach(button => button.addEventListener('click', () => {
            if (button.dataset.dueOffset === '') { due.value = ''; return; }
            const d = new Date(`${todayIso()}T00:00:00`);
            d.setDate(d.getDate() + Number(button.dataset.dueOffset));
            due.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        }));
        const heading = form.querySelector('#taskComposeHeading');

        const open = (task = null) => {
            editingId = task?.id ?? null;
            composer.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            form.hidden = false;
            heading.textContent = task ? 'Edit task' : 'Assign a task';
            submit.textContent = task ? 'Save changes' : 'Post task';
            title.value = task?.title || '';
            body.value = task?.body || '';
            due.value = task?.due_date || '';
            // No due dates in the past; an existing overdue date may stay as it is.
            const today = todayIso();
            due.min = task?.due_date && task.due_date < today ? task.due_date : today;
            visibilityInputs.forEach(input => { input.checked = input.value === (task?.visibility || 'assigned'); });
            othersComment.checked = Boolean(task?.others_can_comment);
            syncVisibility();
            autoGrow(body);
            renderPeople(task);
            title.focus();
            composer.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        };
        const close = () => {
            editingId = null;
            composer.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            form.hidden = true;
            form.reset();
        };

        async function renderPeople(task) {
            if (!secretaries) {
                people.replaceChildren(el('p', 'ws-loading', 'Loading secretaries…'));
                try { secretaries = await request(data.taskSecretariesUrl); } catch (error) { people.replaceChildren(el('p', 'ws-error', error.message)); return; }
            }
            people.replaceChildren();
            if (!secretaries.length) {
                people.append(el('p', 'ws-empty-inline', 'There are no verified secretaries yet.'));
                return;
            }
            const current = new Map((task?.members || []).map(member => [member.id, member]));
            secretaries.forEach(person => {
                const member = current.get(person.id);
                const rowNode = el('div', 'ws-person');
                rowNode.dataset.id = person.id;
                const pick = el('label', 'ws-person-pick');
                const box = document.createElement('input');
                box.type = 'checkbox';
                box.className = 'ws-person-see';
                box.checked = Boolean(member) || !task;
                pick.append(box, avatar(person, 'sm'), el('span', 'ws-person-name', person.name));
                const perms = el('div', 'ws-person-perms');
                const permToggle = (cls, label, on) => {
                    const wrap = el('label', `ws-perm ${cls}`);
                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.checked = on;
                    wrap.append(input, el('span', '', label));
                    return wrap;
                };
                perms.append(
                    permToggle('comment', 'Can comment', member ? member.can_comment : true),
                    permToggle('complete', 'Can mark done', member ? member.can_complete : true),
                );
                const sync = () => {
                    rowNode.classList.toggle('is-off', !box.checked);
                    perms.querySelectorAll('input').forEach(input => { input.disabled = !box.checked; });
                };
                box.addEventListener('change', () => { sync(); syncAll(); });
                sync();
                rowNode.append(pick, perms);
                people.append(rowNode);
            });
            syncAll();
        }
        const syncAll = () => {
            const boxes = [...people.querySelectorAll('.ws-person-see')];
            allToggle.checked = boxes.length > 0 && boxes.every(box => box.checked);
            allToggle.indeterminate = boxes.some(box => box.checked) && !allToggle.checked;
        };
        allToggle.addEventListener('change', () => {
            people.querySelectorAll('.ws-person-see').forEach(box => {
                box.checked = allToggle.checked;
                box.dispatchEvent(new Event('change'));
            });
        });

        toggle.addEventListener('click', () => (composer.classList.contains('is-open') ? close() : open()));
        cancel.addEventListener('click', close);
        body.addEventListener('input', () => autoGrow(body));
        // Typing @Name in the title or details also ticks that secretary below, so they can see the task.
        const everyoneButMe = () => mentions.people().filter(person => person.id !== myId);
        mentions.attach(title, everyoneButMe);
        mentions.attach(body, everyoneButMe);
        const selectMentioned = () => {
            const ids = mentions.mentioned(`${title.value}\n${body.value}`).map(person => person.id);
            people.querySelectorAll('.ws-person').forEach(row => {
                const box = row.querySelector('.ws-person-see');
                if (!ids.includes(Number(row.dataset.id)) || box.checked) return;
                box.checked = true;
                box.dispatchEvent(new Event('change'));
                row.classList.add('is-mentioned');
                setTimeout(() => row.classList.remove('is-mentioned'), 1600);
            });
        };
        title.addEventListener('input', selectMentioned);
        body.addEventListener('input', selectMentioned);
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const members = [...people.querySelectorAll('.ws-person')]
                .filter(rowNode => rowNode.querySelector('.ws-person-see').checked)
                .map(rowNode => ({
                    id: Number(rowNode.dataset.id),
                    can_comment: rowNode.querySelector('.ws-perm.comment input').checked,
                    can_complete: rowNode.querySelector('.ws-perm.complete input').checked,
                }));
            if (!title.value.trim()) { title.focus(); say('Give the task a title.', true); return; }
            if (!members.length) { say('Choose at least one secretary who can see this task.', true); return; }
            submit.disabled = true;
            const wasEditing = editingId;
            try {
                const visibility = visibilityInputs.find(input => input.checked)?.value || 'assigned';
                const payload = JSON.stringify({
                    title: title.value.trim(), body: body.value.trim() || null, due_date: due.value || null,
                    visibility, others_can_comment: visibility === 'everyone' && othersComment.checked, members,
                });
                const saved = wasEditing
                    ? await request(data.taskUpdateUrl.replace('__ID__', wasEditing), { method: 'PUT', body: payload })
                    : await request(data.taskCreateUrl, { method: 'POST', body: payload });
                upsert(saved);
                close();
                notify({
                    title: wasEditing ? 'Task updated' : 'Task assigned',
                    message: saved.title,
                    changes: [{ label: 'Who sees it', to: saved.visibility === 'everyone'
                        ? `Everyone${saved.others_can_comment ? ' (all can comment)' : ' (others view only)'}`
                        : 'Assigned secretaries only' }].concat(saved.members.map(member => ({
                        label: member.name,
                        to: [member.can_comment ? 'can comment' : 'view only', member.can_complete ? 'can mark done' : null].filter(Boolean).join(', '),
                    }))),
                });
            } catch (error) {
                say(error.message, true);
            } finally {
                submit.disabled = false;
            }
        });
        return { open };
    }
    const composerApi = state.is_head ? buildComposer() : null;

    function upsert(task) {
        const index = state.tasks.findIndex(item => item.id === task.id);
        if (index === -1) state.tasks.unshift(task);
        else state.tasks[index] = task;
        render();
    }
    function remove(id) {
        state.tasks = state.tasks.filter(task => task.id !== id);
        render();
    }

    function statusPill(task) {
        if (task.status === 'awaiting_confirmation') {
            const pill = el('span', 'ws-pill wait');
            // "All" only when every member really marked it (tasks closed before per-member marks have just one).
            const allMarked = (task.progress?.total || 0) > 1 && task.progress.done === task.progress.total;
            const who = allMarked ? `Done by all ${task.progress.total}` : `Done by ${task.done_by?.name || 'a secretary'}`;
            pill.append(icon(['M12 7v5l3 2', 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z']), document.createTextNode(`${who} · waiting for Head Secretary`));
            return pill;
        }
        const pill = el('span', 'ws-pill open');
        pill.append(el('span', 'ws-dot'), document.createTextNode('Open'));
        // With several people finishing it, show how far along they are.
        if ((task.progress?.total || 0) > 1) {
            pill.append(el('span', 'ws-pill-progress', ` · ${task.progress.done} of ${task.progress.total} done`));
            pill.title = task.members.filter(member => member.can_complete)
                .map(member => `${member.name}: ${member.done_at ? 'done' : 'not yet'}`).join('\n');
        }
        return pill;
    }

    function card(task) {
        const article = el('article', ['ws-task', task.status === 'awaiting_confirmation' ? 'is-waiting' : '', task.is_new ? 'is-new' : ''].filter(Boolean).join(' '));
        article.dataset.id = task.id;

        // Header: author, time, menu
        const head = el('header', 'ws-task-head');
        head.append(avatar(task.author));
        const who = el('div', 'ws-task-who');
        who.append(el('strong', '', task.author?.name || 'Head Secretary'));
        const when = el('span', 'ws-task-when');
        when.append(document.createTextNode(`${timeAgo(task.created_at)} · `));
        const audience = el('span', 'ws-audience');
        audience.append(task.visibility === 'everyone'
            ? icon(['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M3 12h18M12 3c2.8 3 2.8 15 0 18M12 3c-2.8 3-2.8 15 0 18'])
            : icon(['M16 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1', 'M9 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z', 'M22 19v-1a4 4 0 0 0-3-3.9', 'M16 3.1a3.5 3.5 0 0 1 0 6.8']));
        audience.append(document.createTextNode(task.visibility === 'everyone' ? 'Everyone' : 'Assigned only'));
        audience.title = task.visibility === 'everyone'
            ? `Every secretary can see this${task.others_can_comment ? ' and comment' : ''}`
            : 'Only the assigned secretaries can see this';
        when.append(audience);
        when.title = task.created_at ? new Date(task.created_at).toLocaleString() : '';
        who.append(when);
        head.append(who);
        if (task.mentions_me) head.append(el('span', 'ws-new', 'Mentioned you'));
        else if (task.is_new) head.append(el('span', 'ws-new', 'New'));
        if (task.viewer.can_manage) {
            const tools = el('div', 'ws-task-tools');
            const edit = el('button', 'ws-icon-btn');
            edit.type = 'button';
            edit.setAttribute('aria-label', 'Edit task');
            edit.title = 'Edit task, who can see it, and what they can do';
            edit.append(icon(['M4 20h4l10.5-10.5a2.8 2.8 0 0 0-4-4L4 16v4Z', 'm13.5 7.5 4 4']));
            edit.addEventListener('click', () => composerApi?.open(task));
            const del = el('button', 'ws-icon-btn danger');
            del.type = 'button';
            del.setAttribute('aria-label', 'Delete task');
            del.title = 'Delete task';
            del.append(icon(['M4 7h16', 'M10 11v6M14 11v6', 'M6 7l1 13h10l1-13', 'M9 7V4h6v3']));
            del.addEventListener('click', async () => {
                if (!window.confirm(`Delete the task "${task.title}"? Its comments are deleted too.`)) return;
                try {
                    await request(data.taskDeleteUrl.replace('__ID__', task.id), { method: 'DELETE' });
                    remove(task.id);
                    notify({ title: 'Task deleted', message: task.title });
                } catch (error) { say(error.message, true); }
            });
            tools.append(edit, del);
            head.append(tools);
        }
        article.append(head);

        // Body
        const main = el('div', 'ws-task-main');
        const titleNode = el('h3', 'ws-task-title');
        titleNode.append(mentions.render(task.title));
        main.append(titleNode);
        if (task.body) {
            const bodyNode = el('p', 'ws-task-body');
            bodyNode.append(mentions.render(task.body));
            main.append(bodyNode);
        }
        const tags = el('div', 'ws-task-tags');
        tags.append(statusPill(task));
        const chip = dueLabel(task.due_date);
        if (chip) {
            const dueChip = el('span', `ws-chip ${chip.tone}`.trim());
            dueChip.append(icon(['M8 3v3M16 3v3M4 9h16', 'M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1Z']), document.createTextNode(chip.text));
            tags.append(dueChip);
        }
        main.append(tags);

        // Who can see it (with their permissions for the Head)
        const seen = el('div', 'ws-task-members');
        const stack = el('div', 'ws-avatar-stack');
        task.members.slice(0, 6).forEach(member => {
            const node = avatar(member, 'xs');
            node.title = `${member.name}${task.viewer.can_manage ? ` · ${member.can_comment ? 'can comment' : 'view only'}${member.can_complete ? ', can mark done' : ''}` : ''}${member.done_at ? ' · done' : ''}`;
            if (member.done_at) node.classList.add('is-done');
            stack.append(node);
        });
        if (task.members.length > 6) stack.append(el('span', 'ws-avatar xs more', `+${task.members.length - 6}`));
        const assignedText = task.members.length === 1 ? `Assigned to ${task.members[0].name}` : `Assigned to ${task.members.length} secretaries`;
        seen.append(stack, el('span', '', task.viewer.is_assigned ? `${assignedText} · including you` : assignedText));
        main.append(seen);
        article.append(main);

        // Reactions + comment count, like the line above Facebook's Like / Comment buttons
        const comments = task.comments.filter(item => item.kind === 'comment');
        const stats = el('div', 'ws-task-stats');
        stats.append(reactionSummary(task.reactions));
        if (comments.length) {
            const count = el('button', 'ws-link', `${comments.length} comment${comments.length === 1 ? '' : 's'}`);
            count.type = 'button';
            count.addEventListener('click', () => toggleComments(task.id));
            stats.append(count);
        }
        if (task.reactions.total || comments.length) article.append(stats);

        // Action bar
        const bar = el('div', 'ws-task-actions');
        bar.append(reactButton(task, null, task.reactions, false));
        const commentToggle = el('button', 'ws-action');
        commentToggle.type = 'button';
        commentToggle.append(icon(['M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l1.8-3.2A7.5 7.5 0 1 1 20 11.5Z']), document.createTextNode('Comment'));
        commentToggle.setAttribute('aria-expanded', String(openComments.has(task.id)));
        commentToggle.addEventListener('click', () => toggleComments(task.id, true));
        bar.append(commentToggle);

        const act = (label, cls, url, done, paths) => {
            const button = el('button', `ws-action ${cls}`);
            button.type = 'button';
            button.append(icon(paths), document.createTextNode(label));
            button.addEventListener('click', async () => {
                button.disabled = true;
                try {
                    const result = await request(url.replace('__ID__', task.id), { method: 'POST' });
                    done(result);
                } catch (error) {
                    say(error.message, true);
                    button.disabled = false;
                }
            });
            return button;
        };
        if (task.viewer.can_complete) {
            bar.append(act('Mark as done', 'primary', data.taskCompleteUrl, updated => {
                upsert(updated);
                if (updated.status === 'awaiting_confirmation') {
                    notify({ title: 'Marked as done', message: 'The Head Secretary will confirm it.', changes: [{ label: task.title, from: 'Open', to: 'Waiting for confirmation' }] });
                } else {
                    const left = updated.progress.total - updated.progress.done;
                    notify({ title: 'Your part is done', message: `Waiting for ${left} more ${left === 1 ? 'secretary' : 'secretaries'} before it goes to the Head Secretary.` });
                }
            }, ['M5 12.5l4.5 4.5L19 7.5']));
        }
        if (task.viewer.can_undo) {
            bar.append(act('Undo my done', '', data.taskUndoUrl, updated => {
                upsert(updated);
                notify({ title: 'Marked as not done', message: task.title });
            }, ['M4 4v6h6', 'M20 12a8 8 0 0 0-14.9-4L4 10']));
        }
        if (task.viewer.can_confirm) {
            // Still open = only some secretaries finished; the Head can close it early.
            const early = task.status === 'open';
            const button = act('Confirm done', 'primary', data.taskConfirmUrl, () => {
                completed = null;
                completedCount += 1;
                remove(task.id);
                notify({ title: 'Task closed', message: 'It has been removed from the feed.', changes: [{ label: task.title, from: early ? 'Open' : 'Waiting for confirmation', to: 'Done' }] });
            }, ['M5 12.5l4.5 4.5L19 7.5']);
            if (early) button.title = `${task.progress.done} of ${task.progress.total} marked it done. Confirming closes it for everyone.`;
            bar.append(button);
        }
        if (task.viewer.can_reopen) {
            bar.append(act('Reopen', '', data.taskReopenUrl, updated => {
                upsert(updated);
                notify({ title: 'Task reopened', changes: [{ label: task.title, from: 'Waiting for confirmation', to: 'Open' }] });
            }, ['M4 4v6h6', 'M20 12a8 8 0 0 0-14.9-4L4 10']));
        }
        article.append(bar);

        // Comment thread: top-level comments with their replies indented under them, and
        // events like "marked as done" as small system lines, all in time order.
        if (openComments.has(task.id)) {
            const thread = el('div', 'ws-thread');
            const words = { done: 'marked this as done', reopened: 'reopened this task', confirmed: 'confirmed it as done' };
            task.comments.filter(item => !item.parent_id).forEach(item => {
                if (item.kind !== 'comment') {
                    thread.append(el('p', 'ws-event', `${item.author?.name || 'Someone'} ${words[item.kind] || item.kind} · ${timeAgo(item.created_at)}`));
                    return;
                }
                const block = el('div', 'ws-comment-block');
                block.append(commentRow(task, item, false));
                const replies = task.comments.filter(reply => reply.parent_id === item.id);
                if (replies.length || replyingTo.get(task.id) === item.id) {
                    const list = el('div', 'ws-replies');
                    replies.forEach(reply => list.append(commentRow(task, reply, true)));
                    if (replyingTo.get(task.id) === item.id && task.viewer.can_comment) {
                        list.append(commentForm(task, item));
                    }
                    block.append(list);
                }
                thread.append(block);
            });
            if (task.viewer.can_comment) thread.append(commentForm(task, null));
            else thread.append(el('p', 'ws-event', 'You can view this task and react, but not comment on it.'));
            article.append(thread);
        }
        return article;
    }

    function toggleComments(taskId, focus = false) {
        if (openComments.has(taskId) && !focus) openComments.delete(taskId);
        else openComments.add(taskId);
        render();
        if (focus) feed.querySelector(`.ws-task[data-id="${taskId}"] .ws-comment-form:last-child .ws-comment-input`)?.focus();
    }

    /* One comment or reply: bubble, reaction pill, then "Like · Reply · 3m" */
    function commentRow(task, item, isReply) {
        const rowNode = el('div', `ws-comment ${item.mine ? 'mine' : ''} ${isReply ? 'reply' : ''}`.trim());
        rowNode.dataset.commentId = item.id;
        rowNode.append(avatar(item.author, isReply ? 'xs' : 'sm'));
        const wrap = el('div', 'ws-comment-wrap');
        const bubble = el('div', 'ws-bubble');
        const text = el('p');
        text.append(mentions.render(item.body));
        bubble.append(el('strong', '', item.author?.name || 'Someone'), text);
        if (item.reactions.total) {
            const pill = reactionSummary(item.reactions);
            pill.classList.add('on-bubble');
            bubble.append(pill);
        }
        const meta = el('div', 'ws-comment-meta');
        meta.append(reactButton(task, item.id, item.reactions, true));
        if (task.viewer.can_comment) {
            const reply = el('button', 'ws-meta-btn', 'Reply');
            reply.type = 'button';
            reply.addEventListener('click', () => {
                // Replies attach to the top-level comment, like Facebook.
                replyingTo.set(task.id, item.parent_id || item.id);
                render();
                const key = `${task.id}:r${item.parent_id || item.id}`;
                const input = feed.querySelector(`.ws-comment-input[data-draft-key="${key}"]`);
                if (input) {
                    const handle = mentions.people().find(person => person.id === item.author?.id)?.handle;
                    if (isReply && !input.value && handle && item.author?.id !== myId) input.value = `@${handle} `;
                    input.focus();
                    input.setSelectionRange(input.value.length, input.value.length);
                }
            });
            meta.append(reply);
        }
        const time = el('span', 'ws-comment-time', timeAgo(item.created_at));
        time.title = item.created_at ? new Date(item.created_at).toLocaleString() : '';
        meta.append(time);
        wrap.append(bubble, meta);
        rowNode.append(wrap);
        return rowNode;
    }

    /* Comment box (parent = null) or reply box under a comment */
    function commentForm(task, parent) {
        const key = parent ? `${task.id}:r${parent.id}` : `${task.id}`;
        const form = el('form', `ws-comment-form ${parent ? 'reply' : ''}`.trim());
        const input = el('textarea', 'ws-comment-input');
        input.rows = 1;
        input.maxLength = 2000;
        input.dataset.draftKey = key;
        input.placeholder = parent ? `Reply to ${parent.author?.name || 'this comment'}…` : 'Write a comment… (type @ to mention someone)';
        input.setAttribute('aria-label', parent ? `Reply to ${parent.author?.name || 'comment'}` : `Comment on ${task.title}`);
        input.value = drafts.get(key) || '';
        mentions.attach(input, mentionableFor(task));
        const send = el('button', 'ws-send');
        send.type = 'submit';
        send.setAttribute('aria-label', parent ? 'Send reply' : 'Send comment');
        send.append(icon(['M4 12l16-8-6 16-2-6-8-2Z']));
        input.addEventListener('input', () => { drafts.set(key, input.value); autoGrow(input); });
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); }
            if (event.key === 'Escape' && parent) { event.preventDefault(); replyingTo.delete(task.id); drafts.delete(key); render(); }
        });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const value = input.value.trim();
            if (!value) return;
            send.disabled = true;
            try {
                const updated = await request(data.taskCommentUrl.replace('__ID__', task.id), {
                    method: 'POST',
                    body: JSON.stringify({ body: value, parent_id: parent ? parent.id : null }),
                });
                drafts.delete(key);
                if (parent) replyingTo.delete(task.id);
                upsert(updated);
                feed.querySelector(`.ws-comment-input[data-draft-key="${task.id}"]`)?.focus();
            } catch (error) {
                say(error.message, true);
                send.disabled = false;
            }
        });
        form.append(avatar({ name: data.userName, avatar_url: data.userAvatar }, parent ? 'xs' : 'sm'), input, send);
        return form;
    }

    /* ---------- Reactions (Facebook's six) ---------- */
    function reactionSummary(summary) {
        const node = el('span', 'ws-react-summary');
        if (!summary?.total) return node;
        const icons = el('span', 'ws-react-icons');
        summary.types.slice(0, 3).forEach(type => icons.append(el('span', `ws-react-emoji ${type}`, REACTION[type]?.emoji || '👍')));
        node.append(icons, el('span', 'ws-react-count', String(summary.total)));
        node.title = `${summary.types.map(type => `${REACTION[type]?.emoji} ${summary.counts[type]}`).join('  ')}\n${summary.names.join(', ')}${summary.total > summary.names.length ? ' and others' : ''}`;
        return node;
    }

    function reactButton(task, commentId, summary, compact) {
        const mine = summary?.mine || null;
        const wrap = el('span', `ws-react-wrap ${compact ? 'compact' : ''}`.trim());
        const button = el('button', compact ? `ws-meta-btn ${mine ? `reacted ${mine}` : ''}` : `ws-action ${mine ? `reacted ${mine}` : ''}`);
        button.type = 'button';
        button.setAttribute('aria-haspopup', 'true');
        if (compact) button.textContent = mine ? REACTION[mine].label : 'Like';
        else if (mine) button.append(el('span', 'ws-react-emoji big', REACTION[mine].emoji), document.createTextNode(REACTION[mine].label));
        else button.append(icon(['M7 10v11H4V10h3Z', 'M7 10l4-7c1.5 0 2.5 1 2.2 2.6L12.8 9H19a2 2 0 0 1 2 2.3l-1.2 7.2A2 2 0 0 1 17.8 20H7']), document.createTextNode('Like'));
        button.title = 'Click to like · hover or long-press for more reactions';
        button.setAttribute('aria-label', mine ? `You reacted ${REACTION[mine].label}. Click to remove.` : 'Like. Hover, long-press or press Arrow Up for more reactions.');

        const picker = el('span', 'ws-react-picker');
        picker.setAttribute('role', 'menu');
        REACTIONS.forEach(reaction => {
            const option = el('button', `ws-react-option ${reaction.type === mine ? 'on' : ''}`, reaction.emoji);
            option.type = 'button';
            option.setAttribute('role', 'menuitem');
            option.setAttribute('aria-label', reaction.label);
            option.title = reaction.label;
            option.addEventListener('click', event => { event.stopPropagation(); closePicker(); react(task, commentId, reaction.type); });
            picker.append(option);
        });

        let openTimer = 0, closeTimer = 0, longPressed = false;
        const openPicker = () => { clearTimeout(closeTimer); wrap.classList.add('picking'); };
        const closePicker = () => { clearTimeout(openTimer); wrap.classList.remove('picking'); };
        wrap.addEventListener('pointerenter', event => {
            if (event.pointerType !== 'mouse') return;
            clearTimeout(closeTimer);
            openTimer = setTimeout(openPicker, 450);
        });
        wrap.addEventListener('pointerleave', () => { clearTimeout(openTimer); closeTimer = setTimeout(closePicker, 300); });
        button.addEventListener('pointerdown', event => {
            if (event.pointerType === 'mouse') return;
            longPressed = false;
            openTimer = setTimeout(() => { longPressed = true; openPicker(); }, 450);
        });
        button.addEventListener('pointerup', () => clearTimeout(openTimer));
        button.addEventListener('contextmenu', event => { if (wrap.classList.contains('picking')) event.preventDefault(); });
        button.addEventListener('keydown', event => {
            if (event.key === 'ArrowUp') { event.preventDefault(); openPicker(); picker.querySelector('button')?.focus(); }
        });
        picker.addEventListener('keydown', event => {
            const options = [...picker.querySelectorAll('button')];
            const index = options.indexOf(document.activeElement);
            if (event.key === 'ArrowRight') { event.preventDefault(); options[(index + 1) % options.length].focus(); }
            if (event.key === 'ArrowLeft') { event.preventDefault(); options[(index - 1 + options.length) % options.length].focus(); }
            if (event.key === 'Escape') { event.preventDefault(); closePicker(); button.focus(); }
        });
        button.addEventListener('click', () => {
            if (longPressed) { longPressed = false; return; }
            closePicker();
            // A plain click likes, or removes your current reaction, like Facebook.
            react(task, commentId, mine || 'like');
        });
        wrap.append(button, picker);
        return wrap;
    }

    async function react(task, commentId, type) {
        try {
            const updated = await request(data.taskReactUrl.replace('__ID__', task.id), {
                method: 'POST',
                body: JSON.stringify({ type, comment_id: commentId }),
            });
            upsert(updated);
        } catch (error) {
            say(error.message, true);
        }
    }

    /* ---------- Critical block + calendar (side column) ---------- */
    const URGENCY = task => {
        if (task.status !== 'open' || !task.due_date) return task.status !== 'open' ? 'waiting' : 'none';
        const days = dayDiff(task.due_date);
        if (days < 0) return 'overdue';
        if (days === 0) return 'today';
        if (days <= 2) return 'soon';
        return 'later';
    };

    function focusTask(id) {
        const target = feed.querySelector(`.ws-task[data-id="${id}"]`);
        if (!target) return;
        target.scrollIntoView({ block: 'center', behavior: 'smooth' });
        target.classList.add('is-flash');
        setTimeout(() => target.classList.remove('is-flash'), 1600);
    }

    function renderCritical() {
        if (!critical) return;
        const urgent = state.tasks
            .filter(task => ['overdue', 'today', 'soon'].includes(URGENCY(task)))
            .sort((a, b) => a.due_date.localeCompare(b.due_date));
        critical.replaceChildren();
        critical.classList.toggle('is-critical', urgent.some(task => ['overdue', 'today'].includes(URGENCY(task))));
        const head = el('div', 'ws-side-head');
        head.append(icon(['M12 9v4M12 17h.01', 'M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z']), el('h2', '', 'Due soon'));
        critical.append(head);
        if (!urgent.length) {
            critical.append(el('p', 'ws-side-empty', 'Nothing is due in the next two days.'));
            return;
        }
        const list = el('ul', 'ws-due-list');
        urgent.forEach(task => {
            const level = URGENCY(task);
            const days = dayDiff(task.due_date);
            const item = el('li');
            const button = el('button', `ws-due-item ${level}`);
            button.type = 'button';
            const when = level === 'overdue' ? `Overdue by ${-days} day${days === -1 ? '' : 's'}` : level === 'today' ? 'Due today' : days === 1 ? 'Due tomorrow' : `Due in ${days} days`;
            button.append(el('span', 'ws-due-when', when), el('span', 'ws-due-title', task.title));
            button.addEventListener('click', () => focusTask(task.id));
            item.append(button);
            list.append(item);
        });
        critical.append(list);
    }

    let calendarMonth = (() => { const now = new Date(); return new Date(now.getFullYear(), now.getMonth(), 1); })();
    let calendarPick = null;
    function renderCalendar() {
        if (!calendar) return;
        const year = calendarMonth.getFullYear();
        const month = calendarMonth.getMonth();
        const iso = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        const byDay = new Map();
        state.tasks.filter(task => task.due_date).forEach(task => {
            if (!byDay.has(task.due_date)) byDay.set(task.due_date, []);
            byDay.get(task.due_date).push(task);
        });
        calendar.replaceChildren();
        const head = el('div', 'ws-cal-head');
        const prev = el('button', 'ws-icon-btn');
        prev.type = 'button';
        prev.setAttribute('aria-label', 'Previous month');
        prev.append(icon(['M15 6l-6 6 6 6']));
        prev.addEventListener('click', () => { calendarMonth = new Date(year, month - 1, 1); calendarPick = null; renderCalendar(); });
        const next = el('button', 'ws-icon-btn');
        next.type = 'button';
        next.setAttribute('aria-label', 'Next month');
        next.append(icon(['M9 6l6 6-6 6']));
        next.addEventListener('click', () => { calendarMonth = new Date(year, month + 1, 1); calendarPick = null; renderCalendar(); });
        head.append(prev, el('h2', '', calendarMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })), next);
        calendar.append(head);

        const grid = el('div', 'ws-cal-grid');
        grid.setAttribute('role', 'grid');
        ['S', 'M', 'T', 'W', 'T', 'F', 'S'].forEach(day => grid.append(el('span', 'ws-cal-dow', day)));
        for (let i = 0; i < calendarMonth.getDay(); i++) grid.append(el('span', 'ws-cal-blank'));
        const days = new Date(year, month + 1, 0).getDate();
        const today = todayIso();
        for (let day = 1; day <= days; day++) {
            const key = iso(new Date(year, month, day));
            const tasksThatDay = byDay.get(key) || [];
            const cell = el('button', 'ws-cal-day');
            cell.type = 'button';
            if (key === today) cell.classList.add('today');
            if (key === calendarPick) cell.classList.add('picked');
            cell.append(el('span', 'ws-cal-num', String(day)));
            if (tasksThatDay.length) {
                cell.classList.add('has-tasks');
                const dots = el('span', 'ws-cal-dots');
                tasksThatDay.slice(0, 3).forEach(task => dots.append(el('span', `ws-cal-dot ${URGENCY(task)}`)));
                cell.append(dots);
                cell.setAttribute('aria-label', `${key}: ${tasksThatDay.length} task${tasksThatDay.length === 1 ? '' : 's'} due`);
                cell.title = tasksThatDay.map(task => task.title).join('\n');
            } else {
                cell.setAttribute('aria-label', key);
            }
            cell.addEventListener('click', () => { calendarPick = calendarPick === key ? null : key; renderCalendar(); });
            grid.append(cell);
        }
        calendar.append(grid);

        const legend = el('div', 'ws-cal-legend');
        [['overdue', 'Overdue / today'], ['soon', 'Within 2 days'], ['later', 'Later'], ['waiting', 'Waiting for confirmation']]
            .forEach(([cls, label]) => { const item = el('span'); item.append(el('span', `ws-cal-dot ${cls}`), document.createTextNode(label)); legend.append(item); });
        calendar.append(legend);

        if (calendarPick) {
            const picked = byDay.get(calendarPick) || [];
            const list = el('div', 'ws-cal-picked');
            list.append(el('p', 'ws-cal-picked-date', new Date(`${calendarPick}T00:00:00`).toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })));
            if (!picked.length) list.append(el('p', 'ws-side-empty', 'No tasks due this day.'));
            picked.forEach(task => {
                const button = el('button', `ws-due-item ${URGENCY(task)}`);
                button.type = 'button';
                button.append(el('span', 'ws-due-when', task.status === 'open' ? 'Open' : 'Waiting for confirmation'), el('span', 'ws-due-title', task.title));
                button.addEventListener('click', () => focusTask(task.id));
                list.append(button);
            });
            calendar.append(list);
        }
    }

    /* ---------- What is shown: Open/Completed, filter, search, sort ---------- */
    const view = { name: 'open', filter: 'all', query: '', sort: 'due' };
    let completed = null;          // loaded on first visit of the Completed tab
    let completedCount = 0;
    const statsEl = panel.querySelector('#taskStats');
    const activityEl = panel.querySelector('#taskActivity');
    const searchInput = panel.querySelector('#taskSearch');
    const sortSelect = panel.querySelector('#taskSort');
    const filterBar = panel.querySelector('#taskFilters');

    const needsMe = task => state.is_head ? task.status === 'awaiting_confirmation' : task.status === 'open' && task.viewer.can_complete;
    const lastActivity = task => task.comments.length ? task.comments[task.comments.length - 1].created_at : task.created_at;
    const matchesQuery = task => {
        if (!view.query) return true;
        const haystack = [task.title, task.body, task.author?.name, ...task.members.map(member => member.name), ...(task.comments || []).map(item => item.body)]
            .filter(Boolean).join(' ').toLowerCase();
        return view.query.split(/\s+/).every(word => haystack.includes(word));
    };
    const FILTERS = {
        all: () => true,
        mine: needsMe,
        overdue: task => URGENCY(task) === 'overdue',
        soon: task => ['today', 'soon'].includes(URGENCY(task)),
        waiting: task => task.status === 'awaiting_confirmation',
    };
    function visibleTasks() {
        const list = state.tasks.filter(task => FILTERS[view.filter](task) && matchesQuery(task));
        const byDue = (a, b) => (a.due_date || '9999').localeCompare(b.due_date || '9999') || b.created_at.localeCompare(a.created_at);
        if (view.sort === 'new') return list.sort((a, b) => b.created_at.localeCompare(a.created_at));
        if (view.sort === 'active') return list.sort((a, b) => lastActivity(b).localeCompare(lastActivity(a)));
        // Due date: waiting-for-confirmation first for the Head (it needs them), then by date.
        return list.sort((a, b) => (state.is_head ? Number(b.status !== 'open') - Number(a.status !== 'open') : 0) || byDue(a, b));
    }

    function renderStats() {
        if (!statsEl) return;
        const today = todayIso();
        const weekEnd = (() => { const d = new Date(`${today}T00:00:00`); d.setDate(d.getDate() + 7); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; })();
        const items = [
            { key: 'all', label: 'Open', value: state.tasks.length },
            { key: 'mine', label: state.is_head ? 'Needs confirmation' : 'Assigned to me', value: state.tasks.filter(needsMe).length, tone: 'ink' },
            { key: 'overdue', label: 'Overdue', value: state.tasks.filter(task => URGENCY(task) === 'overdue').length, tone: 'danger' },
            { key: 'week', label: 'Due this week', value: state.tasks.filter(task => task.status === 'open' && task.due_date && task.due_date >= today && task.due_date <= weekEnd).length },
            { key: 'completed', label: 'Completed · 30 days', value: completedCount },
        ];
        statsEl.replaceChildren(...items.map(item => {
            const button = el('button', `ws-stat ${item.tone && item.value ? item.tone : ''}`.trim());
            button.type = 'button';
            const active = item.key === 'completed' ? view.name === 'completed' : view.name === 'open' && view.filter === item.key;
            button.setAttribute('aria-pressed', String(active));
            button.append(el('span', 'ws-stat-value', String(item.value)), el('span', 'ws-stat-label', item.label));
            button.addEventListener('click', () => {
                if (item.key === 'completed') return setView('completed');
                if (item.key === 'week') { view.sort = 'due'; sortSelect.value = 'due'; return setView('open', 'all'); }
                setView('open', item.key);
            });
            return button;
        }));
        panel.querySelector('[data-view-count="open"]').textContent = String(state.tasks.length);
        panel.querySelector('[data-view-count="completed"]').textContent = completedCount ? String(completedCount) : '';
    }

    function setView(name, filter = view.filter) {
        view.name = name;
        view.filter = filter;
        panel.querySelectorAll('[data-view]').forEach(tab => tab.setAttribute('aria-selected', String(tab.dataset.view === name)));
        filterBar.hidden = name !== 'open';
        filterBar.querySelectorAll('[data-filter]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.filter === view.filter)));
        if (name === 'completed' && completed === null) loadCompleted();
        render();
    }

    function render() {
        // Keep focus/typing in a comment or reply box across re-renders.
        const focusedKey = document.activeElement?.classList?.contains('ws-comment-input') ? document.activeElement.dataset.draftKey : null;
        feed.replaceChildren();
        renderStats();
        renderCritical();
        renderCalendar();
        if (view.name === 'completed') {
            renderCompleted();
        } else {
            const list = visibleTasks();
            if (!list.length) {
                const empty = el('div', 'ws-empty');
                empty.append(icon(['M9 11l3 3L22 4', 'M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'], 'ws-empty-icon'));
                if (state.tasks.length) {
                    empty.append(el('strong', '', 'No tasks match'), el('span', '', view.query ? `Nothing matches “${view.query}”.` : 'Try another filter.'));
                    const reset = el('button', 'ws-btn', 'Show all open tasks');
                    reset.type = 'button';
                    reset.addEventListener('click', () => { view.query = ''; searchInput.value = ''; setView('open', 'all'); });
                    empty.append(reset);
                } else {
                    empty.append(el('strong', '', 'No open tasks'), el('span', '', state.is_head
                        ? 'Assign a task above. Secretaries see only the tasks you share with them.'
                        : 'When the Head Secretary assigns you a task, it shows up here.'));
                }
                feed.append(empty);
            }
            list.forEach(task => feed.append(card(task)));
        }
        state.attention = state.tasks.filter(needsMe).length;
        setCount('tasks', state.attention);
        if (focusedKey) {
            const input = feed.querySelector(`.ws-comment-input[data-draft-key="${focusedKey}"]`);
            if (input) { input.focus(); input.setSelectionRange(input.value.length, input.value.length); }
        }
    }

    /* ---------- Completed (read-only history of the last 60 days) ---------- */
    async function loadCompleted() {
        feed.replaceChildren(el('div', 'ws-loading', 'Loading completed tasks…'));
        try {
            completed = await request(data.taskCompletedUrl);
        } catch (error) {
            completed = null;
            say(error.message, true);
        }
        if (view.name === 'completed') render();
    }

    function renderCompleted() {
        if (completed === null) { feed.append(el('div', 'ws-loading', 'Loading completed tasks…')); return; }
        // Everyone who marked their part done gets the credit (older tasks only have the one done_by).
        const finishers = task => (task.finished_by?.length ? task.finished_by : [task.done_by]).filter(Boolean);
        const names = people => people.length <= 2 ? people.map(p => p.name).join(' and ')
            : `${people.slice(0, -1).map(p => p.name).join(', ')} and ${people[people.length - 1].name}`;
        const list = completed.filter(task => !view.query || [task.title, task.body, ...finishers(task).map(p => p.name)].filter(Boolean).join(' ').toLowerCase().includes(view.query));
        if (!list.length) {
            const empty = el('div', 'ws-empty');
            empty.append(icon(['M9 11l3 3L22 4'], 'ws-empty-icon'), el('strong', '', 'Nothing completed yet'), el('span', '', 'Tasks the Head Secretary confirms in the last 60 days are listed here.'));
            feed.append(empty);
            return;
        }
        const table = el('div', 'ws-card ws-done-list');
        list.forEach(task => {
            const row = el('article', 'ws-done-row');
            const check = el('span', 'ws-done-check');
            check.append(icon(['M5 12.5l4.5 4.5L19 7.5']));
            const body = el('div', 'ws-done-body');
            body.append(el('strong', '', task.title));
            const meta = el('span', 'ws-done-meta');
            const parts = [
                finishers(task).length ? `Done by ${names(finishers(task))}` : null,
                task.confirmed_by ? `confirmed by ${task.confirmed_by.name}` : null,
                task.confirmed_at ? timeAgo(task.confirmed_at) : null,
            ].filter(Boolean);
            meta.textContent = parts.join(' · ');
            body.append(meta);
            row.append(check, body);
            if (task.due_date) {
                const tag = el('span', `ws-chip ${task.on_time ? '' : 'late'}`.trim(), task.on_time ? 'On time' : 'Late');
                tag.title = `Due ${task.due_date}`;
                row.append(tag);
            }
            table.append(row);
        });
        feed.append(table);
    }

    /* ---------- Recent activity (side column) ---------- */
    async function loadActivity() {
        if (!activityEl) return;
        let items = [];
        try { items = await request(data.taskActivityUrl); } catch (error) { return; }
        activityEl.replaceChildren();
        const head = el('div', 'ws-side-head plain');
        head.append(icon(['M3 12h4l3-8 4 16 3-8h4']), el('h2', '', 'Recent activity'));
        activityEl.append(head);
        if (!items.length) { activityEl.append(el('p', 'ws-side-empty', 'No activity yet.')); return; }
        const list = el('ul', 'ws-activity');
        const words = { comment: 'commented on', done: 'marked done', reopened: 'reopened', confirmed: 'confirmed' };
        items.slice(0, 8).forEach(item => {
            const li = el('li');
            const button = el('button', 'ws-activity-item');
            button.type = 'button';
            const line = el('span', 'ws-activity-line');
            line.append(el('strong', '', item.author?.name || 'Someone'), document.createTextNode(` ${item.is_reply ? 'replied on' : (words[item.kind] || item.kind)} `), el('em', '', item.task_title || 'a task'));
            button.append(avatar(item.author, 'xs'), line);
            if (item.body) button.append(el('span', 'ws-activity-quote', item.body));
            button.append(el('span', 'ws-activity-time', timeAgo(item.created_at)));
            button.addEventListener('click', () => {
                if (view.name !== 'open' || !state.tasks.some(task => task.id === item.task_id && FILTERS[view.filter](task))) setView('open', 'all');
                openComments.add(item.task_id);
                render();
                focusTask(item.task_id);
            });
            li.append(button);
            list.append(li);
        });
        activityEl.append(list);
    }

    /* ---------- Toolbar wiring + keyboard shortcuts ---------- */
    panel.querySelectorAll('[data-view]').forEach(tab => tab.addEventListener('click', () => setView(tab.dataset.view)));
    filterBar.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => setView('open', button.dataset.filter)));
    let searchTimer = 0;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { view.query = searchInput.value.trim().toLowerCase(); render(); }, 120);
    });
    searchInput.addEventListener('keydown', event => { if (event.key === 'Escape') { searchInput.value = ''; view.query = ''; render(); searchInput.blur(); } });
    sortSelect.addEventListener('change', () => { view.sort = sortSelect.value; render(); });
    document.addEventListener('keydown', event => {
        const active = document.activeElement;
        const typing = active && (active.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName));
        if (typing || event.ctrlKey || event.metaKey || event.altKey) return;
        if (event.key === '/') { event.preventDefault(); searchInput.focus(); }
        if ((event.key === 'n' || event.key === 'N') && composerApi) { event.preventDefault(); composerApi.open(); }
    });

    async function load(quiet = false) {
        if (loading) return;
        loading = true;
        if (!quiet && !state.tasks.length) feed.replaceChildren(el('div', 'ws-loading', 'Loading tasks…'));
        try {
            const result = await request(data.taskListUrl);
            // Don't redraw under someone typing a comment.
            const typing = document.activeElement?.classList?.contains('ws-comment-input') && document.activeElement.value.trim();
            // "New" marks stay for this visit; the server has now recorded them as seen.
            const seenNow = new Set(state.tasks.filter(task => task.is_new).map(task => task.id));
            result.tasks.forEach(task => { if (seenNow.has(task.id)) task.is_new = true; });
            state = result;
            completedCount = result.completed_count || 0;
            if (!typing) render();
            else setCount('tasks', result.attention);
            loadActivity();
            say('');
            // Everything this page just showed counts as seen (POST: CSRF-protected).
            if (!document.hidden && result.tasks.length) {
                request(data.taskSeenUrl, { method: 'POST', body: JSON.stringify({ ids: result.tasks.map(task => task.id), as_of: result.as_of }) })
                    .then(() => window.dispatchEvent(new CustomEvent('lspdms:tasks-seen')))
                    .catch(() => { /* the reminder simply stays until the next load */ });
            }
        } catch (error) {
            if (!quiet) say(error.message, true);
        } finally {
            loading = false;
        }
    }


    load();
    mentions.ready.then(() => { if (state.tasks.length) render(); });
    // Live: another person posted, commented, reacted or finished something (pushed over Reverb).
    let liveTimer = 0;
    window.addEventListener('lspdms:task-updated', () => {
        clearTimeout(liveTimer);
        liveTimer = setTimeout(() => load(true), 250);
    });
    // Keep the feed fresh while the page is visible (also covers when live updates are off).
    window.setInterval(() => { if (!document.hidden) load(true); }, 20000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) load(true); });
}
