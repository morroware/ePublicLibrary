/**
 * Reading session tracker.
 *
 * Opens a session on reader boot (for authed users), beats every 30s, and
 * closes on pagehide / visibilitychange / beforeunload using
 * navigator.sendBeacon so we don't lose the tail of a session on tab close.
 */

import { post } from '../shared/api.js';

const HEARTBEAT_MS = 30 * 1000;
const IDLE_TIMEOUT_MS = 5 * 60 * 1000;

export function initSessions(ctx) {
    if (ctx.isGuest) return;
    const { bookUuid } = ctx;
    if (!bookUuid) return;

    const startedAt = Date.now();
    let sessionId = null;
    let pagesRead = 0;
    let lastInteraction = startedAt;
    let lastCfi = ctx.initialCfi || null;
    let beatTimer = null;
    let ended = false;

    /* ---- Track activity to avoid logging idle time ---- */
    const bumpInteraction = () => { lastInteraction = Date.now(); };
    ['mousemove', 'keydown', 'touchstart', 'click'].forEach((ev) =>
        document.addEventListener(ev, bumpInteraction, { passive: true }));

    ctx.rendition?.on('relocated', (loc) => {
        pagesRead += 1;
        lastCfi = loc?.start?.cfi || lastCfi;
        bumpInteraction();
    });

    /* ---- Start ---- */
    (async () => {
        try {
            const res = await post('api/sessions.php', {
                action: 'start',
                uuid: bookUuid,
                start_cfi: ctx.initialCfi || null,
            });
            sessionId = res.id;
            beatTimer = setInterval(beat, HEARTBEAT_MS);
        } catch {
            /* swallow — sessions are best-effort */
        }
    })();

    function elapsedSeconds() {
        const now = Date.now();
        // Drop big idle gaps from the duration estimate
        const elapsed = (now - startedAt) / 1000;
        const sinceInteraction = (now - lastInteraction) / 1000;
        return Math.max(0, Math.round(elapsed - Math.max(0, sinceInteraction - 30)));
    }

    async function beat() {
        if (!sessionId || ended) return;
        // If the user has been idle for a long time, stop heartbeating
        if (Date.now() - lastInteraction > IDLE_TIMEOUT_MS) return;
        try {
            await post('api/sessions.php', {
                action: 'heartbeat',
                id: sessionId,
                duration_seconds: elapsedSeconds(),
                pages_read: pagesRead,
                end_cfi: lastCfi,
            });
        } catch { /* ignore */ }
    }

    function sendFinalBeacon() {
        if (!sessionId || ended) return;
        ended = true;
        clearInterval(beatTimer);
        const body = JSON.stringify({
            action: 'heartbeat',
            id: sessionId,
            duration_seconds: elapsedSeconds(),
            pages_read: pagesRead,
            end_cfi: lastCfi,
        });
        const APP_BASE = (document.querySelector('meta[name=app-base]')?.content || '').replace(/\/$/, '');
        const url = `${APP_BASE}/api/sessions.php`;
        // sendBeacon ignores custom headers; the server can fall back to
        // identifying the user via the session cookie. CSRF check requires
        // X-CSRF-Token, so we send the token in the body and let the API
        // accept either header or body token. The current API checks the
        // header — see api/sessions.php and includes/csrf.php (request_token
        // reads both POST body and header).
        if (navigator.sendBeacon) {
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
            const blob = new Blob([body], { type: 'application/json' });
            // Workaround: append CSRF as a header isn't possible with sendBeacon;
            // post() reads _token from form-encoded bodies, so duplicate as a
            // query string.
            navigator.sendBeacon(`${url}?_token=${encodeURIComponent(csrf)}`, blob);
        } else {
            // Fallback: synchronous-ish fetch with keepalive
            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]')?.content || '' },
                body,
                keepalive: true,
            }).catch(() => {});
        }
    }

    window.addEventListener('pagehide',       sendFinalBeacon);
    window.addEventListener('beforeunload',   sendFinalBeacon);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) beat();   // flush before tab goes away
    });
}
