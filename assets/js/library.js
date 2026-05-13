/**
 * Library page entry — search autocomplete, theme toggle, sort, book-card a11y.
 */

import { initThemeToggle } from './shared/theme.js';
import { combobox } from './shared/combobox.js';
import { get, url } from './shared/api.js';
import { registerServiceWorker } from './shared/sw-register.js';

registerServiceWorker();

/* ---- Theme ---- */
initThemeToggle();

/* ---- Sort dropdowns auto-submit ---- */
['sortBy', 'sortOrder'].forEach((id) => {
    const el = document.getElementById(id);
    if (el && el.form) {
        el.addEventListener('change', () => el.form.submit());
    }
});

/* ---- Search autocomplete ---- */
const searchInput = document.getElementById('searchInput');
const listbox = document.getElementById('autocomplete-listbox');
if (searchInput && listbox) {
    combobox({
        input: searchInput,
        listbox,
        async fetch(term) {
            try {
                return await get(`api/search.php?q=${encodeURIComponent(term)}`);
            } catch {
                return [];
            }
        },
        onSelect(value) {
            searchInput.value = value;
            searchInput.form.submit();
        },
    });

    // Position listbox under the input — flip above when the bottom is
    // too close to the viewport edge (mobile keyboards, short screens).
    const wrap = searchInput.closest('.search-input-wrapper');
    const positionListbox = () => {
        if (!wrap) return;
        const r = wrap.getBoundingClientRect();
        // Height when populated; if hidden/empty, fall back to a reasonable estimate.
        const lbHeight = listbox.offsetHeight || 240;
        const spaceBelow = window.innerHeight - r.bottom;
        const spaceAbove = r.top;
        const flipAbove = spaceBelow < lbHeight + 16 && spaceAbove > spaceBelow;
        const top = flipAbove
            ? r.top + window.scrollY - lbHeight - 4
            : r.bottom + window.scrollY + 4;
        listbox.style.top = `${top}px`;
        listbox.style.left = `${r.left + window.scrollX}px`;
        listbox.style.width = `${r.width}px`;
    };
    positionListbox();
    window.addEventListener('resize', positionListbox);
    window.addEventListener('scroll', positionListbox, { passive: true });
}

/* ---- Book card keyboard activation ---- */
document.querySelectorAll('.book-card').forEach((card) => {
    const readUrl = card.dataset.readUrl;
    if (!readUrl) return;
    card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            // Don't intercept when focus is inside an interactive child
            if (e.target.closest('a, button')) return;
            e.preventDefault();
            window.location.href = readUrl;
        }
    });
    card.addEventListener('click', (e) => {
        // Allow native link clicks (download, details) to bubble through
        if (e.target.closest('a, button')) return;
        window.location.href = readUrl;
    });
});

/* ---- Lazy cover loading ---- *
 * Covers are already <div style="background-image"> in the template, so the
 * browser fetches them as needed. If we add a lazy hint, IntersectionObserver
 * can start them only when scrolled near. The current implementation relies on
 * browser native lazy behavior; explicit observer can be added later.
 */
