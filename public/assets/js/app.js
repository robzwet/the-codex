/* The Codex — field-template editor + sectioned page editor with [[wikilinks]]. */
(function () {
    'use strict';

    /* ---------- Field-template editor (per-category "Manage fields") ---------- */
    const fieldRows = document.getElementById('field-rows');
    const addField = document.getElementById('add-field');
    const rowTemplate = document.getElementById('field-row-template');
    if (fieldRows && addField && rowTemplate) {
        addField.addEventListener('click', function () {
            fieldRows.appendChild(rowTemplate.content.cloneNode(true));
        });
        fieldRows.addEventListener('click', function (e) {
            if (e.target.classList.contains('field-remove')) {
                e.target.closest('.field-edit-row').remove();
            }
        });
    }

    /* ---------- Page form ---------- */
    const form = document.getElementById('page-form');
    const sectionsWrap = document.getElementById('sections');
    if (!form || !sectionsWrap) return;

    const toolbar = document.getElementById('toolbar');
    const sectionTpl = document.getElementById('section-row-template');
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}

    function editors() { return Array.prototype.slice.call(document.querySelectorAll('.section-editor')); }
    let activeEditor = null;
    document.addEventListener('focusin', function (e) {
        if (e.target.classList && e.target.classList.contains('section-editor')) activeEditor = e.target;
    });
    function currentEditor() {
        return (activeEditor && document.body.contains(activeEditor)) ? activeEditor : (editors()[0] || null);
    }

    /* --- Shared toolbar acts on the currently open/focused section --- */
    if (toolbar) {
        toolbar.addEventListener('mousedown', function (e) { if (e.target.closest('button')) e.preventDefault(); });
        toolbar.addEventListener('click', function (e) {
            const btn = e.target.closest('button');
            if (!btn) return;
            e.preventDefault();
            const ed = currentEditor();
            if (!ed) return;
            if (btn.dataset.cmd) {
                ed.focus();
                document.execCommand(btn.dataset.cmd, false, null);
            } else if (btn.dataset.block) {
                ed.focus();
                document.execCommand('formatBlock', false, btn.dataset.block);
            } else if (btn.hasAttribute('data-wikilink')) {
                // No focus() here — focusing collapses the selection we want to wrap.
                const sel = window.getSelection();
                if (!sel || !sel.rangeCount) return;
                const range = sel.getRangeAt(0);
                const selectedText = range.toString(); // range.toString works without doc focus
                if (!range.collapsed && selectedText) {
                    // Wrap the selection: "word" -> "[[word]]".
                    const node = document.createTextNode('[[' + selectedText + ']]');
                    range.deleteContents();
                    range.insertNode(node);
                    const after = document.createRange();
                    after.setStartAfter(node);
                    after.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(after);
                } else {
                    // No selection: insert empty brackets with the caret inside.
                    const node = document.createTextNode('[[]]');
                    range.insertNode(node);
                    const mid = document.createRange();
                    mid.setStart(node, 2);
                    mid.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(mid);
                }
                ed.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }

    /* --- Add / remove sections; keep title input from toggling the <details> --- */
    const addSection = document.getElementById('add-section');
    if (addSection && sectionTpl) {
        addSection.addEventListener('click', function () {
            sectionsWrap.appendChild(sectionTpl.content.cloneNode(true));
        });
    }
    sectionsWrap.addEventListener('click', function (e) {
        if (e.target.closest('summary') &&
            (e.target.classList.contains('section-title-input') || e.target.closest('.section-remove'))) {
            e.preventDefault(); // don't toggle the accordion
        }
        if (e.target.closest('.section-remove')) {
            e.target.closest('.section-edit').remove();
        }
    });

    /* --- Serialize sections into the hidden field on submit --- */
    form.addEventListener('submit', function () {
        const data = editors().map(function (ed) {
            const det = ed.closest('.section-edit');
            return { title: det.querySelector('.section-title-input').value.trim(), html: ed.innerHTML };
        }).filter(function (s) { return s.title !== ''; });
        document.getElementById('sections_json').value = JSON.stringify(data);
    });

    /* ---------- Category change: reload infobox fields + section titles ---------- */
    const catSelect = document.getElementById('category-select');
    const fieldsSection = document.getElementById('fields-section');
    const manageLink = document.getElementById('manage-fields-link');
    const fieldsUrl = form.dataset.fieldsUrl;
    const sectionsUrl = form.dataset.sectionsUrl;
    const pageId = form.dataset.pageId;
    const cid = fieldsUrl ? fieldsUrl.match(/campaign\/(\d+)/)[1] : null;
    const isCreate = !pageId;

    function allEditorsEmpty() {
        return editors().every(function (ed) { return ed.textContent.trim() === ''; });
    }
    function rebuildSections(titles) {
        sectionsWrap.innerHTML = '';
        titles.forEach(function (t) {
            const node = sectionTpl.content.cloneNode(true);
            node.querySelector('.section-title-input').value = t;
            node.querySelector('.section-edit').removeAttribute('open');
            sectionsWrap.appendChild(node);
        });
    }

    if (catSelect) {
        catSelect.addEventListener('change', function () {
            const category = catSelect.value;
            if (fieldsSection && fieldsUrl) {
                let url = fieldsUrl + '?category=' + encodeURIComponent(category);
                if (pageId) url += '&page=' + encodeURIComponent(pageId);
                fieldsSection.innerHTML = '<p class="muted">Loading…</p>';
                fetch(url).then(function (r) { return r.text(); }).then(function (html) {
                    fieldsSection.innerHTML = html;
                }).catch(function () { fieldsSection.innerHTML = '<p class="muted">Could not load fields.</p>'; });
            }
            // Only reshape sections on a new page whose sections are still untouched.
            if (isCreate && sectionsUrl && allEditorsEmpty()) {
                fetch(sectionsUrl + '?category=' + encodeURIComponent(category))
                    .then(function (r) { return r.json(); })
                    .then(function (d) { rebuildSections(d.titles || []); })
                    .catch(function () {});
            }
            if (manageLink) {
                if (category) {
                    manageLink.href = '/campaign/' + cid + '/category/' + category + '/fields';
                    manageLink.removeAttribute('hidden');
                } else {
                    manageLink.setAttribute('hidden', '');
                }
            }
        });
    }

    /* ---------- [[ ]] autocomplete (delegated across all section editors) ---------- */
    let box = null, items = [], active = -1, debounce = null;
    const searchUrl = form.dataset.searchUrl;

    sectionsWrap.addEventListener('input', function (e) {
        if (!e.target.classList.contains('section-editor')) return;
        const ctx = openTokenBeforeCaret();
        if (!ctx) { closeBox(); return; }
        clearTimeout(debounce);
        debounce = setTimeout(function () { runSearch(ctx.query); }, 150);
    });
    sectionsWrap.addEventListener('keydown', function (e) {
        if (!box || !e.target.classList.contains('section-editor')) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
        else if (e.key === 'Enter') { e.preventDefault(); choose(active < 0 ? 0 : active); }
        else if (e.key === 'Escape') { closeBox(); }
    });
    document.addEventListener('click', function (e) { if (box && !box.contains(e.target)) closeBox(); });

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
        items = results; active = 0;
        if (!results.length) { closeBox(); return; }
        if (!box) { box = document.createElement('div'); box.className = 'wk-autocomplete'; document.body.appendChild(box); }
        box.innerHTML = '';
        results.forEach(function (r, i) {
            const el = document.createElement('div');
            el.className = 'wk-item' + (i === 0 ? ' active' : '');
            el.textContent = r.title;
            if (!r.exists) { const tag = document.createElement('span'); tag.className = 'new-tag'; tag.textContent = '  + create'; el.appendChild(tag); }
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
        const caret = ctx.start + insert.length;
        const sel = window.getSelection();
        const range = document.createRange();
        range.setStart(ctx.node, caret);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
        closeBox();
    }
    function closeBox() { if (box) { box.remove(); box = null; } items = []; active = -1; }
})();
