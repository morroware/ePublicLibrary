/**
 * Track reading position. Syncs to server for logged-in users (debounced 5s);
 * falls back to localStorage for guests or when the server is unreachable.
 */

import { post, get } from '../shared/api.js';
import { showToast } from '../reader.js';

const STORAGE_KEY_PREFIX = 'elib-progress-';
const DEBOUNCE_MS = 5000;

export function initProgress(ctx) {
    const { rendition, book, bookUuid, isGuest, progressUrl } = ctx;
    let debounceHandle = null;
    let lastCfi = null;

    const fillEl    = document.getElementById('progress-fill');
    const percentEl = document.getElementById('progress-percent');
    const chapterEl = document.getElementById('progress-chapter');
    const trackEl   = document.getElementById('progress-track');

    rendition.on('relocated', (loc) => {
        lastCfi = loc.start.cfi;
        // Percentage: from locations if generated, else falls back to spine ratio
        let percent = 0;
        try {
            percent = Math.round((book.locations.percentageFromCfi(lastCfi) || 0) * 1000) / 10;
        } catch {}
        if (!percent || isNaN(percent)) {
            const spineIdx = loc.start.index ?? 0;
            const spineLen = (book.spine && book.spine.length) || 1;
            percent = Math.round((spineIdx / spineLen) * 1000) / 10;
        }
        const chapter = currentChapter(book, lastCfi);

        if (fillEl) fillEl.style.width = percent + '%';
        if (percentEl) percentEl.textContent = percent.toFixed(1) + '%';
        if (chapterEl) chapterEl.textContent = chapter || '';

        scheduleSync(percent, chapter);
    });

    function scheduleSync(percentage, chapter) {
        clearTimeout(debounceHandle);
        debounceHandle = setTimeout(() => doSync(percentage, chapter), DEBOUNCE_MS);
    }

    async function doSync(percentage, chapter) {
        // Always save to localStorage as a safety net
        localStorage.setItem(STORAGE_KEY_PREFIX + bookUuid, JSON.stringify({
            cfi: lastCfi, percentage, current_chapter: chapter,
            ts: Date.now(),
        }));
        if (isGuest) return;
        try {
            await post(progressUrl, {
                uuid: bookUuid,
                cfi: lastCfi,
                percentage,
                current_chapter: chapter,
            });
        } catch (e) { /* swallow — localStorage is the fallback */ }
    }

    // Click-to-seek on the progress track. Surface invalid-CFI / load
    // failures to the user instead of failing silently.
    if (trackEl) {
        trackEl.addEventListener('click', (e) => {
            const rect = trackEl.getBoundingClientRect();
            const ratio = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
            let cfi;
            try {
                cfi = book.locations.cfiFromPercentage(ratio);
            } catch (err) {
                showToast('Could not jump to that position.', 'error');
                return;
            }
            if (!cfi) {
                showToast('Position index not ready yet — try again in a moment.', 'info');
                return;
            }
            Promise.resolve(rendition.display(cfi)).catch(() => {
                showToast('Could not load that position.', 'error');
            });
        });
    }

    // For guests, restore the localStorage position if no server progress
    if (isGuest) {
        try {
            const saved = JSON.parse(localStorage.getItem(STORAGE_KEY_PREFIX + bookUuid) || 'null');
            if (saved?.cfi && !ctx.initialCfi) {
                rendition.display(saved.cfi);
            }
        } catch {}
    }
}

function currentChapter(book, cfi) {
    try {
        const spineItem = book.spine.get(cfi);
        if (!spineItem) return '';
        // Try to find a TOC label matching the spine item's href
        const toc = book.navigation?.toc || [];
        const found = findLabel(toc, spineItem.href);
        return found || '';
    } catch { return ''; }
}

function findLabel(items, href) {
    for (const item of items) {
        if (item.href && item.href.includes(href.split('#')[0])) return item.label.trim();
        if (item.subitems?.length) {
            const sub = findLabel(item.subitems, href);
            if (sub) return sub;
        }
    }
    return null;
}
