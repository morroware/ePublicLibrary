/**
 * Immersive mode — hide the reader chrome (header, full progress info)
 * and leave only a thin progress strip plus the pages of text.
 *
 * Toggled by the btn-immersive button in the reader header. Within the
 * mode, a single tap on the central viewport area reveals controls
 * briefly; another center tap (or the Escape key) leaves the mode.
 */

const CLASS = 'is-immersive';
const STORAGE_KEY = 'elib-reader-immersive';

export function initImmersive(ctx) {
    const shell = ctx.shell;
    const btn = document.getElementById('btn-immersive');
    if (!shell || !btn) return;

    // Restore preference
    if (localStorage.getItem(STORAGE_KEY) === '1') {
        enter();
    }

    btn.addEventListener('click', () => {
        shell.classList.contains(CLASS) ? leave() : enter();
    });

    // Tap-center while in immersive mode → flash the chrome for a few seconds.
    const viewport = document.getElementById('reader-viewport');
    if (viewport) {
        viewport.addEventListener('click', (e) => {
            if (!shell.classList.contains(CLASS)) return;
            // Ignore taps on hotspots (they're for page nav)
            if (e.target.closest('.reader-hotspot')) return;
            shell.classList.add('immersive-reveal');
            setTimeout(() => shell.classList.remove('immersive-reveal'), 2200);
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && shell.classList.contains(CLASS)) {
            leave();
        }
        if (e.key === 'F11' || (e.key === 'i' && e.ctrlKey && e.shiftKey)) {
            // Power-user toggle
            e.preventDefault();
            shell.classList.contains(CLASS) ? leave() : enter();
        }
    });

    function enter() {
        shell.classList.add(CLASS);
        btn.setAttribute('aria-pressed', 'true');
        btn.classList.add('is-active');
        localStorage.setItem(STORAGE_KEY, '1');
    }
    function leave() {
        shell.classList.remove(CLASS);
        shell.classList.remove('immersive-reveal');
        btn.setAttribute('aria-pressed', 'false');
        btn.classList.remove('is-active');
        localStorage.removeItem(STORAGE_KEY);
    }
}
