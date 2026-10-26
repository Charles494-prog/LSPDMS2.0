/*
 * Idle sign-out, browser side (the server side is app/Support/IdleTimeout.php).
 *
 * Real activity (keys, clicks, touches, the wheel, typing, moving the mouse) in any tab or chat
 * window of this browser is shared through localStorage. While someone is active, a heartbeat
 * tells the server at most every few minutes, so typing a long form keeps the session; polling
 * never counts. Five minutes before the limit every tab shows a warning with a countdown and
 * "Stay signed in"; at the limit the server confirms and every tab goes to the sign-in page,
 * which says why. Loaded on every signed-in page (motion.js) and in the chat window.
 */
import { BACKGROUND_HEADERS } from './background-request';

const ACTIVITY_KEY = 'lspdms-idle-activity';
const HEARTBEAT_KEY = 'lspdms-idle-heartbeat';
const LOGOUT_KEY = 'lspdms-idle-logout';
const FRAME_MESSAGE = 'lspdms:idle-activity';
// Activity is recorded (and shared with the other tabs) at most this often.
const MARK_EVERY = 5000;

const storage = {
    get(key) { try { return window.localStorage.getItem(key); } catch (error) { return null; } },
    set(key, value) { try { window.localStorage.setItem(key, value); return true; } catch (error) { return false; } },
};

/* Calls onActivity for real input only (isTrusted), never for code that changes the page. */
function listenForActivity(onActivity) {
    let lastInputAt = 0;
    let lastX = null;
    let lastY = null;
    const options = { capture: true, passive: true };
    const input = event => {
        if (!event.isTrusted) return;
        lastInputAt = Date.now();
        // Where the pointer is now, so the move Chrome reports when the page changes under it does not count.
        if (event.screenX !== undefined) { lastX = event.screenX; lastY = event.screenY; }
        onActivity();
    };
    ['pointerdown', 'keydown', 'wheel', 'touchstart', 'input'].forEach(type => document.addEventListener(type, input, options));
    // A real move of the mouse, not a page that scrolls or redraws under a still pointer.
    document.addEventListener('mousemove', event => {
        if (!event.isTrusted || (event.screenX === lastX && event.screenY === lastY)) return;
        lastX = event.screenX;
        lastY = event.screenY;
        onActivity();
    }, options);
    // Scrolling counts only right after real input: code that scrolls (a chat feed jumping to a
    // new message) must not keep an unattended session alive.
    document.addEventListener('scroll', () => { if (Date.now() - lastInputAt < 2000) onActivity(); }, options);
}

/* Chat window (an iframe without the layout): only reports activity to the page around it. */
function startFrameTracker() {
    let lastMark = 0;
    listenForActivity(() => {
        const now = Date.now();
        if (now - lastMark < MARK_EVERY) return;
        lastMark = now;
        if (Number(storage.get(ACTIVITY_KEY)) < now && storage.set(ACTIVITY_KEY, String(now))) return;
        try { window.parent.postMessage({ type: FRAME_MESSAGE }, window.location.origin); } catch (error) { /* page closing */ }
    });
}

