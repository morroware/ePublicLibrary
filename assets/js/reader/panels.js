/**
 * Open/close reader panels with focus management.
 */

import { focusTrap } from '../shared/focus-trap.js';

export function initPanels(ctx) {
    const panels = {
        'panel-toc':       { btn: 'btn-toc' },
        'panel-bookmarks': { btn: 'btn-bookmarks' },
        'panel-settings':  { btn: 'btn-settings' },
    };

    const traps = {};
    let activePanel = null;

    function open(id) {
        if (activePanel) close(activePanel);
        const el = document.getElementById(id);
        if (!el) return;
        el.hidden = false;
        document.getElementById(panels[id].btn)?.setAttribute('aria-expanded', 'true');
        document.getElementById(panels[id].btn)?.classList.add('is-active');
        if (!traps[id]) {
            traps[id] = focusTrap(el, { onEscape: () => close(id) });
        }
        traps[id].activate();
        activePanel = id;
    }

    function close(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.hidden = true;
        document.getElementById(panels[id].btn)?.setAttribute('aria-expanded', 'false');
        document.getElementById(panels[id].btn)?.classList.remove('is-active');
        traps[id]?.deactivate();
        if (activePanel === id) activePanel = null;
    }

    function toggle(id) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.hidden) open(id); else close(id);
    }

    // Wire trigger buttons
    Object.entries(panels).forEach(([panelId, cfg]) => {
        const btn = document.getElementById(cfg.btn);
        if (!btn) return;
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-controls', panelId);
        btn.addEventListener('click', () => toggle(panelId));
    });

    // Close buttons inside panels
    document.querySelectorAll('[data-close]').forEach((btn) => {
        btn.addEventListener('click', () => close(btn.dataset.close));
    });

    // Click-outside-to-close on the document body — but not on the buttons themselves
    document.addEventListener('mousedown', (e) => {
        if (!activePanel) return;
        const el = document.getElementById(activePanel);
        if (!el) return;
        const triggerBtn = document.getElementById(panels[activePanel].btn);
        if (el.contains(e.target) || (triggerBtn && triggerBtn.contains(e.target))) return;
        close(activePanel);
    });
}
