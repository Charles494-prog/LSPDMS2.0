/*
 * Small DOM / fetch / date helpers shared by the chat window (workspace.js) and the Tasks page (tasks.js).
 * All user text is written with textContent (never innerHTML).
 */
export function createHelpers(root) {
    const data = root.dataset;
    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    };
    const icon = (paths, className = 'ws-icon') => {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('class', className);
        paths.forEach(d => {
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', d);
            svg.append(path);
        });
        return svg;
    };
    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            ...options,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': data.csrf,
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                ...options.headers,
            },
        });
        const payload = await response.json().catch(() => ({}));
        if (response.status === 429) {
            const wait = Number(response.headers.get('Retry-After')) || 30;
            throw new Error(`Too many requests at once. Please wait ${wait} second${wait === 1 ? '' : 's'} and try again.`);
        }
        if (!response.ok) {
            const first = payload.errors ? Object.values(payload.errors).flat()[0] : null;
            throw new Error(first || payload.message || 'Something went wrong. Please try again.');
        }
        return payload;
    }
    const notify = (options) => {
        // Inside the chat window (an iframe) the message is centered on the whole page, not in the small window.
        let fn = window.lspdmsNotify;
        try { if (window.parent !== window && window.parent.lspdmsNotify) fn = window.parent.lspdmsNotify; } catch (error) { /* other origin */ }
        if (fn) fn(options);
    };
    const todayIso = () => {
        const now = new Date();
        return new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    };
    const dayDiff = iso => Math.round((new Date(`${iso}T00:00:00`) - new Date(`${todayIso()}T00:00:00`)) / 86400000);
    const dueLabel = iso => {
        if (!iso) return null;
        const diff = dayDiff(iso);
        const date = new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        if (diff < 0) return { text: `Overdue · ${date}`, tone: 'late' };
        if (diff === 0) return { text: 'Due today', tone: 'soon' };
        if (diff === 1) return { text: 'Due tomorrow', tone: 'soon' };
        return { text: `Due ${date}`, tone: '' };
    };
    const timeAgo = iso => {
        if (!iso) return '';
        const seconds = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
        if (seconds < 60) return 'just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes}m`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h`;
        const days = Math.floor(hours / 24);
        if (days < 7) return `${days}d`;
        return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    };
    const initials = name => (name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0].toUpperCase()).join('') || '?';
    const avatar = (person, size = '') => {
        const node = el('span', `ws-avatar ${size}`.trim());
        node.title = person?.name || '';
        node.append(el('span', 'ws-avatar-initials', initials(person?.name)));
        if (person?.avatar_url) {
            const img = document.createElement('img');
            img.src = person.avatar_url;
            img.alt = '';
            img.loading = 'lazy';
            img.addEventListener('error', () => img.remove(), { once: true });
            node.append(img);
        }
        return node;
    };
    const setCount = (key, count) => {
        const badge = root.querySelector(`[data-count="${key}"]`);
        if (!badge) return;
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.hidden = !count;
    };
    const autoGrow = textarea => {
        textarea.style.height = 'auto';
        textarea.style.height = `${Math.min(textarea.scrollHeight, 160)}px`;
    };

    return { el, icon, request, notify, todayIso, dayDiff, dueLabel, timeAgo, initials, avatar, setCount, autoGrow };
}