function startIdleSession(meta) {
    const limitMs = Number(meta.content) * 1000;
    if (!(limitMs > 0)) return;
    const { heartbeatUrl, logoutUrl, signedOutUrl } = meta.dataset;
    const warnMs = Math.min(5 * 60000, limitMs / 2);
    const heartbeatEvery = Math.min(3 * 60000, Math.max(15000, limitMs / 4));
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    // This page load is activity (the server counted it too), so no heartbeat is needed for it.
    // It started when the navigation did, which is never later than the server's own record.
    const pageLoadedAt = Math.min(Date.now(), Math.round(performance.timeOrigin) || Date.now());
    let lastActivity = Math.max(pageLoadedAt, Number(storage.get(ACTIVITY_KEY)) || 0);
    let heartbeat = { sent: lastActivity, covered: lastActivity };
    let lastMark = 0;
    let tickTimer = 0;
    let heartbeatInFlight = false;
    let expiring = false;
    let leaving = false;
    storage.set(ACTIVITY_KEY, String(lastActivity));
    storage.set(HEARTBEAT_KEY, JSON.stringify(heartbeat));

    const syncShared = () => { lastActivity = Math.max(lastActivity, Number(storage.get(ACTIVITY_KEY)) || 0); };
    const readHeartbeat = () => {
        try { return JSON.parse(storage.get(HEARTBEAT_KEY)) || heartbeat; } catch (error) { return heartbeat; }
    };
    const writeHeartbeat = state => { heartbeat = state; storage.set(HEARTBEAT_KEY, JSON.stringify(state)); };
    const post = (url, body) => fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), ...BACKGROUND_HEADERS },
        body: JSON.stringify(body),
    });

    /* ---------- Warning dialog (the app's centered notice style) ---------- */
    const style = document.createElement('style');
    style.textContent = '.idle-notice .app-notice-action{border:0;font:inherit;font-weight:650;cursor:pointer}'
        + '.idle-notice-countdown{color:var(--text,#111);font-weight:700;font-variant-numeric:tabular-nums}';
    document.head.append(style);
    const dialog = document.createElement('div');
    dialog.className = 'app-notice idle-notice';
    dialog.hidden = true;
    dialog.innerHTML = '<div class="app-notice-card" data-type="info" role="alertdialog" aria-modal="true" aria-labelledby="idleNoticeTitle" aria-describedby="idleNoticeMessage">'
        + '<span class="app-notice-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 7.5V12l3 2M20.5 12a8.5 8.5 0 1 1-17 0 8.5 8.5 0 0 1 17 0Z" pathLength="30"/></svg></span>'
        + '<h2 class="app-notice-title" id="idleNoticeTitle">Are you still there?</h2>'
        + '<p class="app-notice-message" id="idleNoticeMessage">For your security, you will be signed out in <span class="idle-notice-countdown">5:00</span> because there has been no activity.</p>'
        + '<div class="app-notice-actions"><button class="app-notice-action" type="button">Stay signed in</button></div>'
        + '</div>';
    document.body.append(dialog);
    const countdown = dialog.querySelector('.idle-notice-countdown');
    const stayButton = dialog.querySelector('.app-notice-action');
    let returnFocus = null;
    let hideTimer = 0;

    function showWarning(remaining) {
        const seconds = Math.ceil(remaining / 1000);
        countdown.textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        if (!dialog.hidden && dialog.classList.contains('is-open')) return;
        clearTimeout(hideTimer);
        returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        dialog.hidden = false;
        void dialog.offsetWidth; // restart the entrance + icon animation
        dialog.classList.add('is-open');
        stayButton.focus({ preventScroll: true });
    }
    function hideWarning() {
        if (dialog.hidden || !dialog.classList.contains('is-open')) return;
        dialog.classList.remove('is-open');
        hideTimer = setTimeout(() => { dialog.hidden = true; }, 180);
        if (returnFocus && returnFocus !== document.body && document.contains(returnFocus)) returnFocus.focus({ preventScroll: true });
        else if (dialog.contains(document.activeElement)) document.activeElement.blur();
        returnFocus = null;
    }
    const warningOpen = () => !dialog.hidden && dialog.classList.contains('is-open');

    /* ---------- Server sync ---------- */
    // The server saw activity this tab did not (it reports the seconds left, so the two clocks
    // never need to agree): follow it. Whole seconds and request time make a few seconds of noise.
    function adoptServer(remainingSeconds) {
        if (remainingSeconds === null || remainingSeconds === undefined || !Number.isFinite(Number(remainingSeconds))) return;
        const serverActivity = Date.now() - (limitMs - Number(remainingSeconds) * 1000);
        if (serverActivity > lastActivity + 5000) {
            lastActivity = serverActivity;
            storage.set(ACTIVITY_KEY, String(lastActivity));
            // The server already has this time: it needs no heartbeat.
            writeHeartbeat({ sent: Number(readHeartbeat().sent) || 0, covered: lastActivity });
            tick();
        }
    }

    async function sendHeartbeat() {
        const now = Date.now();
        const covered = lastActivity;
        writeHeartbeat({ sent: now, covered });
        heartbeatInFlight = true;
        try {
            const response = await post(heartbeatUrl, { idle_seconds: Math.max(0, Math.floor((now - covered) / 1000)) });
            const body = await response.json().catch(() => ({}));
            if (response.ok) adoptServer(body.remaining);
            else if (response.status === 401 && body.reason === 'idle') leave();
        } catch (error) {
            writeHeartbeat({ sent: 0, covered: 0 }); // offline: the next tick tries again
        } finally {
            heartbeatInFlight = false;
        }
    }
    // At most one heartbeat per interval across all tabs; the first activity after a quiet spell goes at once.
    function maybeHeartbeat() {
        if (heartbeatInFlight || leaving || expiring || !navigator.onLine) return;
        const state = readHeartbeat();
        if (lastActivity > (Number(state.covered) || 0) && Date.now() - (Number(state.sent) || 0) >= heartbeatEvery) sendHeartbeat();
    }

    /* ---------- Sign-out ---------- */
    let channel = null;
    try { channel = new BroadcastChannel('lspdms-auth'); } catch (error) { channel = null; }
    function leave(fromOtherTab = false) {
        if (leaving) return;
        leaving = true;
        clearTimeout(tickTimer);
        if (!fromOtherTab) {
            try { channel?.postMessage({ type: 'idle-logout' }); } catch (error) { /* channel closed */ }
            storage.set(LOGOUT_KEY, String(Date.now()));
        }
        (window.top || window).location.assign(signedOutUrl);
    }
    // The countdown ended here; the server's clock decides (another tab may have been busy).
    async function expire() {
        if (leaving || expiring) return;
        expiring = true;
        try {
            const response = await post(logoutUrl, {});
            const body = await response.json().catch(() => ({}));
            if (response.ok && body.signed_out === false) {
                expiring = false;
                adoptServer(body.remaining);
                tick();
                return;
            }
        } catch (error) {
            // offline or restarting: the sign-out page checks the session itself
        }
        leave();
    }

    /* ---------- Clock ---------- */
    function tick() {
        clearTimeout(tickTimer);
        if (leaving || expiring) return;
        syncShared();
        const remaining = limitMs - (Date.now() - lastActivity);
        if (remaining <= 0) {
            expire();
            return;
        }
        if (remaining <= warnMs) showWarning(remaining);
        else hideWarning();
        maybeHeartbeat();
        tickTimer = setTimeout(tick, warningOpen() ? 1000 - (remaining % 1000 || 0) + 20 : Math.min(10000, Math.max(250, remaining - warnMs)));
    }

    function markActivity(force = false) {
        if (leaving || expiring) return;
        const now = Date.now();
        if (!force && !warningOpen() && now - lastMark < MARK_EVERY) return;
        lastMark = now;
        syncShared();
        // Too late: the session has already passed the limit, so the server decides.
        if (now - lastActivity >= limitMs) {
            expire();
            return;
        }
        lastActivity = now;
        storage.set(ACTIVITY_KEY, String(now));
        if (warningOpen()) tick();
        maybeHeartbeat();
    }

    listenForActivity(markActivity);
    stayButton.addEventListener('click', () => markActivity(true));
    window.addEventListener('message', event => {
        if (event.origin === window.location.origin && event.data?.type === FRAME_MESSAGE) markActivity();
    });
    window.addEventListener('storage', event => {
        if (event.key === LOGOUT_KEY && event.newValue) leave(true);
        else if (event.key === ACTIVITY_KEY && warningOpen()) tick();
    });
    channel?.addEventListener('message', event => { if (event.data?.type === 'idle-logout') leave(true); });
    // Timers sleep with the PC and in background tabs: re-check as soon as the page is seen again.
    document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
    window.addEventListener('pageshow', event => { if (event.persisted) tick(); });
    window.addEventListener('focus', tick);
    // The layout's session check reports the server's own countdown and idle sign-outs.
    window.addEventListener('lspdms:idle-remaining', event => adoptServer(event.detail));
    window.lspdmsIdle = { signedOut: () => leave() };
    tick();
}

if (!window.lspdmsIdleStarted) {
    window.lspdmsIdleStarted = true;
    const meta = document.querySelector('meta[name="lspdms-idle"]');
    if (meta) startIdleSession(meta);
    else if (window.parent !== window) startFrameTracker();
}
