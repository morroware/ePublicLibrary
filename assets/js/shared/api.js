/**
 * Tiny fetch wrapper.
 * - Reads APP_BASE from <meta name="app-base"> so URLs honor subdirectory installs.
 * - Sends CSRF token on every non-GET request.
 * - Returns parsed JSON; throws ApiError on non-2xx with status + message.
 */

const APP_BASE = (document.querySelector('meta[name=app-base]')?.content || '').replace(/\/$/, '');
const CSRF = document.querySelector('meta[name=csrf-token]')?.content || '';

export class ApiError extends Error {
    constructor(message, status, body) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.body = body;
    }
}

export function url(path) {
    if (/^https?:\/\//.test(path)) return path;
    return `${APP_BASE}/${path.replace(/^\//, '')}`;
}

export async function request(path, { method = 'GET', body, headers = {}, ...rest } = {}) {
    const opts = {
        method,
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            ...(body && typeof body !== 'string' && !(body instanceof FormData)
                ? { 'Content-Type': 'application/json' } : {}),
            ...(method !== 'GET' && method !== 'HEAD' ? { 'X-CSRF-Token': CSRF } : {}),
            ...headers,
        },
        ...rest,
    };
    if (body && typeof body === 'object' && !(body instanceof FormData)) {
        opts.body = JSON.stringify(body);
    } else if (body !== undefined) {
        opts.body = body;
    }

    const response = await fetch(url(path), opts);
    const text = await response.text();
    let parsed;
    try { parsed = text ? JSON.parse(text) : null; } catch { parsed = text; }

    if (!response.ok) {
        const message = (parsed && parsed.error) || response.statusText || `HTTP ${response.status}`;
        throw new ApiError(message, response.status, parsed);
    }
    return parsed;
}

export const get  = (path, opts)         => request(path, { method: 'GET',  ...opts });
export const post = (path, body, opts)   => request(path, { method: 'POST', body, ...opts });
export const del  = (path, opts)         => request(path, { method: 'DELETE', ...opts });

export { APP_BASE };
