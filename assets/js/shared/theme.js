/**
 * Theme switcher.
 *
 * Theme state lives on <html data-theme="light|dark|sepia">. Persisted in
 * localStorage so it survives reloads.
 */

const STORAGE_KEY = 'elib-theme';

export function getTheme() {
    return localStorage.getItem(STORAGE_KEY) || 'auto';
}

export function applyTheme(theme) {
    const root = document.documentElement;
    if (theme === 'auto') {
        root.removeAttribute('data-theme');
    } else {
        root.setAttribute('data-theme', theme);
    }
    localStorage.setItem(STORAGE_KEY, theme);
}

export function cycleTheme() {
    const current = getTheme();
    const next = current === 'light' ? 'dark'
              : current === 'dark'  ? 'auto'
              : 'light';
    applyTheme(next);
    return next;
}

export function initThemeToggle() {
    // Apply persisted theme as early as possible
    const stored = getTheme();
    if (stored !== 'auto') {
        document.documentElement.setAttribute('data-theme', stored);
    }
    const btn = document.getElementById('theme-toggle');
    if (btn) {
        btn.addEventListener('click', () => {
            const next = cycleTheme();
            btn.setAttribute('aria-label', `Theme: ${next}`);
        });
    }
}
