/**
 * Live-region toast notifications. Uses aria-live so screen readers
 * announce them.
 */

let container;
function ensureContainer() {
    if (container) return container;
    container = document.createElement('div');
    container.id = 'toast-region';
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-atomic', 'true');
    container.style.position = 'fixed';
    container.style.bottom = '1.5rem';
    container.style.right = '1.5rem';
    container.style.zIndex = '300';
    container.style.display = 'flex';
    container.style.flexDirection = 'column';
    container.style.gap = '0.5rem';
    document.body.appendChild(container);
    return container;
}

export function toast(message, { kind = 'info', duration = 2500 } = {}) {
    const c = ensureContainer();
    const el = document.createElement('div');
    el.className = `toast toast-${kind}`;
    el.role = kind === 'error' ? 'alert' : 'status';
    el.textContent = message;
    c.appendChild(el);
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transition = 'opacity 250ms ease';
    }, duration - 250);
    setTimeout(() => { el.remove(); }, duration);
}
