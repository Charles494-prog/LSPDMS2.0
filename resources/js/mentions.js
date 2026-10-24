/*
 * @mentions for the Tasks page: a people picker that opens when you type "@" in a task or comment box,
 * and rendering that turns "@KimberlyOstil" (or a full name typed out, "@Kimberly Ostil") into a
 * highlighted name. Handles come from the server (name without spaces; a second person with the same
 * name gets their id appended). The picker matches name words, the handle and the start of the gmail.
 * All text is written with textContent (never innerHTML).
 */
const AT = /(^|[^\p{L}\p{N}@])@/gu;
const WORD = /^[\p{L}\p{N}]+/u;
const escape = text => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

export function createMentions({ root, request, peopleUrl, el, avatar, me = null }) {
    let people = [];
    let byHandle = new Map();
    let byName = [];   // [{ person, pattern }] for "@First Last"
    const ready = request(peopleUrl)
        .then(list => {
            people = list;
            byHandle = new Map(list.map(person => [person.handle.toLowerCase(), person]));
            byName = list
                .map(person => ({ person, words: String(person.name ?? '').trim().split(/\s+/).filter(Boolean) }))
                .filter(entry => entry.words.length)
                .map(({ person, words }) => ({ person, pattern: new RegExp(`^${words.map(escape).join('\\s+')}(?![\\p{L}\\p{N}])`, 'iu') }));
            return list;
        })
        .catch(() => []);

    // Every known mention in a text: [{ start, end, person }]. At each "@" the longer of the handle
    // and a full-name match wins (same rule as the server, so what is highlighted is who is notified).
    function scan(text) {
        const value = String(text ?? '');
        const found = [];
        for (const match of value.matchAll(AT)) {
            const start = match.index + match[1].length;
            const rest = value.slice(start + 1, start + 301);
            let best = null;
            let length = 0;
            const handle = rest.match(WORD);
            if (handle && byHandle.has(handle[0].toLowerCase())) [best, length] = [byHandle.get(handle[0].toLowerCase()), handle[0].length];
            for (const { person, pattern } of byName) {
                const name = rest.match(pattern);
                if (name && name[0].length > length) [best, length] = [person, name[0].length];
            }
            if (best) found.push({ start, end: start + 1 + length, person: best });
        }
        return found;
    }

    /* ---------- Rendering ---------- */
    // Returns a fragment: plain text, with known mentions as highlighted names.
    function render(text) {
        const fragment = document.createDocumentFragment();
        const value = String(text ?? '');
        let last = 0;
        for (const { start, end, person } of scan(value)) {
            if (start > last) fragment.append(document.createTextNode(value.slice(last, start)));
            const tag = el('span', 'ws-mention', `@${person.name}`);
            tag.title = `@${person.handle}${person.role ? ` · ${person.role}` : ''}`;
            fragment.append(tag);
            last = end;
        }
        if (last < value.length) fragment.append(document.createTextNode(value.slice(last)));
        return fragment;
    }

    // The people mentioned in a text (known handles and full names only).
    function mentioned(text) {
        return [...new Map(scan(text).map(({ person }) => [person.id, person])).values()];
    }

    /* ---------- Picker ---------- */
    const menu = el('div', 'ws-mention-menu');
    menu.setAttribute('role', 'listbox');
    menu.hidden = true;
    root.append(menu);
    let active = null;   // { field, options, index, start, query, note }

    function close() {
        menu.hidden = true;
        active?.field.setAttribute('aria-expanded', 'false');
        active = null;
    }

    function position(field) {
        const box = field.getBoundingClientRect();
        const below = window.innerHeight - box.bottom > 240;
        menu.style.left = `${Math.max(8, Math.min(box.left, window.innerWidth - 288))}px`;
        menu.style.top = below ? `${box.bottom + 4}px` : '';
        menu.style.bottom = below ? '' : `${window.innerHeight - box.top + 4}px`;
    }

    // "ki" matches a name word, the handle or the gmail start; "charles andrei" matches the start of the name.
    function matches(person, query) {
        if (!query) return true;
        const name = String(person.name ?? '').toLowerCase().replace(/\s+/g, ' ');
        if (query.includes(' ')) return name.startsWith(query.replace(/\s+/g, ' '));
        return person.handle.toLowerCase().startsWith(query)
            || name.split(' ').some(part => part.startsWith(query))
            || Boolean(person.email_key?.startsWith(query));
    }

    function draw() {
        menu.replaceChildren();
        if (!active.options.length) {
            menu.append(el('div', 'ws-mention-empty', active.note || (active.query ? `No one called “${active.query}”` : 'No one to mention here')));
            return;
        }
        active.options.forEach((person, index) => {
            const option = el('button', `ws-mention-option ${index === active.index ? 'on' : ''}`.trim());
            option.type = 'button';
            option.tabIndex = -1;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', String(index === active.index));
            const text = el('span', 'ws-mention-text');
            text.append(el('strong', '', person.name), el('span', '', `@${person.handle}`));
            option.append(avatar(person, 'xs'), text);
            option.addEventListener('mousedown', event => { event.preventDefault(); choose(index); });
            option.addEventListener('mousemove', () => { if (active && active.index !== index) { active.index = index; draw(); } });
            menu.append(option);
        });
        menu.querySelector('.on')?.scrollIntoView({ block: 'nearest' });
    }

    function choose(index) {
        if (!active) return;
        const person = active.options[index];
        if (!person) return close();
        const { field, start } = active;
        const caret = field.selectionStart;
        const insert = `@${person.handle} `;
        field.value = field.value.slice(0, start) + insert + field.value.slice(caret);
        const at = start + insert.length;
        field.setSelectionRange(at, at);
        close();
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.focus();
    }

    // candidates(): who may be mentioned in this box (e.g. only people who can see the task).
    function attach(field, candidates) {
        field.setAttribute('aria-autocomplete', 'list');
        field.setAttribute('aria-expanded', 'false');
        // Keep the browser's own autofill / suggestion list from covering the picker.
        field.setAttribute('autocomplete', 'off');
        field.setAttribute('autocorrect', 'off');
        field.setAttribute('data-lpignore', 'true');
        field.setAttribute('data-1p-ignore', '');
        const update = () => {
            const caret = field.selectionStart ?? field.value.length;
            const before = field.value.slice(0, caret);
            // Up to three spaces after "@", so a full name ("@Charles Andrei") can be typed out.
            const match = before.match(/(^|[^\p{L}\p{N}@])@((?:[\p{L}\p{N}]+ ?){0,4})$/u);
            if (!match || match[2].length > 60) return close();
            const query = match[2].toLowerCase();
            const pool = (candidates?.() ?? people);
            const options = pool.filter(person => matches(person, query)).slice(0, 8);
            // Nobody to offer, but the name is someone left out on purpose: say why instead of "no one".
            let note = '';
            if (!options.length && query) {
                const left = people.filter(person => matches(person, query) && !pool.includes(person));
                if (left.length === 1 && left[0].id === me) note = 'That’s you. Mention someone else to notify them.';
                else if (left.length) note = `${left[0].name} can’t see this task, so they can’t be mentioned here.`;
            }
            // Once the text after "@" has a space and fits no one, it is just a sentence: stay out of the way.
            if (!options.length && !note && query.includes(' ')) return close();
            active = { field, options, index: 0, start: caret - match[2].length - 1, query: match[2], note };
            field.setAttribute('aria-expanded', 'true');
            position(field);
            menu.hidden = false;
            draw();
        };
        field.addEventListener('input', update);
        field.addEventListener('click', update);
        field.addEventListener('blur', () => setTimeout(() => { if (active?.field === field) close(); }, 120));
        // Capture phase: runs before the box's own Enter-to-send handler.
        field.addEventListener('keydown', event => {
            if (!active || active.field !== field || menu.hidden) return;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const step = event.key === 'ArrowDown' ? 1 : -1;
                active.index = (active.index + step + active.options.length) % Math.max(1, active.options.length);
                draw();
            } else if ((event.key === 'Enter' || event.key === 'Tab') && active.options.length) {
                event.preventDefault();
                event.stopImmediatePropagation();
                choose(active.index);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.stopImmediatePropagation();
                close();
            }
        }, true);
    }

    window.addEventListener('scroll', () => { if (active) position(active.field); }, { passive: true, capture: true });
    window.addEventListener('resize', () => { if (active) position(active.field); });

    return { ready, render, mentioned, attach, people: () => people };
}
