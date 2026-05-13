/**
 * In-book search — iterates the spine, fetches each chapter, and finds
 * matches with snippets and tap-to-jump.
 *
 * Lazy: the spine is only loaded when the user opens the search panel.
 * Results are streamed into the panel as each chapter finishes.
 */

import { showToast } from '../reader.js';

export function initInBookSearch(ctx) {
    const { book, rendition } = ctx;
    const input  = document.getElementById('in-book-search-input');
    const status = document.getElementById('in-book-search-status');
    const list   = document.getElementById('in-book-search-results');
    if (!input || !list) return;

    let lastQuery = '';
    let aborted = false;
    let runId = 0;

    input.addEventListener('input', () => {
        const q = input.value.trim();
        if (q === lastQuery) return;
        lastQuery = q;
        aborted = true;
        list.innerHTML = '';
        status.textContent = '';
        if (q.length < 2) return;
        aborted = false;
        runSearch(q, ++runId);
    });

    const CHAPTER_LOAD_TIMEOUT_MS = 8000;

    function withTimeout(promise, ms) {
        return new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('timeout')), ms);
            promise.then(
                (v) => { clearTimeout(timer); resolve(v); },
                (e) => { clearTimeout(timer); reject(e); }
            );
        });
    }

    async function runSearch(q, myRun) {
        const lower = q.toLowerCase();
        const items = book?.spine?.spineItems ?? book?.spine?.items ?? [];
        if (items.length === 0) {
            status.textContent = 'Book index not ready yet — try again in a moment.';
            return;
        }
        let totalMatches = 0;
        let skipped = 0;
        for (let i = 0; i < items.length; i++) {
            if (aborted || myRun !== runId) return;
            status.textContent = `Searching… (${i + 1} / ${items.length})`;
            const item = items[i];
            try {
                // Per-chapter timeout: a single slow/broken chapter must not
                // hang the entire search.
                await withTimeout(item.load(book.load.bind(book)), CHAPTER_LOAD_TIMEOUT_MS);
                const doc = item.document || item.contents?.document;
                if (!doc) { try { item.unload(); } catch {} continue; }
                const text = doc.body ? doc.body.textContent || '' : '';
                const hits = findMatches(text, lower, q, item);
                if (hits.length) {
                    totalMatches += hits.length;
                    appendHits(hits, item);
                }
                try { item.unload(); } catch {}
            } catch {
                skipped += 1;
                try { item.unload(); } catch {}
            }
        }
        if (myRun === runId) {
            const skipNote = skipped > 0 ? ` (${skipped} skipped)` : '';
            status.textContent = totalMatches === 0
                ? `No matches.${skipNote}`
                : `${totalMatches} match${totalMatches === 1 ? '' : 'es'} across ${items.length} chapter${items.length === 1 ? '' : 's'}.${skipNote}`;
        }
    }

    function findMatches(text, lower, original, spineItem) {
        const out = [];
        let idx = 0;
        const haystack = text.toLowerCase();
        while ((idx = haystack.indexOf(lower, idx)) !== -1) {
            const start = Math.max(0, idx - 40);
            const end   = Math.min(text.length, idx + lower.length + 80);
            const before = text.slice(start, idx);
            const match  = text.slice(idx, idx + lower.length);
            const after  = text.slice(idx + lower.length, end);
            out.push({
                before, match, after,
                href: spineItem.href,
                cfi:  spineItem.cfiBase ? `${spineItem.cfiBase}!/4` : null,
            });
            idx += lower.length;
            if (out.length >= 20) break;  // cap matches per chapter
        }
        return out;
    }

    function appendHits(hits, spineItem) {
        const groupHeader = document.createElement('li');
        groupHeader.className = 'in-book-search-section';
        const title = titleFor(spineItem) || spineItem.href || 'Section';
        groupHeader.textContent = title;
        list.appendChild(groupHeader);
        hits.forEach((h) => {
            const li = document.createElement('li');
            li.className = 'in-book-search-hit';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'in-book-search-jump';
            btn.innerHTML =
                escapeHtml(h.before) +
                '<mark>' + escapeHtml(h.match) + '</mark>' +
                escapeHtml(h.after) + '…';
            btn.addEventListener('click', () => {
                rendition.display(h.href);
                document.getElementById('panel-search')?.setAttribute('hidden', '');
            });
            li.appendChild(btn);
            list.appendChild(li);
        });
    }

    function titleFor(spineItem) {
        const toc = book.navigation?.toc || [];
        const href = (spineItem.href || '').split('#')[0];
        const dig = (items) => {
            for (const item of items) {
                if (item.href && item.href.includes(href)) return item.label.trim();
                if (item.subitems?.length) {
                    const sub = dig(item.subitems);
                    if (sub) return sub;
                }
            }
            return null;
        };
        return dig(toc);
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, (c) =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
}
