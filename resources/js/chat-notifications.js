import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const widget = document.getElementById('chatWidget');
const launcher = document.getElementById('chatLauncher');
const badge = document.getElementById('chatUnreadBadge');

if (widget && launcher && badge) {
    const endpoint = widget.dataset.unreadUrl;
    let requestInFlight = false;
    let refreshQueued = false;

    function displayUnreadCount(count) {
        const unread = Math.max(0, Number(count) || 0);
        badge.textContent = unread > 99 ? '99+' : String(unread);
        badge.hidden = unread === 0;
        launcher.setAttribute('aria-label', unread === 1
            ? 'Open messages, 1 unread message'
            : `Open messages, ${unread} unread messages`);
    }

    function setNotificationConnectionState(state) {
        const status = state === 'connected'
            ? 'Live chat notifications connected'
            : `Live chat notifications ${state.replaceAll('-', ' ')}; checking periodically`;
        launcher.title = status;
        const changed = launcher.dataset.realtimeState !== state;
        launcher.dataset.realtimeState = state;
        // The poll interval depends on the socket state, so re-arm it when that changes.
        if (changed) schedulePoll();
    }

    async function refreshUnreadCount() {
        if (requestInFlight) {
            refreshQueued = true;
            return;
        }
        requestInFlight = true;
        try {
            const response = await fetch(endpoint, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) throw new Error(`Unread message count request failed (${response.status}).`);
            const data = await response.json();
            displayUnreadCount(data.unread_count);
        } catch (error) {
            console.error('Unable to refresh unread message count:', error);
        } finally {
            requestInFlight = false;
            if (refreshQueued) {
                refreshQueued = false;
                refreshUnreadCount();
            }
        }
    }

    // Fast polling only stands in for the socket; while Reverb is connected it pushes
    // '.message.sent', so the poll becomes a slow safety net. Hidden tabs never poll.
    const FALLBACK_POLL_MS = 5000;
    const CONNECTED_POLL_MS = 90000;
    let pollTimer = null;

    function schedulePoll() {
        window.clearTimeout(pollTimer);
        pollTimer = null;
        if (document.hidden) return;
        const delay = launcher.dataset.realtimeState === 'connected' ? CONNECTED_POLL_MS : FALLBACK_POLL_MS;
        pollTimer = window.setTimeout(() => {
            refreshUnreadCount();
            schedulePoll();
        }, delay);
    }

    refreshUnreadCount();
    schedulePoll();
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshUnreadCount();
        schedulePoll();
    });

    const reverbConfigured = widget.dataset.reverbConfigured === 'true'
        && Boolean(import.meta.env.VITE_REVERB_APP_KEY);
    if (reverbConfigured) {
        const echo = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: widget.dataset.authUrl,
            auth: {
                headers: {
                    'X-CSRF-TOKEN': widget.dataset.csrf,
                    Accept: 'application/json',
                },
            },
        });

        echo.connector.pusher.connection.bind('state_change', ({ current }) => {
            setNotificationConnectionState(current);
            if (current === 'connected') refreshUnreadCount();
        });
        echo.private(`user.${widget.dataset.userId}`)
            .listen('.message.sent', refreshUnreadCount)
            // Tasks: the layout's reminder and the Tasks page react to this right away.
            .listen('.task.updated', event => window.dispatchEvent(new CustomEvent('lspdms:task-updated', { detail: event })))
            .error(() => setNotificationConnectionState('subscription-error'));
    } else {
        setNotificationConnectionState('polling-only');
    }

    window.addEventListener('message', event => {
        const frame = widget.querySelector('iframe');
        if (event.origin !== window.location.origin || event.source !== frame?.contentWindow) return;
        if (event.data?.type === 'chat:unread-refresh') refreshUnreadCount();
    });
}
