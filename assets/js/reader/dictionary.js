/**
 * Dictionary popup — click/tap a word in the rendition to see its
 * definition fetched from /api/dictionary.php (which proxies and caches
 * dictionaryapi.dev).
 *
 * Enabled in settings (off by default to keep regular text selection
 * smooth for highlighting). When on, single-word clicks open the popover;
 * multi-word selections fall through to the highlight color picker.
 */

import { get, url } from '../shared/api.js';

const STORAGE_KEY = 'elib-dictionary-enabled';

export function initDictionary(ctx) {
    const { rendition } = ctx;
    const toggle = document.getElementById('setting-dictionary');
    const popover = buildPopover();
    let enabled = (localStorage.getItem(STORAGE_KEY) || '0') === '1';

    if (toggle) {
        toggle.checked = enabled;
        toggle.addEventListener('change', () => {
            enabled = toggle.checked;
            localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0');
        });
    }

    rendition.hooks.content.register((contents) => {
        const doc = contents.document;
        doc.addEventListener('dblclick', onDblClick);
        // Single tap on touch (mobile): use a long-press alternative? For
        // Phase 3 we keep dictionary on double-tap to avoid disrupting page
        // navigation taps.
    });

    function onDblClick(e) {
        if (!enabled) return;
        const sel = e.view.getSelection();
        let word = sel ? sel.toString().trim() : '';
        if (!word && e.target && e.target.nodeType === 3) {
            word = wordAt(e.target.textContent, e);
        }
        if (!word || !/^[a-z\-']{2,40}$/i.test(word)) return;
        sel?.removeAllRanges();
        showPopover(word, e);
    }

    function wordAt(text, ev) {
        // Approximate: use caretRangeFromPoint where supported
        try {
            const range = (ev.view.document.caretRangeFromPoint || (() => null))(ev.clientX, ev.clientY);
            if (!range) return '';
            const node = range.startContainer;
            const offset = range.startOffset;
            const left  = node.textContent.slice(0, offset).match(/[a-z\-']*$/i)?.[0] || '';
            const right = node.textContent.slice(offset).match(/^[a-z\-']*/i)?.[0] || '';
            return (left + right).trim();
        } catch {
            return '';
        }
    }

    function buildPopover() {
        const el = document.createElement('div');
        el.className = 'dictionary-popover';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-label', 'Definition');
        el.hidden = true;
        el.innerHTML = `
            <header class="dictionary-popover-header">
                <strong class="dictionary-word"></strong>
                <span class="dictionary-phonetic"></span>
                <button type="button" class="dictionary-close" aria-label="Close">×</button>
            </header>
            <div class="dictionary-body"></div>
        `;
        document.body.appendChild(el);
        el.querySelector('.dictionary-close').addEventListener('click', hide);
        return el;
    }

    function showPopover(word, anchorEvent) {
        popover.hidden = false;
        popover.querySelector('.dictionary-word').textContent = word;
        popover.querySelector('.dictionary-phonetic').textContent = '';
        popover.querySelector('.dictionary-body').innerHTML = '<p class="dictionary-loading">Looking up…</p>';

        // Position relative to the trigger event (in iframe coords)
        const iframeRect = ctx.viewer.querySelector('iframe')?.getBoundingClientRect();
        const top  = (iframeRect?.top || 0) + (anchorEvent.clientY || 0) + 12;
        const left = (iframeRect?.left || 0) + (anchorEvent.clientX || 0);
        popover.style.top  = `${Math.max(8, top + window.scrollY)}px`;
        popover.style.left = `${Math.max(8, Math.min(window.innerWidth - 340, left + window.scrollX - 160))}px`;

        lookup(word);
    }

    function hide() {
        popover.hidden = true;
    }

    // Outside-pointer dismissal (covers mouse and touch).
    ['mousedown', 'touchstart'].forEach((ev) => {
        document.addEventListener(ev, (e) => {
            if (!popover.contains(e.target) && !popover.hidden) hide();
        }, { passive: true });
    });

    // Escape key dismissal — works from anywhere on the host page.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !popover.hidden) {
            hide();
            e.stopPropagation();
        }
    });

    // The rendition iframe has its own document; pointer / keyboard events
    // inside it don't bubble to the host. Hook each chapter's iframe to
    // also dismiss the popover.
    rendition.hooks.content.register((contents) => {
        const doc = contents.document;
        const dismissOnPointer = () => { if (!popover.hidden) hide(); };
        doc.addEventListener('mousedown', dismissOnPointer, { passive: true });
        doc.addEventListener('touchstart', dismissOnPointer, { passive: true });
        doc.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !popover.hidden) hide();
        });
    });

    async function lookup(word) {
        try {
            const data = await get('api/dictionary.php?word=' + encodeURIComponent(word));
            renderEntries(word, data || []);
        } catch (e) {
            popover.querySelector('.dictionary-body').innerHTML =
                '<p class="dictionary-empty">Lookup unavailable right now.</p>';
        }
    }

    function renderEntries(word, entries) {
        const body = popover.querySelector('.dictionary-body');
        if (!entries.length) {
            body.innerHTML = '<p class="dictionary-empty">No definition found for ' +
                             escapeHtml(word) + '.</p>';
            return;
        }
        const first = entries[0];
        popover.querySelector('.dictionary-phonetic').textContent = first.phonetic || '';
        const parts = [];
        first.meanings.slice(0, 3).forEach((m) => {
            parts.push('<section class="dictionary-meaning">');
            parts.push(`<h4>${escapeHtml(m.partOfSpeech || '')}</h4>`);
            parts.push('<ol class="dictionary-defs">');
            m.definitions.forEach((d) => {
                parts.push('<li>');
                parts.push(escapeHtml(d.definition || ''));
                if (d.example) parts.push(`<em>“${escapeHtml(d.example)}”</em>`);
                parts.push('</li>');
            });
            parts.push('</ol>');
            parts.push('</section>');
        });
        body.innerHTML = parts.join('');
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, (c) =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
}
