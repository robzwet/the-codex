/* The Codex — editor, [[wikilink]] autocomplete, and infobox meta rows. */
(function () {
    'use strict';

    /* ---------- Infobox meta rows ---------- */
    const metaRows = document.getElementById('meta-rows');
    const addMeta = document.getElementById('add-meta');
    if (metaRows && addMeta) {
        addMeta.addEventListener('click', function () {
            const row = document.createElement('div');
            row.className = 'meta-row';
            row.innerHTML =
                '<input type="text" name="meta_key[]" placeholder="Field">' +
                '<input type="text" name="meta_value[]" placeholder="Value">' +
                '<button type="button" class="btn btn-sm meta-remove">✕</button>';
            metaRows.appendChild(row);
        });
        metaRows.addEventListener('click', function (e) {
            if (e.target.classList.contains('meta-remove')) {
                e.target.closest('.meta-row').remove();
            }
        });
    }

    /* ---------- Rich-text editor ---------- */
    const editor = document.getElementById('editor');
    const form = document.getElementById('page-form');
    const toolbar = document.getElementById('toolbar');
    if (!editor || !form) return;

    // New lines become <p> (allowed by the sanitizer) instead of <div> (stripped).
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}

    if (toolbar) {
        toolbar.addEventListener('click', function (e) {
            const btn = e.target.closest('button');
            if (!btn) return;
            e.preventDefault();
            editor.focus();
            if (btn.dataset.cmd) {
                document.execCommand(btn.dataset.cmd, false, null);
            } else if (btn.dataset.block) {
                document.execCommand('formatBlock', false, btn.dataset.block);
            } else if (btn.hasAttribute('data-wikilink')) {
                insertText('[[]]');
                // place caret between the brackets
                const sel = window.getSelection();
                if (sel.rangeCount) {
                    const r = sel.getRangeAt(0);
                    r.setStart(r.startContainer, r.startOffset - 2);
                    r.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(r);
                }
                editor.dispatchEvent(new Event('input'));
            }
        });
    }

    // Sync the editor HTML into the hidden field on submit.
    form.addEventListener('submit', function () {
        document.getElementById('body_html').value = editor.innerHTML;
    });

    function insertText(text) {
        document.execCommand('insertText', false, text);
    }

    /* ---------- [[ ]] autocomplete ---------- */
    const searchUrl = form.dataset.searchUrl;
    let box = null;
    let items = [];
    let active = -1;
    let debounce = null;

    editor.addEventListener('input', function () {
        const ctx = openTokenBeforeCaret();
        if (!ctx) { closeBox(); return; }
        clearTimeout(debounce);
        debounce = setTimeout(function () { runSearch(ctx.query); }, 150);
    });

    editor.addEventListener('keydown', function (e) {
        if (!box) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
        else if (e.key === 'Enter') { e.preventDefault(); choose(active < 0 ? 0 : active); }
        else if (e.key === 'Escape') { closeBox(); }
    });

    document.addEventListener('click', function (e) {
        if (box && !box.contains(e.target)) closeBox();
    });

    // Find an unclosed [[query at the caret. Returns {node, start, offset, query} or null.
    function openTokenBeforeCaret() {
        const sel = window.getSelection();
        if (!sel.rangeCount) return null;
        const range = sel.getRangeAt(0);
        const node = range.startContainer;
        if (node.nodeType !== Node.TEXT_NODE) return null;
        const before = node.textContent.slice(0, range.startOffset);
        const m = before.match(/\[\[([^\[\]]*)$/);
        if (!m) return null;
        return { node: node, start: m.index, offset: range.startOffset, query: m[1] };
    }

    function runSearch(query) {
        if (!query) { closeBox(); return; }
        fetch(searchUrl + '?q=' + encodeURIComponent(query), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { showBox(data.results || []); })
            .catch(function () { closeBox(); });
    }

    function showBox(results) {
        items = results;
        active = 0;
        if (!results.length) { closeBox(); return; }
        if (!box) {
            box = document.createElement('div');
            box.className = 'wk-autocomplete';
            document.body.appendChild(box);
        }
        box.innerHTML = '';
        results.forEach(function (r, i) {
            const el = document.createElement('div');
            el.className = 'wk-item' + (i === 0 ? ' active' : '');
            el.textContent = r.title;
            if (!r.exists) {
                const tag = document.createElement('span');
                tag.className = 'new-tag';
                tag.textContent = '  + create';
                el.appendChild(tag);
            }
            el.addEventListener('mousedown', function (ev) { ev.preventDefault(); choose(i); });
            box.appendChild(el);
        });
        positionBox();
    }

    function positionBox() {
        const sel = window.getSelection();
        if (!sel.rangeCount) return;
        const rect = sel.getRangeAt(0).getBoundingClientRect();
        box.style.top = (window.scrollY + rect.bottom + 4) + 'px';
        box.style.left = (window.scrollX + rect.left) + 'px';
    }

    function move(delta) {
        const els = box.querySelectorAll('.wk-item');
        els[active] && els[active].classList.remove('active');
        active = (active + delta + els.length) % els.length;
        els[active].classList.add('active');
    }

    function choose(i) {
        const item = items[i];
        if (!item) { closeBox(); return; }
        const ctx = openTokenBeforeCaret();
        if (!ctx) { closeBox(); return; }
        const text = ctx.node.textContent;
        const insert = '[[' + item.title + ']]';
        ctx.node.textContent = text.slice(0, ctx.start) + insert + text.slice(ctx.offset);
        // caret just after the inserted token
        const caret = ctx.start + insert.length;
        const sel = window.getSelection();
        const range = document.createRange();
        range.setStart(ctx.node, caret);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
        closeBox();
    }

    function closeBox() {
        if (box) { box.remove(); box = null; }
        items = []; active = -1;
    }
})();
