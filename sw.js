/**
 * ePublicLibrary service worker.
 *
 * Caching strategy:
 *   - App shell (CSS, JS, fonts, favicon, manifest) → cache-first
 *   - Library / book / read HTML → network-first with offline fallback
 *   - Cover images → stale-while-revalidate
 *   - EPUB streams (api/download.php?stream=1) → cache-first up to N books
 *
 * The service worker is registered at the install base URL (computed by
 * shared/sw-register.js from <meta name="app-base">). All cache keys are
 * versioned so a deploy invalidates the old shell.
 */

const VERSION    = 'v1';
const APP_SHELL  = `elib-shell-${VERSION}`;
const HTML_CACHE = `elib-html-${VERSION}`;
const COVERS     = `elib-covers-${VERSION}`;
const BOOKS      = `elib-books-${VERSION}`;
const BOOK_QUOTA = 3;  // last-read N books kept offline

// Pre-cache the app shell at install time. The list is built relative to
// the SW's own location, so it works at the document root or inside a
// subdirectory install.
const SHELL_RELATIVE = [
    '',                                 // base URL itself
    'offline.html',
    'assets/css/design-system.css',
    'assets/css/base.css',
    'assets/css/components.css',
    'assets/css/library.css',
    'assets/css/discovery.css',
    'assets/css/reader.css',
    'assets/js/library.js',
    'assets/js/reader.js',
    'assets/js/shared/api.js',
    'assets/js/shared/toast.js',
    'assets/js/shared/theme.js',
    'assets/js/shared/combobox.js',
    'assets/js/shared/focus-trap.js',
];

const base = new URL('./', self.location).href;
const shellUrls = SHELL_RELATIVE.map((p) => new URL(p, base).href);

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(APP_SHELL);
        await Promise.allSettled(
            shellUrls.map((u) => cache.add(new Request(u, { cache: 'reload' })))
        );
        self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keep = new Set([APP_SHELL, HTML_CACHE, COVERS, BOOKS]);
        const names = await caches.keys();
        await Promise.all(names.map((n) => keep.has(n) ? null : caches.delete(n)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);

    // Only intercept same-origin requests within our base path
    if (url.origin !== self.location.origin) return;
    if (!url.pathname.startsWith(new URL(base).pathname)) return;

    // ---- HTML navigations: network-first with offline fallback ----
    if (req.mode === 'navigate' || req.headers.get('accept')?.includes('text/html')) {
        event.respondWith(htmlStrategy(req));
        return;
    }

    // ---- Cover images: stale-while-revalidate ----
    if (url.pathname.includes('/assets/covers/')) {
        event.respondWith(staleWhileRevalidate(req, COVERS));
        return;
    }

    // ---- EPUB streams: cache-first (with quota) ----
    if (url.pathname.endsWith('/api/download.php') && url.searchParams.get('stream') === '1') {
        event.respondWith(bookStrategy(req));
        return;
    }

    // ---- App shell assets: cache-first ----
    if (
        url.pathname.includes('/assets/css/') ||
        url.pathname.includes('/assets/js/')  ||
        url.pathname.includes('/assets/fonts/') ||
        url.pathname.includes('/assets/vendor/')
    ) {
        event.respondWith(cacheFirst(req, APP_SHELL));
        return;
    }
});

async function htmlStrategy(req) {
    try {
        const fresh = await fetch(req);
        const cache = await caches.open(HTML_CACHE);
        cache.put(req, fresh.clone());
        return fresh;
    } catch {
        const cached = await caches.match(req);
        if (cached) return cached;
        // Fallback to a pre-cached offline page
        const offline = await caches.match(new URL('offline.html', base).href);
        return offline || new Response('Offline', { status: 503, statusText: 'Offline' });
    }
}

async function cacheFirst(req, cacheName) {
    const cached = await caches.match(req);
    if (cached) return cached;
    try {
        const fresh = await fetch(req);
        if (fresh.ok) {
            const cache = await caches.open(cacheName);
            cache.put(req, fresh.clone());
        }
        return fresh;
    } catch {
        return cached || Response.error();
    }
}

async function staleWhileRevalidate(req, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(req);
    const fetched = fetch(req).then((res) => {
        if (res.ok) cache.put(req, res.clone());
        return res;
    }).catch(() => cached);
    return cached || fetched;
}

async function bookStrategy(req) {
    const cache = await caches.open(BOOKS);
    const cached = await cache.match(req);
    if (cached) {
        // Refresh in background so updates land eventually
        fetch(req).then((res) => res.ok && cache.put(req, res.clone())).catch(() => {});
        return cached;
    }
    try {
        const fresh = await fetch(req);
        if (fresh.ok) {
            cache.put(req, fresh.clone());
            await trimBookCache(cache);
        }
        return fresh;
    } catch {
        return Response.error();
    }
}

async function trimBookCache(cache) {
    const requests = await cache.keys();
    if (requests.length <= BOOK_QUOTA) return;
    // Oldest-first; Cache API preserves insertion order so trim from the front
    const excess = requests.length - BOOK_QUOTA;
    for (let i = 0; i < excess; i++) {
        await cache.delete(requests[i]);
    }
}
