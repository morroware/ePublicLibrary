/**
 * Focus trap for modal/dialog patterns.
 *
 *   const trap = focusTrap(panelEl, { onEscape: () => closePanel() });
 *   trap.activate();
 *   trap.deactivate();
 */

const FOCUSABLE = [
    'a[href]', 'button:not([disabled])', 'input:not([disabled])',
    'select:not([disabled])', 'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])', 'details > summary',
].join(',');

export function focusTrap(container, { onEscape } = {}) {
    let lastFocused = null;
    function trapKey(e) {
        if (e.key === 'Escape' && typeof onEscape === 'function') {
            e.stopPropagation();
            onEscape();
            return;
        }
        if (e.key !== 'Tab') return;
        const focusable = container.querySelectorAll(FOCUSABLE);
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    return {
        activate() {
            lastFocused = document.activeElement;
            container.addEventListener('keydown', trapKey);
            const first = container.querySelector(FOCUSABLE);
            (first || container).focus();
        },
        deactivate() {
            container.removeEventListener('keydown', trapKey);
            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
        },
    };
}
