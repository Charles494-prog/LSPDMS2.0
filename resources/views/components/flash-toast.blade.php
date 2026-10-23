@props(['message' => null, 'changes' => null])

{{--
    Centered result message used by every page (replaces the old corner toasts).
    Server flashes: pass `message` (and optionally `changes`, a list of strings or
    ['label' => ..., 'from' => ..., 'to' => ...] items) and it opens on load.
    In-page saves call window.lspdmsNotify({ type, title, message, changes }).
    type is success, error or info. An info message can carry an action link:
    { action: { label, href }, dismissLabel } (the Tasks reminder uses this).
    Success closes by itself after a few seconds; errors and info stay until dismissed.
    onClose (optional) runs when the message is closed without following the action.
--}}
@once
    <style>
        .app-notice {
            position: fixed;
            inset: 0;
            z-index: 100200;
            display: grid;
            place-items: center;
            padding: max(1rem, env(safe-area-inset-top)) 1rem max(1rem, env(safe-area-inset-bottom));
            background: rgba(10, 12, 16, .38);
            -webkit-backdrop-filter: blur(3px);
            backdrop-filter: blur(3px);
            opacity: 0;
            transition: opacity .18s ease;
        }
        .app-notice[hidden] { display: none; }
        .app-notice.is-open { opacity: 1; }
        .app-notice-card {
            --notice-tone: var(--success, #16a34a);
            position: relative;
            display: grid;
            justify-items: center;
            gap: .55rem;
            width: min(420px, 100%);
            max-height: calc(100vh - 2rem);
            overflow: auto;
            padding: 1.6rem 1.4rem 1.2rem;
            border: 1px solid var(--border, #e5e5e5);
            border-radius: 14px;
            background: var(--surface, #fff);
            color: var(--text, #111);
            box-shadow: 0 30px 80px -20px rgba(0, 0, 0, .45);
            font: 400 .92rem/1.5 -apple-system, 'Helvetica Neue', Arial, sans-serif;
            text-align: center;
            overflow-wrap: anywhere;
            transform: translateY(10px) scale(.96);
            transition: transform .28s cubic-bezier(.22, .9, .32, 1);
        }
        .app-notice.is-open .app-notice-card { transform: none; }
        .app-notice-card[data-type="error"] { --notice-tone: var(--danger, #dc2626); }
        .app-notice-card[data-type="info"] { --notice-tone: var(--accent, #0070f3); }
        .app-notice-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: .5rem; margin-top: .5rem; }
        .app-notice-actions .app-notice-ok { margin-top: 0; }
        .app-notice-action { display: inline-flex; align-items: center; justify-content: center; min-width: 9rem; padding: .55rem 1.2rem; border-radius: 8px; background: var(--notice-tone); color: #fff; font-weight: 650; text-decoration: none; }
        .app-notice-action:hover { filter: brightness(1.08); }
        .app-notice-action:focus-visible { outline: 2px solid var(--notice-tone); outline-offset: 3px; }
        .app-notice-action[hidden] { display: none; }
        .app-notice-card[data-type="info"] .app-notice-ok { background: transparent; color: var(--text, #111); box-shadow: inset 0 0 0 1px var(--border, #e5e5e5); }
        .app-notice-card[data-type="info"] .app-notice-icon { animation: app-notice-ring 1.4s ease-in-out 2; }
        @keyframes app-notice-ring { 0%, 100% { transform: rotate(0); } 15% { transform: rotate(14deg); } 30% { transform: rotate(-12deg); } 45% { transform: rotate(8deg); } 60% { transform: rotate(-4deg); } }
        .app-notice-icon {
            display: grid;
            place-items: center;
            width: 3.2rem;
            height: 3.2rem;
            border-radius: 50%;
            background: color-mix(in srgb, var(--notice-tone) 14%, transparent);
            color: var(--notice-tone);
        }
        .app-notice-icon svg { width: 1.7rem; height: 1.7rem; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }
        .app-notice-icon path { stroke-dasharray: 30; stroke-dashoffset: 30; }
        .app-notice.is-open .app-notice-icon path { animation: app-notice-draw .45s .12s ease-out forwards; }
        @keyframes app-notice-draw { to { stroke-dashoffset: 0; } }
        .app-notice-title { margin: .1rem 0 0; font-size: 1.08rem; font-weight: 700; letter-spacing: -.01em; }
        .app-notice-message { margin: 0; color: var(--text-muted, #555); }
        .app-notice-changes {
            display: grid;
            gap: .35rem;
            width: 100%;
            margin: .35rem 0 0;
            padding: .7rem .8rem;
            border: 1px solid var(--border, #e5e5e5);
            border-radius: 10px;
            background: color-mix(in srgb, var(--text, #111) 3%, transparent);
            list-style: none;
            text-align: left;
            font-size: .84rem;
        }
        .app-notice-changes[hidden], .app-notice-timer[hidden] { display: none; }
        .app-notice-changes li { display: flex; flex-wrap: wrap; align-items: baseline; gap: .2rem .45rem; }
        .app-notice-changes li::before { content: ""; flex: none; width: .4rem; height: .4rem; margin-right: .1rem; border-radius: 50%; background: var(--notice-tone); transform: translateY(-.1rem); }
        .app-notice-changes .label { font-weight: 650; }
        .app-notice-changes .from { color: var(--text-muted, #666); text-decoration: line-through; }
        .app-notice-changes .arrow { color: var(--text-muted, #666); }
        .app-notice-changes .to { font-weight: 650; }
        .app-notice-ok {
            min-width: 7rem;
            margin-top: .5rem;
            padding: .55rem 1.2rem;
            border: 0;
            border-radius: 8px;
            background: var(--text, #111);
            color: var(--surface, #fff);
            font: inherit;
            font-weight: 650;
            cursor: pointer;
        }
        .app-notice-ok:focus-visible { outline: 2px solid var(--notice-tone); outline-offset: 3px; }
        .app-notice-timer { position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: var(--notice-tone); transform-origin: left; opacity: .7; }
        .app-notice-card:hover .app-notice-timer { animation-play-state: paused !important; }
        @keyframes app-notice-timer { from { transform: scaleX(1); } to { transform: scaleX(0); } }
        @media (prefers-reduced-motion: reduce) {
            .app-notice, .app-notice-card { transition: none; }
            .app-notice-icon path { stroke-dashoffset: 0; animation: none !important; }
        }
    </style>
    <div class="app-notice" id="appNotice" hidden>
        <div class="app-notice-card" role="alertdialog" aria-modal="true" aria-labelledby="appNoticeTitle" aria-describedby="appNoticeMessage">
            <span class="app-notice-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="" pathLength="30"/></svg></span>
            <h2 class="app-notice-title" id="appNoticeTitle"></h2>
            <p class="app-notice-message" id="appNoticeMessage"></p>
            <ul class="app-notice-changes" hidden></ul>
            <div class="app-notice-actions">
                <a class="app-notice-action" href="#" hidden></a>
                <button class="app-notice-ok" type="button">OK</button>
            </div>
            <span class="app-notice-timer" aria-hidden="true"></span>
        </div>
    </div>
    <script>
        (() => {
            const root = document.getElementById('appNotice');
            if (!root) return;
            const card = root.querySelector('.app-notice-card');
            const iconPath = root.querySelector('.app-notice-icon path');
            const title = root.querySelector('.app-notice-title');
            const message = root.querySelector('.app-notice-message');
            const list = root.querySelector('.app-notice-changes');
            const ok = root.querySelector('.app-notice-ok');
            const actionLink = root.querySelector('.app-notice-action');
            const bar = root.querySelector('.app-notice-timer');
            const ICONS = {
                success: 'M5 12.5l4.5 4.5L19 7.5',
                error: 'M12 7v6M12 16.5v.5',
                info: 'M18 9a6 6 0 0 0-12 0c0 6-2.5 7.5-2.5 7.5h17S18 15 18 9ZM10 20a2.2 2.2 0 0 0 4 0',
            };
            let timer = null, remaining = 0, startedAt = 0, returnFocus = null, onClose = null;

            const close = () => {
                clearTimeout(timer);
                const callback = onClose;
                onClose = null;
                if (callback && !root.hidden) callback();
                root.classList.remove('is-open');
                setTimeout(() => { if (!root.classList.contains('is-open')) root.hidden = true; }, 180);
                if (returnFocus && document.contains(returnFocus)) returnFocus.focus({ preventScroll: true });
                returnFocus = null;
            };
            const startTimer = ms => {
                remaining = ms; startedAt = Date.now();
                clearTimeout(timer);
                timer = setTimeout(close, ms);
            };

            // Changes: strings, or { label, from, to } objects (from/to are optional).
            const renderChanges = changes => {
                list.replaceChildren();
                (changes || []).filter(Boolean).forEach(change => {
                    const li = document.createElement('li');
                    if (typeof change === 'string') li.textContent = change;
                    else {
                        const add = (cls, text) => { const span = document.createElement('span'); span.className = cls; span.textContent = text; li.append(span); };
                        if (change.label) add('label', change.from !== undefined || change.to !== undefined ? `${change.label}:` : change.label);
                        if (change.from !== undefined && change.from !== null && change.from !== '') { add('from', String(change.from)); add('arrow', '→'); }
                        if (change.to !== undefined && change.to !== null) add('to', String(change.to === '' ? '(blank)' : change.to));
                    }
                    list.append(li);
                });
                list.hidden = !list.children.length;
            };

            window.lspdmsNotify = (options = {}) => {
                const opts = typeof options === 'string' ? { message: options } : options;
                const type = ['error', 'info'].includes(opts.type) ? opts.type : 'success';
                if (!opts.message && !opts.title) return;
                card.dataset.type = type;
                iconPath.setAttribute('d', ICONS[type]);
                // Without a title, a short message is shown as the heading itself.
                title.textContent = opts.title || opts.message;
                message.textContent = opts.title ? (opts.message || '') : '';
                message.hidden = !message.textContent;
                renderChanges(opts.changes);
                actionLink.hidden = !opts.action?.href;
                if (opts.action?.href) {
                    actionLink.href = opts.action.href;
                    actionLink.textContent = opts.action.label || 'Open';
                }
                ok.textContent = opts.dismissLabel || 'OK';
                onClose = typeof opts.onClose === 'function' ? opts.onClose : null;
                returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
                root.hidden = false;
                // restart the entrance + icon animation
                root.classList.remove('is-open');
                void root.offsetWidth;
                root.classList.add('is-open');
                // Focus the dismiss button even when there is an action link: an Enter meant for
                // something else then just closes the message instead of leaving the page.
                ok.focus({ preventScroll: true });
                const duration = type !== 'success' ? 0 : (opts.duration ?? (list.hidden ? 3200 : 5200));
                bar.hidden = !duration;
                bar.style.animation = 'none';
                void bar.offsetWidth;
                if (duration) {
                    bar.style.animation = `app-notice-timer ${duration}ms linear forwards`;
                    startTimer(duration);
                } else {
                    clearTimeout(timer);
                }
            };

            ok.addEventListener('click', close);
            actionLink.addEventListener('click', () => { onClose = null; });
            root.addEventListener('click', event => { if (event.target === root) close(); });
            document.addEventListener('keydown', event => {
                if (root.hidden) return;
                if (event.key === 'Escape') { event.preventDefault(); close(); }
                if (event.key === 'Tab') {
                    // keep focus inside: the action link (when shown) and OK
                    event.preventDefault();
                    const controls = [actionLink, ok].filter(control => !control.hidden);
                    const next = controls[(controls.indexOf(document.activeElement) + 1) % controls.length];
                    next.focus();
                }
            });
            // Hovering pauses the auto-close so the change list can be read.
            card.addEventListener('mouseenter', () => {
                if (!timer || bar.hidden) return;
                clearTimeout(timer);
                remaining -= Date.now() - startedAt;
            });
            card.addEventListener('mouseleave', () => { if (!bar.hidden && remaining > 0 && !root.hidden) startTimer(remaining); });
        })();
    </script>
@endonce

@if (filled($message))
    <script>
        window.lspdmsNotify({ message: @json($message), changes: @json($changes ?? []) });
    </script>
@endif
