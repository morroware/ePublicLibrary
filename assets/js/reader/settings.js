/**
 * Reader settings: font size, font family, line height, theme, margins.
 * Persisted in localStorage (not per-user yet — Phase 3 syncs to server).
 */

import { applyRenditionTheme } from './viewer.js';

const STORAGE_KEY = 'elib-reader-settings';
const DEFAULTS = {
    fontSize:   100,
    fontFamily: 'serif',
    lineHeight: 1.6,
    theme:      'auto',
    margins:    'normal',
};

const FONT_STACKS = {
    'serif':      "'Iowan Old Style', Charter, 'Bitstream Charter', 'Sitka Text', Cambria, serif",
    'sans-serif': "-apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif",
    'dyslexic':   "'OpenDyslexic', 'Iowan Old Style', Charter, serif",
};

const MARGINS = {
    compact: 24,
    normal:  56,
    wide:    96,
};

function load() {
    try {
        return { ...DEFAULTS, ...(JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}) };
    } catch { return { ...DEFAULTS }; }
}
function save(s) { localStorage.setItem(STORAGE_KEY, JSON.stringify(s)); }
export function getStoredTheme() {
    return load().theme;
}

export function initSettings(ctx) {
    const settings = load();
    const { rendition } = ctx;

    function applyAll() {
        try {
            rendition.themes.override('body', {
                'font-family': FONT_STACKS[settings.fontFamily] + ' !important',
                'line-height': String(settings.lineHeight) + ' !important',
            });
            rendition.themes.fontSize(settings.fontSize + '%');
            applyRenditionTheme(rendition, settings.theme);
            // margins map to the rendition padding
            const m = MARGINS[settings.margins] ?? MARGINS.normal;
            ctx.viewer.style.padding = `${m / 2}px ${m}px`;
        } catch (e) { /* rendition may not be ready */ }
    }
    applyAll();

    // Wire UI controls
    const fsInput = document.getElementById('setting-font-size');
    const fsOut   = document.getElementById('font-size-out');
    if (fsInput && fsOut) {
        fsInput.value = settings.fontSize;
        fsOut.textContent = settings.fontSize + '%';
        fsInput.addEventListener('input', () => {
            settings.fontSize = parseInt(fsInput.value, 10);
            fsOut.textContent = settings.fontSize + '%';
            save(settings);
            applyAll();
        });
    }

    const ffInput = document.getElementById('setting-font-family');
    if (ffInput) {
        ffInput.value = settings.fontFamily;
        ffInput.addEventListener('change', () => {
            settings.fontFamily = ffInput.value;
            save(settings);
            applyAll();
        });
    }

    const lhInput = document.getElementById('setting-line-height');
    const lhOut   = document.getElementById('line-height-out');
    if (lhInput && lhOut) {
        lhInput.value = settings.lineHeight;
        lhOut.textContent = settings.lineHeight;
        lhInput.addEventListener('input', () => {
            settings.lineHeight = parseFloat(lhInput.value);
            lhOut.textContent = settings.lineHeight;
            save(settings);
            applyAll();
        });
    }

    document.querySelectorAll('input[name="reader-theme"]').forEach((r) => {
        r.checked = r.value === settings.theme;
        r.addEventListener('change', () => {
            if (r.checked) {
                settings.theme = r.value;
                save(settings);
                applyAll();
            }
        });
    });

    const mgInput = document.getElementById('setting-margins');
    if (mgInput) {
        mgInput.value = settings.margins;
        mgInput.addEventListener('change', () => {
            settings.margins = mgInput.value;
            save(settings);
            applyAll();
        });
    }
}
