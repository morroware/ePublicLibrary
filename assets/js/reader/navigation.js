/**
 * Navigation: prev/next buttons, hotspots, keyboard, swipe.
 */

export function initNavigation(ctx) {
    const { rendition } = ctx;

    // Hotspot regions
    document.querySelectorAll('.reader-hotspot').forEach((spot) => {
        spot.addEventListener('click', () => {
            spot.dataset.direction === 'prev' ? rendition.prev() : rendition.next();
        });
    });

    // Keyboard — both the page and within epub.js iframe
    function keyHandler(e) {
        // Ignore typing in inputs
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
        switch (e.key) {
            case 'ArrowLeft':
            case 'PageUp':
                rendition.prev();
                e.preventDefault();
                break;
            case 'ArrowRight':
            case 'PageDown':
            case ' ':
                rendition.next();
                e.preventDefault();
                break;
        }
    }
    document.addEventListener('keydown', keyHandler);
    rendition.on('keydown', keyHandler);
    rendition.hooks.content.register((contents) => {
        contents.document.addEventListener('keydown', keyHandler);
    });

    // Touch swipe
    let touchStart = null;
    const SWIPE_THRESHOLD = 50;
    function ts(e) { touchStart = e.changedTouches[0]; }
    function te(e) {
        if (!touchStart) return;
        const t = e.changedTouches[0];
        const dx = t.clientX - touchStart.clientX;
        const dy = t.clientY - touchStart.clientY;
        if (Math.abs(dx) > SWIPE_THRESHOLD && Math.abs(dx) > Math.abs(dy)) {
            dx > 0 ? rendition.prev() : rendition.next();
        }
        touchStart = null;
    }
    ctx.viewer.addEventListener('touchstart', ts, { passive: true });
    ctx.viewer.addEventListener('touchend', te, { passive: true });
    rendition.hooks.content.register((contents) => {
        contents.document.addEventListener('touchstart', ts, { passive: true });
        contents.document.addEventListener('touchend', te, { passive: true });
    });
}
