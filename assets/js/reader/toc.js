/**
 * Table of contents panel — load TOC from the EPUB navigation.
 */

export function initToc(ctx) {
    const { book, rendition } = ctx;
    const listEl = document.getElementById('toc-list');
    if (!listEl) return;

    book.loaded.navigation.then((nav) => {
        listEl.innerHTML = '';
        listEl.removeAttribute('aria-busy');
        if (!nav.toc || nav.toc.length === 0) {
            const li = document.createElement('li');
            li.className = 'reader-panel-empty';
            li.textContent = 'No table of contents available.';
            listEl.appendChild(li);
            return;
        }
        renderItems(nav.toc, listEl, 0, rendition);
    }).catch(() => {
        listEl.innerHTML = '<li class="reader-panel-empty">Could not load table of contents.</li>';
    });
}

function renderItems(items, parent, depth, rendition) {
    items.forEach((item) => {
        const li = document.createElement('li');
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'toc-item';
        btn.style.paddingLeft = (depth * 1) + 'rem';
        btn.textContent = item.label.trim();
        btn.addEventListener('click', () => {
            rendition.display(item.href);
            // Close the panel
            const panel = document.getElementById('panel-toc');
            if (panel) panel.hidden = true;
        });
        li.appendChild(btn);
        parent.appendChild(li);
        if (item.subitems && item.subitems.length > 0) {
            const sub = document.createElement('ol');
            sub.className = 'reader-panel-list';
            parent.appendChild(sub);
            renderItems(item.subitems, sub, depth + 1, rendition);
        }
    });
}
