/**
 * Text-to-speech using the browser's SpeechSynthesis API.
 *
 * No backend, no third-party. The system's installed voices are used. We
 * pull the visible chapter text out of the epub.js iframe, split into
 * sentences, and speak them one at a time so we can highlight the active
 * sentence and advance to the next page when the current one finishes.
 */

const STORAGE_KEY = 'elib-tts-prefs';

export function initTts(ctx) {
    if (typeof window.speechSynthesis === 'undefined') {
        document.getElementById('btn-tts')?.setAttribute('hidden', '');
        return;
    }
    const { rendition } = ctx;
    const synth = window.speechSynthesis;
    const toolbar    = document.getElementById('tts-toolbar');
    const playBtn    = document.getElementById('tts-play');
    const pauseBtn   = document.getElementById('tts-pause');
    const stopBtn    = document.getElementById('tts-stop');
    const nextBtn    = document.getElementById('tts-next');
    const prevBtn    = document.getElementById('tts-prev');
    const rateInput  = document.getElementById('tts-rate');
    const rateOut    = document.getElementById('tts-rate-out');
    const voiceSel   = document.getElementById('tts-voice');
    const triggerBtn = document.getElementById('btn-tts');
    if (!toolbar || !triggerBtn) return;

    let prefs = loadPrefs();
    let sentences = [];
    let cursor = 0;
    let activeUtter = null;
    let active = false;

    rateInput.value = prefs.rate;
    rateOut.textContent = `${prefs.rate}x`;

    function loadPrefs() {
        try {
            return Object.assign({ rate: 1, voice: '' },
                JSON.parse(localStorage.getItem(STORAGE_KEY)) || {});
        } catch { return { rate: 1, voice: '' }; }
    }
    function savePrefs() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
    }

    function populateVoices() {
        const voices = synth.getVoices();
        voiceSel.innerHTML = '';
        if (voices.length === 0) {
            // Some browsers (Chrome) populate voices asynchronously; show a
            // placeholder so the empty <select> doesn't look broken.
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Loading voices…';
            placeholder.disabled = true;
            placeholder.selected = true;
            voiceSel.appendChild(placeholder);
            return;
        }
        voices.forEach((v) => {
            const opt = document.createElement('option');
            opt.value = v.name;
            opt.textContent = `${v.name} — ${v.lang}` + (v.default ? ' (default)' : '');
            if (prefs.voice && v.name === prefs.voice) opt.selected = true;
            voiceSel.appendChild(opt);
        });
    }
    populateVoices();
    synth.onvoiceschanged = populateVoices;

    // Derive the Pause/Resume label from the SpeechSynthesis state instead
    // of toggling it imperatively — keeps the button in sync even if state
    // changes via skip/rate-change/visibilitychange.
    function renderPauseLabel() {
        pauseBtn.textContent = synth.paused ? 'Resume' : 'Pause';
        pauseBtn.setAttribute('aria-pressed', synth.paused ? 'true' : 'false');
    }

    triggerBtn.addEventListener('click', () => {
        toolbar.hidden = !toolbar.hidden;
        triggerBtn.classList.toggle('is-active', !toolbar.hidden);
        triggerBtn.setAttribute('aria-expanded', String(!toolbar.hidden));
        if (toolbar.hidden) stop();
    });

    playBtn.addEventListener('click', () => start());
    pauseBtn.addEventListener('click', () => {
        if (synth.speaking && !synth.paused) {
            synth.pause();
        } else if (synth.paused) {
            synth.resume();
        }
        renderPauseLabel();
    });
    stopBtn.addEventListener('click', () => stop());
    nextBtn.addEventListener('click', () => skip(1));
    prevBtn.addEventListener('click', () => skip(-1));
    rateInput.addEventListener('input', () => {
        prefs.rate = parseFloat(rateInput.value);
        rateOut.textContent = `${prefs.rate}x`;
        savePrefs();
        // Restart current sentence at the new rate
        if (active) {
            const at = cursor;
            stop();
            cursor = at;
            start();
        }
    });
    voiceSel.addEventListener('change', () => {
        prefs.voice = voiceSel.value;
        savePrefs();
        if (active) {
            const at = cursor;
            stop();
            cursor = at;
            start();
        }
    });

    function extractSentences() {
        const iframe = ctx.viewer.querySelector('iframe');
        const text = iframe?.contentDocument?.body?.innerText || '';
        const trimmed = text.replace(/\s+/g, ' ').trim();
        if (!trimmed) return [];
        return trimmed
            .split(/(?<=[.!?])\s+(?=[A-Z“"‘'])/)
            .filter((s) => s.length > 0);
    }

    function start() {
        if (active) return;
        sentences = extractSentences();
        if (sentences.length === 0) {
            sentences = [(ctx.viewer.querySelector('iframe')?.contentDocument?.body?.innerText || '').slice(0, 1000)];
        }
        cursor = Math.min(cursor, sentences.length - 1);
        if (cursor < 0) cursor = 0;
        active = true;
        speakCurrent();
    }

    function stop() {
        active = false;
        synth.cancel();
        clearHighlight();
        cursor = 0;
        renderPauseLabel();
    }

    function skip(delta) {
        if (!active) return;
        synth.cancel();
        cursor = Math.max(0, Math.min(sentences.length - 1, cursor + delta));
        speakCurrent();
    }

    function speakCurrent() {
        if (!active) return;
        if (cursor >= sentences.length) {
            // End of page — advance and continue
            rendition.next().then(() => {
                setTimeout(() => {
                    sentences = extractSentences();
                    cursor = 0;
                    if (sentences.length === 0) { stop(); return; }
                    speakCurrent();
                }, 300);
            }).catch(() => stop());
            return;
        }
        const text = sentences[cursor];
        const utter = new SpeechSynthesisUtterance(text);
        utter.rate = prefs.rate;
        utter.pitch = 1;
        const voices = synth.getVoices();
        const picked = voices.find((v) => v.name === prefs.voice) || voices[0];
        if (picked) utter.voice = picked;
        utter.onend = () => {
            clearHighlight();
            if (!active) return;
            cursor += 1;
            speakCurrent();
        };
        utter.onerror = () => stop();
        activeUtter = utter;
        highlightSentence(text);
        synth.speak(utter);
    }

    function highlightSentence(text) {
        // Best-effort visual indicator in the iframe: wrap the matching
        // text-node substring with a <span class="elib-tts-active">.
        try {
            const iframe = ctx.viewer.querySelector('iframe');
            const doc = iframe?.contentDocument;
            if (!doc || !text) return;
            clearHighlight();
            const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT);
            let node;
            while ((node = walker.nextNode())) {
                const idx = node.textContent.indexOf(text);
                if (idx >= 0) {
                    const range = doc.createRange();
                    range.setStart(node, idx);
                    range.setEnd(node, idx + text.length);
                    const span = doc.createElement('span');
                    span.className = 'elib-tts-active';
                    span.style.cssText = 'background: rgba(255, 234, 100, 0.6); border-radius: 3px;';
                    range.surroundContents(span);
                    return;
                }
            }
        } catch { /* ignore */ }
    }

    function clearHighlight() {
        try {
            const iframe = ctx.viewer.querySelector('iframe');
            iframe?.contentDocument?.querySelectorAll('.elib-tts-active').forEach((el) => {
                const parent = el.parentNode;
                while (el.firstChild) parent.insertBefore(el.firstChild, el);
                parent.removeChild(el);
                parent.normalize();
            });
        } catch { /* ignore */ }
    }

    // Stop when the user navigates away
    rendition.on('relocated', () => {
        if (active) {
            // Re-extract on the new page and continue
            sentences = extractSentences();
            cursor = 0;
            // Don't auto-restart; user navigated manually
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden && active) {
            synth.pause();
            renderPauseLabel();
        }
    });
}
