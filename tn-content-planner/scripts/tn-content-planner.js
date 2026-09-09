/* global TNCP, wp */
(() => {
    'use strict';
    const __ = (text) => wp.i18n.__(text, 'tn-content-planner');
    const flags = ['local', 'related', 'children', 'siblings', 'parents'];
    const columns = ['title', 'slug'];
    const app = document.getElementById('tncp-app');
    const dialog = document.getElementById('tncp-dialog');
    let type = TNCP.types[0]?.name, plan = { revision: 0, rows: [] }, catalog = [];
    let selected = new Set(), dirty = false, busy = false, step = 1, editing = null;

    function el(tag, attributes = {}, children = []) {
        const node = document.createElement(tag);
        for (const [key, value] of Object.entries(attributes)) {
            if (key === 'text') node.textContent = value;
            else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
            else if (key === 'checked' || key === 'disabled' || key === 'hidden') node[key] = value;
            else node.setAttribute(key, value);
        }
        for (const child of children) node.append(child);
        return node;
    }
    function button(text, action, primary = false, disabled = false) {
        return el('button', { type: 'button', class: `button ${primary ? 'button-primary' : ''}`, text, onclick: action, disabled });
    }
    function announce(message, error = false) {
        const notice = document.getElementById('tncp-notice');
        notice.hidden = !message;
        notice.className = `notice ${error ? 'notice-error' : 'notice-success'}`;
        notice.querySelector('p').textContent = message;
    }
    async function api(action, data) {
        const response = await fetch(`${TNCP.api}${action}/${type}`, {
            method: data ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': TNCP.nonce },
            credentials: 'same-origin', ...(data ? { body: JSON.stringify(data) } : {})
        });
        let result;
        try { result = await response.json(); } catch { throw new Error(__('The server returned an unreadable response. Your edits are still here; try again.')); }
        if (!response.ok) throw new Error(result.message || __('The request failed. Try again.'));
        return result;
    }
    async function work(task) {
        if (busy) return;
        busy = true; app.inert = true; app.setAttribute('aria-busy', 'true');
        const controls = [...app.querySelectorAll('button, input, select')];
        const previous = controls.map(control => control.disabled);
        controls.forEach(control => { control.disabled = true; });
        try { await task(); } catch (error) { announce(error.message, true); }
        finally {
            busy = false; app.inert = false; app.setAttribute('aria-busy', 'false');
            controls.forEach((control, index) => { if (control.isConnected) control.disabled = previous[index]; });
        }
    }
    function ask(title, description, choices) {
        return new Promise(resolve => {
            document.getElementById('tncp-dialog-title').textContent = title;
            document.getElementById('tncp-dialog-description').textContent = description;
            const actions = document.getElementById('tncp-dialog-actions');
            actions.replaceChildren(...choices.map(([value, label]) => el('button', { value, class: 'button', text: label })), el('button', { value: 'cancel', class: 'button', text: __('Cancel'), autofocus: '' }));
            dialog.returnValue = 'cancel';
            dialog.addEventListener('close', () => resolve(dialog.returnValue), { once: true });
            dialog.showModal();
        });
    }
    const uid = () => `r_${crypto.randomUUID()}`;
    function slug(value) {
        return value.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim().replace(/[^\p{L}\p{N}_%-]+/gu, '-').replace(/^-+|-+$/g, '');
    }
    function parseHTML(value) { const template = document.createElement('template'); template.innerHTML = value; return template.content; }
    function plain(value) { return parseHTML(value).textContent || ''; }
    function preview(value) {
        const source = parseHTML(value);
        const output = el('span');
        function copy(node, parent) {
            if (node.nodeType === Node.TEXT_NODE) { parent.append(document.createTextNode(node.textContent)); return; }
            if (node.nodeType !== Node.ELEMENT_NODE) return;
            if (['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT'].includes(node.tagName)) return;
            const target = ['I', 'SPAN', 'STRONG', 'EM', 'B', 'BR'].includes(node.tagName) ? el(node.tagName.toLowerCase()) : el('span');
            if (['I', 'SPAN'].includes(node.tagName)) {
                if (node.hasAttribute('class')) target.className = node.getAttribute('class');
                if (node.hasAttribute('aria-hidden')) target.setAttribute('aria-hidden', node.getAttribute('aria-hidden'));
            }
            node.childNodes.forEach(child => copy(child, target)); parent.append(target);
        }
        source.childNodes.forEach(node => copy(node, output));
        return output;
    }
    function newRow() {
        return { id: uid(), title: '', slug: '', parent: '', template: 'single', flags: Object.fromEntries(flags.map(flag => [flag, false])), post_id: 0, baseline: null, confirmed: {} };
    }
    function parentId(row) {
        if (!row.parent) return 0;
        if (row.parent.startsWith('post:')) return Number(row.parent.slice(5));
        return plan.rows.find(item => `row:${item.id}` === row.parent)?.post_id || -1;
    }
    function parentKey(row) {
        if (!row.parent.startsWith('post:')) return row.parent;
        const mapped = plan.rows.find(item => item.post_id === Number(row.parent.slice(5)));
        return mapped ? `row:${mapped.id}` : row.parent;
    }
    function depth(row, seen = new Set()) {
        const key = row.id ? `row:${row.id}` : `post:${row.post_id}`;
        if (seen.has(key) || seen.size > 100) throw new Error(__('Choose a parent that does not create a circular hierarchy.'));
        seen.add(key);
        if (!row.parent) return 0;
        const parent = plan.rows.find(item => `row:${item.id}` === parentKey(row));
        if (parent) return 1 + depth(parent, seen);
        const post = catalog.find(item => `post:${item.id}` === row.parent);
        if (!post) return 1;
        return 1 + depth({ post_id: post.id, parent: post.parent ? `post:${post.parent}` : '' }, seen);
    }
    function pattern(row) { return `${type}-${depth(row)}-${row.template}-${flags.filter(flag => row.flags[flag]).length}`; }
    function orderedRows() {
        const result = [], seen = new Set();
        function visit(row) {
            if (seen.has(row.id)) return;
            seen.add(row.id); result.push(row);
            plan.rows.filter(child => parentKey(child) === `row:${row.id}`).forEach(visit);
        }
        plan.rows.filter(row => !parentKey(row).startsWith('row:')).forEach(visit);
        plan.rows.forEach(visit);
        return result;
    }
    function markDirty() { dirty = true; step = 1; }
    async function load() {
        await work(async () => {
            const data = await api('plan'); plan = data.plan; catalog = data.catalog; dirty = false; selected.clear(); step = 1; render();
        });
    }
    async function change(row, field, value) {
        if (busy || row[field] === value) return;
        const old = row[field];
        if (field === 'slug') value = slug(value);
        if (row.post_id && ['title', 'slug', 'parent'].includes(field)) {
            const message = field === 'parent' ? __('Do you want to move this post under the new parent?') : field === 'title' ? __('Do you want to change the linked post’s title or create a new item in the plan with the new name?') : __('Do you want to change the linked post’s slug or create a new item in the plan with this new slug?');
            const choices = [['update', field === 'parent' ? __('Move linked post') : __('Change linked post')]];
            if (field !== 'parent') choices.push(['new', __('Create new plan item')]);
            const decision = await ask(__('Linked post change'), `${message} ${__('Post changes are applied in Step 2.')}`, choices);
            if (decision === 'cancel') { render(); return; }
            if (decision === 'new') {
                const copy = { ...structuredClone(row), id: uid(), post_id: 0, baseline: null, confirmed: {} };
                copy[field] = value;
                if (field === 'title') copy.slug = slug(plain(value));
                let base = copy.slug || 'new-item', suffix = 2;
                while (plan.rows.some(item => item.slug === copy.slug) || catalog.some(post => post.slug === copy.slug)) copy.slug = `${base}-${suffix++}`;
                plan.rows.push(copy); editing = copy.id; markDirty(); render(); return;
            }
            if (Array.isArray(row.confirmed)) row.confirmed = {};
            row.confirmed[field] = true;
        }
        row[field] = value;
        try { plan.rows.forEach(item => depth(item)); } catch (error) { row[field] = old; announce(error.message, true); render(); return; }
        if (field === 'slug' && !row.post_id) {
            const matches = catalog.filter(post => post.slug === value);
            if (matches.length === 1 && !plan.rows.some(item => item.id !== row.id && item.post_id === matches[0].id)) {
                const post = matches[0]; row.post_id = post.id;
                row.baseline = { title: post.title, slug: post.slug, parent: post.parent };
                if (!row.title) row.title = post.title;
                if (!row.parent) row.parent = post.parent ? `post:${post.parent}` : '';
                announce(__('Existing slug found. This row is now linked to Post ID') + ` ${post.id}.`);
            } else if (matches.length) announce(__('This slug is ambiguous or already linked in the plan. Choose a unique slug.'), true);
        }
        markDirty(); setTimeout(render, 0);
    }
    async function confirmPending() {
        // CSV and automatic slug mapping follow the same confirmation rules as typed edits.
        for (const row of [...plan.rows]) {
            if (!row.post_id) {
                const matches = catalog.filter(post => post.slug === row.slug);
                if (matches.length === 1) {
                    const post = matches[0]; row.post_id = post.id; row.baseline = { title: post.title, slug: post.slug, parent: post.parent };
                }
            }
            if (!row.baseline) continue;
            for (const field of ['title', 'slug', 'parent']) {
                const desired = field === 'parent' ? parentId(row) : row[field];
                if (desired !== row.baseline[field] && !row.confirmed[field]) {
                    const wanted = row[field];
                    row[field] = field === 'parent' ? (row.baseline.parent ? `post:${row.baseline.parent}` : '') : row.baseline[field];
                    await change(row, field, wanted);
                    if (row[field] !== wanted) return false;
                }
            }
        }
        return true;
    }
    async function save() {
        if (busy) return;
        if (!await confirmPending()) { render(); return; }
        await work(async () => {
            plan = await api('save', { revision: plan.revision, rows: plan.rows }); dirty = false; render(); announce(__('Plan saved. Select rows, then review and create.'));
        });
    }
    function titleControl(row) {
        if (editing === row.id || selected.has(row.id)) return el('input', {
            type: 'text', value: row.title, 'aria-label': __('Title, HTML allowed'), required: '', 'data-title': row.id,
            onchange: event => change(row, 'title', event.target.value),
            onblur: () => { editing = null; }
        });
        return el('button', { type: 'button', class: 'tncp-title', 'aria-label': __('Edit title') + ': ' + plain(row.title), onclick: () => {
            editing = row.id; render(); const input = app.querySelector(`[data-title="${row.id}"]`); input?.focus();
        } }, [preview(row.title || __('Click to add a title'))]);
    }
    function rowView(row) {
        const tr = el('tr', { 'data-row': row.id });
        const check = el('input', { type: 'checkbox', checked: selected.has(row.id), 'aria-label': __('Select') + ' ' + plain(row.title), onchange: event => {
            event.target.checked ? selected.add(row.id) : selected.delete(row.id); render();
        } });
        const title = el('td', { class: 'tncp-title-cell' }, [titleControl(row)]);
        title.style.paddingInlineStart = `${Math.min(depth(row), 12) * 1.25 + 0.5}rem`;
        const parent = el('select', { 'aria-label': __('Parent'), onchange: event => change(row, 'parent', event.target.value) });
        parent.append(el('option', { value: '', text: __('— No parent —') }));
        for (const item of orderedRows()) {
            if (item.id !== row.id) parent.append(el('option', { value: `row:${item.id}`, text: plain(item.title) || item.slug || __('Untitled plan row') }));
        }
        const group = el('optgroup', { label: __('Existing posts') });
        catalog.filter(post => post.id !== row.post_id).forEach(post => group.append(el('option', { value: `post:${post.id}`, text: `${plain(post.title)} (#${post.id})` })));
        parent.append(group); parent.value = row.parent;
        const template = el('select', { 'aria-label': __('Template'), onchange: event => change(row, 'template', event.target.value) }, ['single', 'archive', 'custom'].map(value => el('option', { value, text: __(value[0].toUpperCase() + value.slice(1)) })));
        template.value = row.template;
        tr.append(el('td', {}, [check]), title,
            el('td', {}, [el('input', { type: 'text', value: row.slug, required: '', maxlength: '200', 'aria-label': __('Content slug'), onchange: event => change(row, 'slug', event.target.value) })]),
            el('td', {}, [parent]), el('td', {}, [template]));
        flags.forEach(flag => tr.append(el('td', { class: 'tncp-flag' }, [el('input', { type: 'checkbox', checked: row.flags[flag], 'aria-label': __(flag[0].toUpperCase() + flag.slice(1)), onchange: event => { row.flags[flag] = event.target.checked; markDirty(); render(); } })])));
        tr.append(el('td', { class: 'tncp-pattern', text: pattern(row) }), el('td', { text: row.post_id ? String(row.post_id) : '—' }), el('td', {}, [el('button', { type: 'button', class: 'tncp-remove', title: __('Remove row'), 'aria-label': __('Remove row') + ': ' + (plain(row.title) || __('Untitled plan row')), onclick: async () => {
            if (plan.rows.some(item => parentKey(item) === `row:${row.id}`)) { announce(__('Move the child rows before removing their parent.'), true); return; }
            const answer = await ask(__('Remove plan row?'), __('This removes the row from the plan. Linked WordPress posts are kept.'), [['remove', __('Remove row')]]);
            if (answer === 'remove') { plan.rows = plan.rows.filter(item => item.id !== row.id); selected.delete(row.id); markDirty(); render(); }
        } }, [el('span', { class: 'dashicons dashicons-trash', 'aria-hidden': 'true' })])]));
        return tr;
    }
    function render() {
        const active = document.activeElement;
        const focusRow = active?.closest('[data-row]')?.dataset.row;
        const focusLabel = active?.getAttribute('aria-label');
        const selection = active?.tagName === 'INPUT' && active.type === 'text' ? [active.selectionStart, active.selectionEnd] : null;
        const steps = el('nav', { class: 'tncp-steps', 'aria-label': __('Planning steps') }, [
            button(__('1. Plan your WBS'), () => { step = 1; render(); }),
            button(__('2. Review & create'), () => { step = 2; render(); }, false, dirty || !selected.size)
        ]);
        steps.children[step - 1].setAttribute('aria-current', 'step');
        const tabs = el('div', { class: 'tncp-tabs', role: 'tablist', 'aria-label': __('Post types') });
        TNCP.types.forEach(item => tabs.append(el('button', {
            type: 'button', role: 'tab', id: `tncp-tab-${item.name}`, 'aria-selected': String(type === item.name), 'aria-controls': 'tncp-panel', tabindex: type === item.name ? '0' : '-1', text: item.label,
            onkeydown: event => {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault(); const index = TNCP.types.findIndex(entry => entry.name === item.name);
                const next = event.key === 'Home' ? 0 : event.key === 'End' ? TNCP.types.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + TNCP.types.length) % TNCP.types.length;
                tabs.children[next].focus(); tabs.children[next].click();
            },
            onclick: async () => {
                if (busy || type === item.name) return;
                if (dirty && await ask(__('Unsaved plan'), __('Save this post type first, or discard its unsaved edits to change tabs.'), [['discard', __('Discard edits')]]) !== 'discard') return;
                const prior = type; type = item.name;
                await work(async () => { try {
                    const data = await api('plan'); plan = data.plan; catalog = data.catalog; selected.clear(); dirty = false; step = 1; render();
                    document.getElementById(`tncp-tab-${type}`)?.focus();
                } catch (error) { type = prior; throw error; } });
            }
        })));
        const panel = el('section', { id: 'tncp-panel', class: 'tncp-panel', role: 'tabpanel', 'aria-labelledby': `tncp-tab-${type}` });
        app.replaceChildren(steps, tabs, panel);
        if (step === 2) { renderReview(panel); return; }
        panel.append(el('h2', { text: __('Build your content structure') }), el('p', { class: 'description', text: __('Add rows, choose parents, then save your plan. Click a title to edit its HTML. New posts are created as drafts in Step 2.') }));
        if (!TNCP.types.find(item => item.name === type)?.hierarchical) panel.append(el('p', { class: 'description', text: __('This post type is non-hierarchical. Parent relationships are stored, but its native permalinks and editor may not display them.') }));
        const file = el('input', { type: 'file', accept: '.csv,text/csv', class: 'screen-reader-text', id: 'tncp-csv', 'aria-label': __('Import CSV file'), onchange: event => importCSV(event.target.files[0]) });
        panel.append(el('div', { class: 'tncp-actions' }, [
            button(__('Add row'), () => { const row = newRow(); plan.rows.push(row); editing = row.id; markDirty(); render(); app.querySelector(`[data-title="${row.id}"]`)?.focus(); }),
            button(__('Import CSV'), () => file.click()), file, button(__('Download CSV template'), downloadTemplate),
            button(__('Refresh linked posts'), async () => {
                if (await ask(__('Refresh linked posts?'), __('This reloads titles, slugs and parents from WordPress and discards pending edits to linked rows and all unsaved edits in this tab.'), [['refresh', __('Refresh linked posts')]]) !== 'refresh') return;
                await work(async () => { plan = await api('refresh', { revision: plan.revision }); const data = await api('plan'); catalog = data.catalog; dirty = false; render(); announce(__('Linked posts refreshed.')); });
            })
        ]));
        if (!plan.rows.length) panel.append(el('div', { class: 'tncp-empty' }, [el('h3', { text: __('Start with the content you need') }), el('p', { text: __('Add your first row or import a CSV to build your work breakdown structure.') })]));
        else {
            const table = el('table', { class: 'widefat striped tncp-table' });
            const head = el('tr');
            [__('Select'), __('Title *'), __('Content slug *'), __('Parent'), __('Template'), __('Local'), __('Related'), __('Children'), __('Siblings'), __('Parents'), __('XP pattern'), __('Post ID'), __('Actions')].forEach(text => head.append(el('th', { scope: 'col', text })));
            table.append(el('thead', {}, [head]), el('tbody', {}, orderedRows().map(rowView)));
            panel.append(el('p', { class: 'description', text: __('Scroll the table horizontally to see all planning fields.') }));
            panel.append(el('div', { class: 'tncp-scroll', tabindex: '0', role: 'region', 'aria-label': __('Content plan table') }, [table]));
        }
        panel.append(el('div', { class: 'tncp-actions tncp-footer' }, [button(__('Save plan'), save, true),
            button(__('Select all'), () => { selected = new Set(plan.rows.map(row => row.id)); render(); }),
            button(__('Clear selection'), () => { selected.clear(); render(); }),
            button(__('Review & create selected'), () => { step = 2; render(); }, false, dirty || !selected.size),
            el('span', { text: `${plan.rows.length} ${__('rows')} · ${selected.size} ${__('selected')} · ${dirty ? __('Unsaved changes') : __('Saved plan')}`, role: 'status' })
        ]));
        if (focusRow && focusLabel) {
            const control = app.querySelector(`[data-row="${CSS.escape(focusRow)}"] [aria-label="${CSS.escape(focusLabel)}"]`);
            if (control) {
                control.focus({ preventScroll: true });
                if (selection && control.setSelectionRange) control.setSelectionRange(...selection);
            }
        }
    }
    function renderReview(panel) {
        panel.append(el('h2', { text: __('Review selected content') }), el('p', { text: __('Only the selected saved rows are applied. Include any uncreated parents. Existing posts keep their publication status and content. Maximum 50 rows per batch.') }));
        const list = el('ul', { class: 'tncp-review' });
        orderedRows().filter(row => selected.has(row.id)).forEach(row => {
            const changes = row.baseline ? ['title', 'slug', 'parent'].filter(field => (field === 'parent' ? parentId(row) : row[field]) !== row.baseline[field]) : [];
            const parent = row.parent.startsWith('row:') ? plan.rows.find(item => `row:${item.id}` === row.parent)?.title : catalog.find(post => `post:${post.id}` === row.parent)?.title;
            const item = el('li', {}, [el('strong', { text: plain(row.title) }), el('p', { text: `${row.post_id ? __('Update linked post') + ' #' + row.post_id : __('Create draft')} · /${row.slug} · ${__('Parent')}: ${parent ? plain(parent) : __('None')}` })]);
            for (const field of changes) item.append(el('p', { text: `${__(field[0].toUpperCase() + field.slice(1))}: ${String(row.baseline[field])} → ${field === 'parent' ? (plain(parent || '') || __('None')) : row[field]}` }));
            list.append(item);
        });
        panel.append(list, el('div', { class: 'tncp-actions' }, [button(__('Back to plan'), () => { step = 1; render(); }), button(__('Create / update selected'), async () => {
            await work(async () => {
                const result = await api('apply', { revision: plan.revision, selected: [...selected] }); plan = result.plan;
                result.completed.forEach(id => selected.delete(id)); dirty = false; step = 1;
                render(); announce(`${result.completed.length} ${__('rows applied.')} ${result.errors.join(' ')}`, result.errors.length > 0);
                const data = await api('plan'); catalog = data.catalog; render();
            });
        }, true, selected.size > 50 || !selected.size || dirty)]));
    }
    function parseCSV(text) {
        const records = []; let record = [], field = '', quoted = false, closed = false;
        text = text.replace(/^\uFEFF/, '');
        for (let index = 0; index < text.length; index++) {
            const char = text[index];
            if (quoted) {
                if (char === '"') { if (text[index + 1] === '"') { field += '"'; index++; } else { quoted = false; closed = true; } }
                else field += char;
            } else if (char === '"' && !field && !closed) quoted = true;
            else if (char === ',' || char === '\n' || char === '\r') {
                record.push(field); field = ''; closed = false;
                if (char !== ',') { if (record.some(value => value !== '')) records.push(record); record = []; if (char === '\r' && text[index + 1] === '\n') index++; }
            } else { if (closed || char === '"') throw new Error(__('Invalid CSV quoting. Use double quotes around fields containing commas.')); field += char; }
        }
        if (quoted) throw new Error(__('The CSV contains an unclosed quoted field.'));
        record.push(field); if (record.some(value => value !== '')) records.push(record);
        return records;
    }
    function downloadTemplate() {
        const url = URL.createObjectURL(new Blob(['\uFEFF' + columns.join(',') + '\r\n'], { type: 'text/csv;charset=utf-8' }));
        const link = el('a', { href: url, download: `tn-content-planner-${type}-template.csv` }); link.click(); URL.revokeObjectURL(url);
    }
    async function importCSV(file) {
        if (!file) return;
        try {
            if (file.size > 1024 * 1024) throw new Error(__('Use a CSV smaller than 1 MB.'));
            const records = parseCSV(await file.text());
            const headers = records.shift()?.map(value => value.trim().toLowerCase());
            if (!headers || headers.length !== columns.length || headers.some((value, index) => value !== columns[index])) throw new Error(__('Use the column order in the downloadable CSV template.'));
            if (records.length + plan.rows.length > 500) throw new Error(__('Use no more than 500 plan rows per post type.'));
            const imported = records.map((record, index) => {
                if (record.length !== columns.length) throw new Error(`${__('Wrong number of columns in CSV row')} ${index + 2}.`);
                const data = Object.fromEntries(headers.map((key, column) => [key, record[column]]));
                const row = newRow(); row.title = data.title; row.slug = slug(data.slug);
                if (!plain(row.title).trim() || !row.slug) throw new Error(`${__('Invalid title or slug in CSV row')} ${index + 2}.`);
                return row;
            });
            const combined = [...plan.rows, ...imported];
            if (new Set(combined.map(row => row.slug)).size !== combined.length) throw new Error(__('Duplicate slugs found. CSV imports append rows; they do not replace existing plan rows.'));
            plan.rows = combined;
            markDirty(); render(); announce(`${imported.length} ${__('rows imported. Review and save the plan. The import has not changed any posts.')}`);
        } catch (error) { announce(error.message, true); }
    }
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    if (type) load(); else { app.replaceChildren(el('p', { text: __('No editable post types are available.') })); app.setAttribute('aria-busy', 'false'); }
})();
