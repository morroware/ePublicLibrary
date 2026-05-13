/**
 * Service worker registration. Runs on the library and reader pages.
 *
 * The service worker is at the install base ({base}/sw.js) so its scope
 * covers the whole app (root install or subdir install).
 */

const APP_BASE = (document.querySelector('meta[name=app-base]')?.content || '').replace(/\/$/, '');

export function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;
    if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
        // SWs require HTTPS in production; skip on plain HTTP demos.
        return;
    }
    const swUrl    = `${APP_BASE}/sw.js`;
    const scope    = `${APP_BASE}/` || '/';
    navigator.serviceWorker.register(swUrl, { scope }).catch((e) => {
        // Non-fatal — the app still works without the SW
        console.warn('Service worker registration failed:', e);
    });
}
