/**
 * ARIA combobox attached to an existing <input role="combobox"> + <ul role="listbox">.
 *
 * Usage:
 *   combobox({
 *     input:    document.getElementById('searchInput'),
 *     listbox:  document.getElementById('autocomplete-listbox'),
 *     fetch:    async (term) => fetch(`...?q=${term}`).then(r => r.json()),
 *     onSelect: (value) => { input.value = value; input.form.submit(); },
 *   });
 */

export function combobox({ input, listbox, fetch: fetchFn, onSelect }) {
    let suggestions = [];
    let activeIndex = -1;
    let lastTerm = '';
    let debounceHandle = null;
    let requestSeq = 0;

    function open() {
        listbox.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }
    function close() {
        listbox.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
        input.removeAttribute('aria-activedescendant');
    }

    function render() {
        listbox.innerHTML = '';
        suggestions.forEach((s, i) => {
            const li = document.createElement('li');
            li.id = `autocomplete-option-${i}`;
            li.className = 'autocomplete-option';
            li.role = 'option';
            li.textContent = s;
            li.setAttribute('aria-selected', i === activeIndex ? 'true' : 'false');
            li.addEventListener('mousedown', e => {
                e.preventDefault();
                select(i);
            });
            listbox.appendChild(li);
        });
        if (suggestions.length > 0) open(); else close();
    }

    function setActive(i) {
        activeIndex = (i + suggestions.length) % (suggestions.length || 1);
        Array.from(listbox.children).forEach((c, idx) =>
            c.setAttribute('aria-selected', idx === activeIndex ? 'true' : 'false')
        );
        if (suggestions.length > 0) {
            input.setAttribute('aria-activedescendant', `autocomplete-option-${activeIndex}`);
        }
    }

    function select(i) {
        if (i < 0 || i >= suggestions.length) return;
        const value = suggestions[i];
        if (typeof onSelect === 'function') {
            onSelect(value);
        } else {
            input.value = value;
        }
        close();
    }

    async function load(term) {
        const seq = ++requestSeq;
        try {
            const results = await fetchFn(term);
            if (seq !== requestSeq) return;  // stale response
            suggestions = Array.isArray(results) ? results.slice(0, 8) : [];
            render();
        } catch {
            suggestions = [];
            render();
        }
    }

    input.addEventListener('input', () => {
        const term = input.value.trim();
        if (term === lastTerm) return;
        lastTerm = term;
        clearTimeout(debounceHandle);
        if (term.length < 2) { close(); suggestions = []; return; }
        debounceHandle = setTimeout(() => load(term), 220);
    });

    input.addEventListener('keydown', (e) => {
        if (listbox.hidden) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive(activeIndex + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(activeIndex - 1); }
        else if (e.key === 'Enter')   { if (activeIndex >= 0) { e.preventDefault(); select(activeIndex); } }
        else if (e.key === 'Escape')  { close(); }
    });

    input.addEventListener('blur', () => setTimeout(close, 120));
    input.addEventListener('focus', () => {
        if (suggestions.length > 0) open();
    });

    return { close };
}
